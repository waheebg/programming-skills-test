<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Prevent direct instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing.
     */
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }

    /**
     * Get the centralized PDO database instance.
     *
     * @param array|null $config Optional configuration override.
     * @return PDO
     * @throws RuntimeException When database connection fails (without leaking raw credentials/traces).
     */
    public static function getConnection(?array $config = null): PDO
    {
        if (self::$instance === null) {
            if ($config === null) {
                $configPath = dirname(__DIR__, 2) . '/config/database.php';
                if (!file_exists($configPath)) {
                    throw new RuntimeException('Database configuration file not found.');
                }
                $config = require $configPath;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';
            $options  = $config['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // If default fallback password failed and no explicit DB_PASSWORD was configured,
                // try empty password (standard XAMPP default)
                if (getenv('DB_PASSWORD') === false && $password !== '') {
                    try {
                        self::$instance = new PDO($dsn, $username, '', $options);
                        return self::$instance;
                    } catch (PDOException $e2) {
                        // Fall through to standard error handling
                    }
                }
                // Log the real error internally if needed, but do not expose raw errors, credentials or traces to users
                error_log('Database connection error: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed. Please check server logs.');
            }
        }

        return self::$instance;
    }
}
