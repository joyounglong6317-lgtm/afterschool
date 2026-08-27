<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    if ($action === 'assign') {
        $grade = (int)$_POST['grade']; $ban = (int)$_POST['ban']; $teacherId = (int)$_POST['teacher_id'];
        $stmt = $db->prepare("INSERT INTO teacher_assignments (grade, class_no, teacher_id) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)");
        $stmt->execute([$grade, $ban, $teacherId]);
        $msg = "{$grade}학년 {$ban}반 담임이 배정되었습니다.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM teacher_assignments WHERE id=?")->execute([$id]);
        $msg = '배정이 삭제되었습니다.';
    }
}

$teachers = $db->query("SELECT id, name, login_id FROM users WHERE role='teacher' AND status='active' ORDER BY name")->fetchAll();
$assignments = $db->query(
    "SELECT ta.id, ta.grade, ta.class_no, u.name AS teacher_name FROM teacher_assignments ta
     JOIN users u ON u.id=ta.teacher_id ORDER BY ta.grade, ta.class_no"
)->fetchAll();
$banCounts = get_ban_counts();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>담임 배정 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <div class="panel">
    <div class="panel-head"><h2>담임교사 배정 관리</h2></div>
    <div class="panel-body">
      <div class="hint">배정된 반의 학생 제출 데이터를 해당 담임 계정에서만 열람·승인할 수 있습니다.</div>
      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <form method="post" class="field-row" style="align-items:flex-end; margin-top:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="assign">
        <div class="field" style="max-width:110px;"><label>학년</label>
          <select name="grade" id="taGrade" onchange="fillBan()"><option value="1">1학년</option><option value="2">2학년</option><option value="3">3학년</option></select>
        </div>
        <div class="field" style="max-width:110px;"><label>반</label><select name="ban" id="taBan"></select></div>
        <div class="field" style="max-width:220px;"><label>담임 계정</label>
          <select name="teacher_id" required>
            <option value="">선택</option>
            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= h($t['login_id']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><button type="submit" class="btn-blue">배정 저장</button></div>
      </form>

      <div class="scroll-x" style="max-height:280px; margin-top:14px;">
        <table class="grid">
          <thead><tr><th>학년</th><th>반</th><th>담임</th><th>관리</th></tr></thead>
          <tbody>
          <?php if (!$assignments): ?><tr><td colspan="4" class="empty-state">배정된 학급이 없습니다.</td></tr><?php endif; ?>
          <?php foreach ($assignments as $a): ?>
            <tr><td><?= h($a['grade']) ?></td><td><?= h($a['class_no']) ?></td><td><?= h($a['teacher_name']) ?></td>
              <td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="chip-btn" onclick="return confirm('배정을 삭제할까요?')">삭제</button></form></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<script>
const BAN_COUNTS = <?= json_encode($banCounts) ?>;
function fillBan(){
  const grade = document.getElementById('taGrade').value;
  const sel = document.getElementById('taBan');
  const count = BAN_COUNTS[grade] || 10;
  sel.innerHTML = '';
  for(let i=1;i<=count;i++){ const o=document.createElement('option'); o.value=i; o.textContent=i+'반'; sel.appendChild(o); }
}
fillBan();
</script>
</body></html>
