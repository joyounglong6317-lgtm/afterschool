<?php
/**
 * setup.php — 최초 1회만 사용. 관리자 계정 생성 후 이 파일을 서버에서 반드시 삭제하세요!
 */
require_once __DIR__ . '/includes/security.php';
secure_session_start();
enforce_ip_restriction();

$db = get_db();
$adminExists = (int)$db->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'] > 0;
if ($adminExists) { http_response_code(403); die('이미 관리자 계정이 존재합니다. 보안을 위해 setup.php를 서버에서 즉시 삭제해주세요.'); }

$msg = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim($_POST['login_id'] ?? ''); $name = trim($_POST['name'] ?? ''); $password = $_POST['password'] ?? '';
    if ($loginId === '' || $name === '' || strlen($password) < 8) {
        $msg = '아이디, 이름을 입력하고 비밀번호는 8자 이상으로 설정해주세요.';
    } else {
        $stmt = $db->prepare("INSERT INTO users (role, login_id, password_hash, name, status) VALUES ('admin', ?, ?, ?, 'active')");
        $stmt->execute([$loginId, password_hash($password, PASSWORD_DEFAULT), $name]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><title>최초 관리자 설정</title>
<style>body{font-family:sans-serif;max-width:420px;margin:60px auto;padding:0 16px;}
input{width:100%;padding:8px;margin:6px 0 14px;box-sizing:border-box;}
button{padding:10px 16px;background:#1b2a4a;color:#fff;border:none;border-radius:6px;}
.warn{background:#fff3cd;padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;}</style></head>
<body>
<h2>최초 관리자 계정 생성</h2>
<div class="warn">⚠️ 계정 생성 완료 후 이 파일(setup.php)을 서버에서 반드시 삭제하세요.</div>
<?php if ($done): ?>
  <p style="color:green;">✅ 관리자 계정이 생성되었습니다. <a href="login.php">로그인 페이지로 이동</a></p>
  <p><strong>지금 바로 setup.php 파일을 삭제해주세요.</strong></p>
<?php else: ?>
  <?php if ($msg): ?><p style="color:red;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <form method="post">
    <label>관리자 아이디</label><input type="text" name="login_id" required>
    <label>이름</label><input type="text" name="name" required>
    <label>비밀번호 (8자 이상)</label><input type="password" name="password" required minlength="8">
    <button type="submit">관리자 계정 생성</button>
  </form>
<?php endif; ?>
</body></html>
