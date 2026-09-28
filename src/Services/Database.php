<?php
/**
 * src/Services/Database.php
 * Centralized PDO Database Service & Transaction Manager.
 */

declare(strict_types=1);

namespace Vault\Services;

use PDO;
use PDOException;
use RuntimeException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Returns a shared PDO instance using configuration values.
     *
     * @param array<string, mixed>|null $config Optional DB configuration override
     * @return PDO
     */
    public static function getConnection(?array $config = null): PDO
    {
        if (self::$instance === null) {
            if ($config === null) {
                // Fetch loaded global config from index.php scope
                global $config;
                $config = $config['database'] ?? [];
            }

            $host    = (string)($config['host'] ?? 'localhost');
            $port    = (int)($config['port'] ?? 3306);
            $dbName  = (string)($config['name'] ?? '');
            $user    = (string)($config['user'] ?? '');
            $pass    = (string)($config['pass'] ?? '');
            $charset = (string)($config['charset'] ?? 'utf8mb4');

            if ($dbName === '' || $user === '') {
                throw new RuntimeException('Database configuration is incomplete or missing.');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE 'utf8mb4_unicode_ci'",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Mask sensitive credentials from backtraces
                error_log('[Database Error] ' . $e->getMessage());
                throw new RuntimeException('Unable to establish a secure database connection.');
            }
        }

        return self::$instance;
    }

    /**
     * Shorthand helper to execute a prepared statement and return all rows.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Shorthand helper to execute a prepared statement and return a single row.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Shorthand helper to execute a prepared query and return a single scalar value.
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return mixed
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Shorthand helper to execute write statements (INSERT, UPDATE, DELETE).
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return int Number of affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Returns the last auto-incremented ID.
     */
    public static function lastInsertId(): int
    {
        return (int)self::getConnection()->lastInsertId();
    }

    /**
     * Executes a callback within a managed database transaction.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}