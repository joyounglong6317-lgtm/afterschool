<?php
/**
 * config/db_connect.php
 * PDO 기반 DB 연결. Prepared Statement 강제 사용으로 SQL Injection 차단.
 */
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB 연결 실패: ' . $e->getMessage());
            http_response_code(500);
            die('데이터베이스 연결에 실패했습니다. 관리자에게 문의하세요.');
        }
    }
    return $pdo;
}
