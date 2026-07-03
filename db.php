<?php
// ============================================================
// backend/config/db.php — PostgreSQL PDO Singleton
// ============================================================
require_once __DIR__ . '/env.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'pgsql';
        $dsn = $driver === 'mysql'
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database unavailable']));
        }
    }
    return $pdo;
}

function db_driver(): string {
    return defined('DB_DRIVER') ? DB_DRIVER : 'pgsql';
}
