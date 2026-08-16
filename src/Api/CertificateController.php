<?php

declare(strict_types=1);

namespace App\Api;

use App\Certificate\CertificateParser;
use App\Certificate\ValidityChecker;
use App\Certificate\ValidityStatus;
use App\Exception\BadRequestException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\UrlBuilder;
use App\Log\Logger;
use App\Persistence\CertificateAuthorityRepository;
use App\Persistence\CertificateCheckRepository;
use App\Persistence\CertificateRepository;
use App\Persistence\Database;
use App\Persistence\KeyUsageRepository;
use DateTimeImmutable;
use DateTimeZone;

final class CertificateController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly Database $database,
        private readonly CertificateRepository $certificates,
        private readonly CertificateAuthorityRepository $authorities,
        private readonly KeyUsageRepository $keyUsages,
        private readonly CertificateCheckRepository $checks,
        private readonly CertificateParser $parser,
        private readonly ValidityChecker $validity,
        private readonly UrlBuilder $urls,
        private readonly Logger $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->authentication->userId($request);
        $page = Pagination::fromRequest($request);
        $status = $this->status($request);
        $now = $this->now();

        $rows = $this->certificates->findAllForOwner($userId, $status, $page->page, $page->perPage, $now);

        return Response::json([
            'data' => array_map(fn (array $row): array => $this->summary($row, $now), $rows),
            'meta' => $page->meta($this->certificates->countForOwner($userId, $status, $now)),
        ]);
    }

    public function create(Request $request): Response
    {
        $userId = $this->authentication->userId($request);
        $pem = $request->jsonBody()['pem'] ?? null;

        // 400 is not among the documented responses of this endpoint, so a body that
        // is not usable is reported as a validation failure like any other bad input.
        if (!is_string($pem)) {
            throw new ValidationException('The request body must contain a "pem" string.');
        }

        $certificate = $this->parser->parse($pem);
        $now = $this->now();

        // Four tables are written. A certificate without its first check would be a
        // record its own history cannot explain.
        $id = $this->database->transactional(function () use ($userId, $certificate, $now): int {
            $authorityId = $this->authorities->findOrCreate(
                $certificate->issuerDn,
                $certificate->issuerCommonName,
                $now,
            );

            $id = $this->certificates->insert($userId, $authorityId, $certificate, $now);

            foreach ($certificate->keyUsageCodes as $code) {
                $this->keyUsages->link($id, $this->keyUsages->findOrCreate($code, $code));
            }

            [$result, $detail] = $this->validity->check($certificate->validFrom, $certificate->validTo, $now);
            $this->checks->insertForOwnedCertificate($id, $userId, $result, $detail, $now);

            return $id;
        });

        $this->logger->info('Certificate registered.', ['user_id' => $userId, 'certificate_id' => $id]);

        return Response::json(
            $this->detail(
                $this->owned($id, $userId),
                $this->keyUsages->findAllForOwnedCertificate($id, $userId),
                $now,
            ),
            201,
            ['Location' => $this->urls->absolute('/api/v1/certificates/' . $id)],
        );
    }

    public function show(Request $request, array $parameters): Response
    {
        $userId = $this->authentication->userId($request);
        $id = $this->identifier($parameters);
        $now = $this->now();

        $row = $this->owned($id, $userId);

        return Response::json(
            $this->detail($row, $this->keyUsages->findAllForOwnedCertificate($id, $userId), $now)
            + ['checks' => array_map(
                static fn (array $check): array => self::checkView($check),
                $this->checks->findAllForOwnedCertificate($id, $userId),
            )],
        );
    }

    public function check(Request $request, array $parameters): Response
    {
        $userId = $this->authentication->userId($request);
        $id = $this->identifier($parameters);
        $now = $this->now();

        $row = $this->owned($id, $userId);

        [$result, $detail] = $this->validity->check(
            new DateTimeImmutable((string) $row['valid_from'], new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row['valid_to'], new DateTimeZone('UTC')),
            $now,
        );

        $checkId = $this->checks->insertForOwnedCertificate($id, $userId, $result, $detail, $now);

        return Response::json([
            'id' => $checkId,
            'checkedAt' => $now->format('Y-m-d\TH:i:s\Z'),
            'result' => $result->value,
            'detail' => $detail,
        ], 201);
    }

    /**
     * Ownership is part of the query, so a foreign record is indistinguishable from
     * one that never existed. The log records only what the application knows: who
     * asked for which identifier and that nothing came back (ADR-0006).
     *
     * @return array<string, mixed>
     */
    private function owned(int $id, int $userId): array
    {
        $row = $this->certificates->findForOwner($id, $userId);

        if ($row === null) {
            throw new NotFoundException(
                logReason: sprintf('user %d asked for certificate %d and the query returned nothing', $userId, $id),
            );
        }

        return $row;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function identifier(array $parameters): int
    {
        $raw = $parameters['certificateId'] ?? '';

        // A non-numeric identifier cannot name a record, so it is the same 404 as one
        // that names someone else's. Answering 400 here would separate the two.
        if (preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            throw new NotFoundException(logReason: sprintf('"%s" is not a certificate identifier', $raw));
        }

        return (int) $raw;
    }

    private function status(Request $request): ?ValidityStatus
    {
        $raw = $request->query['status'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return ValidityStatus::tryFrom($raw)
            ?? throw new BadRequestException('The status parameter is not a known validity status.');
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function summary(array $row, DateTimeImmutable $now): array
    {
        $validFrom = new DateTimeImmutable((string) $row['valid_from'], new DateTimeZone('UTC'));
        $validTo = new DateTimeImmutable((string) $row['valid_to'], new DateTimeZone('UTC'));

        return [
            'id' => (int) $row['id'],
            'commonName' => (string) $row['common_name'],
            'serialNumber' => (string) $row['serial_number'],
            'validFrom' => Timestamp::toIso8601($row['valid_from']),
            'validTo' => Timestamp::toIso8601($row['valid_to']),
            // Derived on the way out rather than stored: the answer depends on when it
            // is asked (FR-09).
            'status' => $this->validity->check($validFrom, $validTo, $now)[0]->value,
            'authority' => [
                'id' => (int) $row['authority_id'],
                'commonName' => (string) $row['authority_common_name'],
                'subjectDn' => (string) $row['authority_subject_dn'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $keyUsages
     * @return array<string, mixed>
     */
    private function detail(array $row, array $keyUsages, DateTimeImmutable $now): array
    {
        return $this->summary($row, $now) + [
            'subjectDn' => (string) $row['subject_dn'],
            'fingerprintSha256' => (string) $row['fingerprint_sha256'],
            'signatureAlgorithm' => $row['signature_algorithm'] !== null ? (string) $row['signature_algorithm'] : null,
            'publicKeyAlgorithm' => $row['public_key_algorithm'] !== null ? (string) $row['public_key_algorithm'] : null,
            'publicKeyBits' => $row['public_key_bits'] !== null ? (int) $row['public_key_bits'] : null,
            'keyUsages' => array_map(
                static fn (array $usage): array => ['code' => (string) $usage['code'], 'label' => (string) $usage['label']],
                $keyUsages,
            ),
            'createdAt' => Timestamp::toIso8601($row['created_at']),
        ];
    }

    /**
     * @param array<string, mixed> $check
     * @return array<string, mixed>
     */
    private static function checkView(array $check): array
    {
        return [
            'id' => (int) $check['id'],
            'checkedAt' => Timestamp::toIso8601($check['checked_at']),
            'result' => (string) $check['result'],
            'detail' => $check['detail'] !== null ? (string) $check['detail'] : null,
        ];
    }
}
