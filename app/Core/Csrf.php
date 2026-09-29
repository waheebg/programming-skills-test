<?php

namespace App\Core;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Get or create a cryptographically secure CSRF token.
     *
     * @return string
     */
    public static function getToken(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (empty($token) || !is_string($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /**
     * Validate an incoming token against the session token.
     *
     * @param string|null $token
     * @return bool
     */
    public static function validate(?string $token): bool
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }

        $sessionToken = Session::get(self::SESSION_KEY);
        if (empty($sessionToken) || !is_string($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate the CSRF token.
     *
     * @return string
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Generate an HTML hidden input field containing the current CSRF token.
     *
     * @return string
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
