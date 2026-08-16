<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Session cookie hardening and access to the session.
 *
 * The hosting default leaves the session cookie without Secure, without SameSite
 * and without strict session id mode (OMZ-05). The login flow keeps state, nonce
 * and code_verifier here, so those defaults would weaken the protection the design
 * is built on (ADR-0002).
 *
 * The same values are also set in public/.user.ini. The duplication is deliberate:
 * .user.ini is not read under mod_php, which development uses (OMZ-08). ADR-0004
 * requires the two to be identical and treats any difference as a defect, which is
 * why the values live here, in one named place.
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

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set(string $key, string $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Reads a value and removes it in one step. The login flow uses every value it
     * stores exactly once; leaving it behind would allow a second callback to reuse it.
     */
    public static function take(string $key): ?string
    {
        $value = self::get($key);
        unset($_SESSION[$key]);

        return $value;
    }

    /**
     * Called after a successful login so that a session identifier observed before
     * authentication cannot be used afterwards.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
