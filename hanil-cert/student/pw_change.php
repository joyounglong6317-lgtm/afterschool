<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('student');
$error = ''; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $new2 = $_POST['new2'] ?? '';
    $stmt = get_db()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!password_verify($cur, $row['password_hash'])) $error = '현재 비밀번호가 올바르지 않습니다.';
    elseif (strlen($new) < 6) $error = '새 비밀번호는 6자리 이상이어야 합니다.';
    elseif ($new !== $new2) $error = '새 비밀번호가 일치하지 않습니다.';
    else {
        $upd = get_db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $upd->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>비밀번호 변경 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<div class="login-wrap" style="max-width:380px;">
  <h2>비밀번호 변경</h2>
  <?php if ($success): ?>
    <div class="alert alert-ok">비밀번호가 변경되었습니다. <a href="dashboard.php">돌아가기</a></div>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-error" style="margin-top:0;"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label>현재 비밀번호</label><input type="password" name="current" required></div>
      <div class="field"><label>새 비밀번호 (6자리 이상)</label><input type="password" name="new" required></div>
      <div class="field"><label>새 비밀번호 확인</label><input type="password" name="new2" required></div>
      <button class="btn-gold submit" type="submit">변경하기</button>
    </form>
    <div class="switch-link"><a href="dashboard.php">취소하고 돌아가기</a></div>
  <?php endif; ?>
</div>
</body></html>
