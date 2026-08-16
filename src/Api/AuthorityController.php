<?php

declare(strict_types=1);

namespace App\Api;

use App\Http\Request;
use App\Http\Response;
use App\Persistence\CertificateAuthorityRepository;

/**
 * Shared lookup data: readable by any authenticated user, owned by nobody. The
 * authentication check is still first — the list says which authorities the
 * registry knows, which is not for anonymous callers (FR-11).
 */
final class AuthorityController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly CertificateAuthorityRepository $authorities,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authentication->userId($request);
        $page = Pagination::fromRequest($request);

        $rows = $this->authorities->findAll($page->page, $page->perPage);

        return Response::json([
            'data' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'commonName' => (string) $row['common_name'],
                'subjectDn' => (string) $row['subject_dn'],
            ], $rows),
            'meta' => $page->meta($this->authorities->count()),
        ]);
    }
}
