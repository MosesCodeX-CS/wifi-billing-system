<?php
// core/db.php - PDO connection helper
require_once __DIR__ . '/config.php';

function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        error_log('DB Connect error: ' . $e->getMessage());
        throw $e;
    }
    return $pdo;
}
