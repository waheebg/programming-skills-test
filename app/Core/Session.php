<?php

namespace App\Core;

class Session
{
    /**
     * Start secure session if not already started.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                // Check if HTTPS is active
                $isSecure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

                // Configure security parameters for session cookie
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                // Enforce strict session handling and cookie-only storage
                ini_set('session.use_strict_mode', '1');
                ini_set('session.use_only_cookies', '1');
            }

            @session_start();
        }
    }

    /**
     * Regenerate session ID to prevent session fixation attacks.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     * @return bool
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        return @session_regenerate_id($deleteOldSession);
    }

    /**
     * Set a session key-value pair.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value by key, or return default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from session.
     *
     * @param string $key
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Set a flash message for the next request.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function setFlash(string $key, $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and clear a flash message.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash(string $key, $default = null)
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Check if a flash message exists.
     *
     * @param string $key
     * @return bool
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Fully destroy the session and clear the session cookie.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::start();
        }

        $_SESSION = [];

        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        @session_destroy();
    }
}
