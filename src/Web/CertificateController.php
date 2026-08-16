<?php

declare(strict_types=1);

namespace App\Web;

use App\Api\Authentication;
use App\Certificate\CertificateParser;
use App\Certificate\ValidityChecker;
use App\Certificate\ValidityStatus;
use App\Exception\HttpException;
use App\Exception\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Log\Logger;
use App\Persistence\CertificateAuthorityRepository;
use App\Persistence\CertificateCheckRepository;
use App\Persistence\CertificateRepository;
use App\Persistence\Database;
use App\Persistence\KeyUsageRepository;
use App\Persistence\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The pages for owned data. They read through the same repositories as the API, so
 * the ownership rule of ADR-0006 holds here without being restated.
 *
 * Forms are protected against cross-site submission by the SameSite=Lax cookie: a
 * POST from another origin does not carry it, so it arrives unauthenticated and is
 * sent to the sign-in page (ADR-0004).
 */
final class CertificateController
{
    private const PER_PAGE = 100;

    public function __construct(
        private readonly Authentication $authentication,
        private readonly View $view,
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly CertificateRepository $certificates,
        private readonly CertificateAuthorityRepository $authorities,
        private readonly KeyUsageRepository $keyUsages,
        private readonly CertificateCheckRepository $checks,
        private readonly CertificateParser $parser,
        private readonly ValidityChecker $validity,
        private readonly Logger $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        $status = ValidityStatus::tryFrom($request->query['status'] ?? '');
        $now = $this->now();

        $rows = $this->certificates->findAllForOwner($userId, $status, 1, self::PER_PAGE, $now);

        foreach ($rows as $index => $row) {
            $rows[$index]['status'] = $this->statusOf($row, $now);
        }

        return $this->html($userId, 'certificates', 'Certificates', [
            'certificates' => $rows,
            'status' => $status?->value,
            'total' => $this->certificates->countForOwner($userId, $status, $now),
        ]);
    }

    public function form(Request $request): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        return $this->html($userId, 'certificate-new', 'Add certificate', ['error' => null]);
    }

    public function create(Request $request): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        try {
            $id = $this->store($userId, $request->form['pem'] ?? '');
        } catch (HttpException $exception) {
            // A rejected paste belongs back on the form with an explanation, not on an
            // error page. The message never contains the input (CertificateParser).
            return $this->html($userId, 'certificate-new', 'Add certificate', [
                'error' => $exception->detail() ?? 'The certificate could not be added.',
            ]);
        }

        // Redirect after a successful post, so a refresh does not resubmit it.
        return Response::redirect('/certificates/' . $id);
    }

    public function show(Request $request, array $parameters): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        $id = $this->identifier($parameters);
        $row = $this->owned($id, $userId);
        $row['authority_subject_dn'] = (string) $row['authority_subject_dn'];

        return $this->html($userId, 'certificate', $row['common_name'], [
            'certificate' => $row,
            'keyUsages' => $this->keyUsages->findAllForOwnedCertificate($id, $userId),
            'checks' => $this->checks->findAllForOwnedCertificate($id, $userId),
            'status' => $this->statusOf($row, $this->now()),
        ]);
    }

    public function check(Request $request, array $parameters): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        $id = $this->identifier($parameters);
        $row = $this->owned($id, $userId);
        $now = $this->now();

        [$result, $detail] = $this->validity->check(
            new DateTimeImmutable((string) $row['valid_from'], new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row['valid_to'], new DateTimeZone('UTC')),
            $now,
        );

        $this->checks->insertForOwnedCertificate($id, $userId, $result, $detail, $now);

        return Response::redirect('/certificates/' . $id);
    }

    private function store(int $userId, string $pem): int
    {
        $certificate = $this->parser->parse($pem);
        $now = $this->now();

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

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function owned(int $id, int $userId): array
    {
        $row = $this->certificates->findForOwner($id, $userId);

        if ($row === null) {
            throw new NotFoundException(
                'No such certificate.',
                sprintf('user %d asked for certificate %d and the query returned nothing', $userId, $id),
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

        if (preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            throw new NotFoundException('No such certificate.', sprintf('"%s" is not a certificate identifier', $raw));
        }

        return (int) $raw;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function statusOf(array $row, DateTimeImmutable $now): string
    {
        return $this->validity->check(
            new DateTimeImmutable((string) $row['valid_from'], new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row['valid_to'], new DateTimeZone('UTC')),
            $now,
        )[0]->value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function html(int $userId, string $template, string $title, array $data): Response
    {
        $user = $this->users->findByTokenSubject($userId);

        return Response::html($this->view->page($template, $title, $user['email'] ?? null, $data));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
