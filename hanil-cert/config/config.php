<?php
/**
 * config/config.php — 실제 서버 환경에 맞게 값을 수정하세요.
 */
define('DB_HOST', 'db.yannick6317.gabia.io');
define('DB_NAME', 'dbyannick6317');
define('DB_USER', 'yannick6317');
define('DB_PASS', '8tkfckdl@@');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', '한일여자고등학교 기능장 자격증 취합·판별 시스템');
define('BASE_URL', 'https://commerce.knowee.co.kr/hanil-cert'); // 실제 배포 경로로 수정

define('SESSION_IDLE_TIMEOUT', 30 * 60);
define('MAX_LOGIN_FAIL', 5);
define('LOGIN_LOCK_MINUTES', 15);

// ===== 학교 IP 접속 제한 =====
define('ENABLE_IP_RESTRICTION', true);
define('SCHOOL_FIXED_IPS', [
    '112.158.55.211',
]);
define('ADMIN_BYPASS_IP_RESTRICTION', false);

// ===== 배점 규정 (원본 시스템과 동일) =====
define('MIN_TYPES', 6);
define('MIN_SCORE', 35);

date_default_timezone_set('Asia/Seoul');

define('DEBUG_MODE', false);
if (DEBUG_MODE) { error_reporting(E_ALL); ini_set('display_errors', 1); }
else { error_reporting(0); ini_set('display_errors', 0); }
