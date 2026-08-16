<?php

declare(strict_types=1);

namespace App\Web;

use App\Api\Authentication;
use App\Http\Request;
use App\Http\Response;
use App\Persistence\CertificateRepository;
use App\Persistence\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

final class ProfileController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly CertificateRepository $certificates,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->authentication->optionalUserId($request);

        if ($userId === null) {
            return Response::redirect('/');
        }

        $user = $this->users->findByTokenSubject($userId);

        if ($user === null) {
            return Response::redirect('/logout');
        }

        return Response::html($this->view->page('profile', 'Profile', (string) $user['email'], [
            'user' => $user,
            'certificateCount' => $this->certificates->countForOwner(
                $userId,
                null,
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            ),
        ]));
    }
}
