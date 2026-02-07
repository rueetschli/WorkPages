<?php
/**
 * Database service - PDO singleton with prepared-statement helpers.
 *
 * Supports lazy connection: call setConfig() early, and the actual PDO
 * connection is created on first use (pdo() / query() / fetch() etc.).
 */
class DB
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    /**
     * Store config for lazy connection (called from front controller).
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get (or create) the shared PDO connection.
     */
    public static function connect(array $config): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_NAME'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );

            self::$instance = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    /**
     * Return the PDO connection, creating it lazily if needed.
     */
    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            if (self::$config === null) {
                throw new RuntimeException('DB: neither connect() nor setConfig() has been called.');
            }
            self::connect(self::$config);
        }
        return self::$instance;
    }

    /**
     * Execute a statement with bound parameters.
     * Returns the PDOStatement (useful for INSERT/UPDATE/DELETE).
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch all matching rows.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Return the last inserted auto-increment ID.
     */
    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }
}
