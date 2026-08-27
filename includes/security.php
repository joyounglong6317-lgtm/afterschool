<?php
/**
 * includes/security.php — 모든 페이지 최상단에서 require_once
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_name('HANILCERT_SID');
    session_start();
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT)) {
        session_unset(); session_destroy(); secure_session_start();
        $_SESSION['login_error'] = '장시간 미사용으로 자동 로그아웃 되었습니다. 다시 로그인해주세요.';
    }
    $_SESSION['last_activity'] = time();
}

function get_client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function ip_matches(string $clientIp, string $rule): bool {
    $rule = trim($rule);
    if ($rule === '') return false;
    if (strpos($rule, '/') !== false) {
        [$subnet, $bits] = explode('/', $rule);
        $ipLong = ip2long($clientIp); $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;
        $mask = -1 << (32 - (int)$bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
    if (substr($rule, -1) === '.') return strpos($clientIp, $rule) === 0;
    return $clientIp === $rule;
}

function is_ip_allowed(string $clientIp): bool {
    foreach (SCHOOL_FIXED_IPS as $rule) { if (ip_matches($clientIp, $rule)) return true; }
    try {
        $stmt = get_db()->query("SELECT ip_address FROM allowed_ips WHERE is_active = 1");
        foreach ($stmt->fetchAll() as $row) { if (ip_matches($clientIp, $row['ip_address'])) return true; }
    } catch (Throwable $e) { error_log('allowed_ips 조회 실패: ' . $e->getMessage()); }
    return false;
}

function enforce_ip_restriction(): void {
    if (!ENABLE_IP_RESTRICTION) return;
    $clientIp = get_client_ip();
    if (ADMIN_BYPASS_IP_RESTRICTION && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') return;
    if (!is_ip_allowed($clientIp)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ko"><head><meta charset="utf-8"><title>접속 제한</title></head>
              <body style="font-family:sans-serif;text-align:center;padding-top:80px;">
              <h2>🚫 접속이 제한되었습니다</h2><p>본 시스템은 학교 지정 네트워크에서만 접속 가능합니다.</p>
              <p style="color:#888;font-size:13px;">감지된 접속 IP: ' . htmlspecialchars($clientIp) . '</p></body></html>';
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400); die('잘못된 요청입니다 (CSRF 토큰 불일치). 새로고침 후 다시 시도하세요.');
    }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array {
    $user = current_user();
    if (!$user) { header('Location: ' . BASE_URL . '/login.php'); exit; }
    return $user;
}
function require_role($roles): array {
    $user = require_login();
    $roles = (array)$roles;
    if (!in_array($user['role'], $roles, true)) { http_response_code(403); die('접근 권한이 없습니다.'); }
    return $user;
}

function is_account_locked(array $userRow): bool {
    return !empty($userRow['locked_until']) && strtotime($userRow['locked_until']) > time();
}
function record_login_attempt(?int $userId, string $loginIdAttempt, bool $success, string $reason = ''): void {
    $stmt = get_db()->prepare("INSERT INTO login_logs (login_id_attempt, user_id, ip_address, success, reason) VALUES (?,?,?,?,?)");
    $stmt->execute([$loginIdAttempt, $userId, get_client_ip(), $success ? 1 : 0, $reason]);
}
function handle_failed_login(array $userRow): void {
    $db = get_db();
    $newCount = (int)$userRow['failed_login_count'] + 1;
    if ($newCount >= MAX_LOGIN_FAIL) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60);
        $stmt = $db->prepare("UPDATE users SET failed_login_count=?, locked_until=? WHERE id=?");
        $stmt->execute([$newCount, $lockUntil, $userRow['id']]);
    } else {
        $stmt = $db->prepare("UPDATE users SET failed_login_count=? WHERE id=?");
        $stmt->execute([$newCount, $userRow['id']]);
    }
}
function reset_login_fail_count(int $userId): void {
    $stmt = get_db()->prepare("UPDATE users SET failed_login_count=0, locked_until=NULL, last_login_at=NOW() WHERE id=?");
    $stmt->execute([$userId]);
}

function bootstrap_page($requiredRoles = null): ?array {
    secure_session_start();
    enforce_ip_restriction();
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ($requiredRoles !== null) return require_role($requiredRoles);
    return current_user();
}
