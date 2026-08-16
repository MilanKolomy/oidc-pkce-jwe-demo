<?php

declare(strict_types=1);

namespace App\Api;

use App\Exception\UnauthorizedException;
use App\Http\Request;
use App\Token\TokenVerifier;

/**
 * Turns a request into the identifier of the user making it.
 *
 * Every endpoint starts here, and it is the only way to learn who is calling. A
 * controller cannot accidentally act on an identifier taken from the request itself,
 * because there is nowhere else to get one from.
 */
final class Authentication
{
    public const COOKIE_NAME = 'app_token';

    public function __construct(private readonly TokenVerifier $verifier)
    {
    }

    public function userId(Request $request): int
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if ($token === null || $token === '') {
            throw new UnauthorizedException(logReason: 'no ' . self::COOKIE_NAME . ' cookie was sent');
        }

        return $this->verifier->verify($token);
    }

    /**
     * The same check, for the pages. A browser that is not signed in should be sent
     * to the sign-in page rather than shown a 401 document, so the absence of a
     * usable token is an answer here rather than a refusal.
     */
    public function optionalUserId(Request $request): ?int
    {
        try {
            return $this->userId($request);
        } catch (UnauthorizedException) {
            return null;
        }
    }
}
