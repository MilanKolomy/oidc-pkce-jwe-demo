<?php

declare(strict_types=1);

namespace App\Api;

use App\Exception\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Persistence\UserRepository;

final class UserController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly UserRepository $users,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->authentication->userId($request);
        $user = $this->users->findByTokenSubject($userId);

        if ($user === null) {
            // The token decrypted and is inside its lifetime, but the user it names is
            // gone — deleted while the token was still valid. Not a 404: nothing was
            // asked for by identifier, and the credential is what stopped being usable.
            throw new UnauthorizedException(logReason: 'the token names a user that no longer exists');
        }

        return Response::json([
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'displayName' => $user['display_name'] !== null ? (string) $user['display_name'] : null,
            'createdAt' => Timestamp::toIso8601($user['created_at']),
            'lastLoginAt' => Timestamp::toIso8601($user['last_login_at']),
        ]);
    }
}
