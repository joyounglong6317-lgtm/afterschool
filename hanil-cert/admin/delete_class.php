<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$grade = (int)($_GET['grade'] ?? 0);
$ban = (int)($_GET['ban'] ?? 0);
$db = get_db();
$msg = '';
$confirmed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $typed = trim($_POST['confirm_text'] ?? '');
    $label = "{$grade}학년 {$ban}반";
    if ($typed === $label) {
        $ids = $db->prepare("SELECT id FROM users WHERE role='student' AND grade=? AND class_no=?");
        $ids->execute([$grade, $ban]);
        $studentIds = array_column($ids->fetchAll(), 'id');
        if ($studentIds) {
            $in = implode(',', array_fill(0, count($studentIds), '?'));
            $db->prepare("DELETE FROM submissions WHERE student_id IN ($in)")->execute($studentIds);
            $db->prepare("DELETE FROM users WHERE id IN ($in)")->execute($studentIds);
        }
        $confirmed = true;
        $msg = "{$label} 학생 " . count($studentIds) . "명의 데이터를 삭제했습니다.";
    } else {
        $msg = '입력한 내용이 일치하지 않아 삭제를 취소했습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><title>반 삭제 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<main style="max-width:480px;">
  <div class="panel"><div class="panel-body">
    <h2 style="color:var(--red);">⚠ <?= h($grade) ?>학년 <?= h($ban) ?>반 전체 데이터 삭제</h2>
    <?php if ($msg): ?><div class="alert <?= $confirmed?'alert-ok':'alert-error' ?>"><?= h($msg) ?></div><?php endif; ?>
    <?php if (!$confirmed): ?>
      <p>이 반 학생들의 계정과 제출 데이터가 모두 삭제됩니다. 되돌릴 수 없습니다.</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>확인을 위해 "<?= h($grade) ?>학년 <?= h($ban) ?>반"을 정확히 입력하세요</label>
          <input type="text" name="confirm_text" required></div>
        <button type="submit" class="btn-red">영구 삭제</button>
        <button type="button" class="btn-ghost" onclick="location.href='dashboard.php'">취소</button>
      </form>
    <?php else: ?>
      <a href="dashboard.php" class="btn-gold">대시보드로 돌아가기</a>
    <?php endif; ?>
  </div></div>
</main>
</body></html>
