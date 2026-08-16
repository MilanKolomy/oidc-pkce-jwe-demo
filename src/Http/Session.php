<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Session cookie hardening, applied at startup on every request.
 *
 * The hosting default leaves the session cookie without Secure, without SameSite
 * and without strict session id mode (OMZ-05). The login flow keeps state, nonce
 * and code_verifier in the session, so those defaults would weaken the protection
 * the design is built on.
 *
 * The same values are also set in public/.user.ini. The duplication is deliberate:
 * .user.ini is not read under mod_php, which development uses. ADR-0004 requires
 * the two to be identical and treats any difference as a defect — which is why the
 * values live here, in one named place, rather than inline in the front controller.
 */
final class Session
{
    public static function configure(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'secure' => true,
            // Strict would not send the cookie on the top-level GET redirect back from
            // Google, the state check would fail and login would not work (ADR-0004).
            'samesite' => 'Lax',
        ]);
    }
}
