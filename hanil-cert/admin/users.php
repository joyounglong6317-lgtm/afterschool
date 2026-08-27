<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    try {
        if ($action === 'create_staff') {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'teacher';
            $loginId = trim($_POST['login_id']); $name = trim($_POST['name']);
            $pw = $_POST['temp_password'] ?: bin2hex(random_bytes(4));
            if ($loginId === '' || $name === '') throw new RuntimeException('아이디와 이름을 입력해주세요.');
            $stmt = $db->prepare("INSERT INTO users (role, login_id, password_hash, name) VALUES (?,?,?,?)");
            $stmt->execute([$role, $loginId, password_hash($pw, PASSWORD_DEFAULT), $name]);
            $msg = "계정이 생성되었습니다. (아이디: {$loginId} / 임시비밀번호: {$pw})";

        } elseif ($action === 'reset_password') {
            $uid = (int)$_POST['user_id'];
            $newPw = bin2hex(random_bytes(4));
            $stmt = $db->prepare("UPDATE users SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?");
            $stmt->execute([password_hash($newPw, PASSWORD_DEFAULT), $uid]);
            $msg = "비밀번호가 초기화되었습니다. 새 임시비밀번호: {$newPw}";

        } elseif ($action === 'toggle_status') {
            $uid = (int)$_POST['user_id'];
            $stmt = $db->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=?");
            $stmt->execute([$uid]);
            $msg = '계정 상태가 변경되었습니다.';

        } elseif ($action === 'set_enrollment') {
            $uid = (int)$_POST['user_id'];
            $date = trim($_POST['enrollment_date'] ?? '');
            $val = $date !== '' ? $date : null;
            $stmt = $db->prepare("UPDATE users SET enrollment_date=? WHERE id=? AND role='student'");
            $stmt->execute([$val, $uid]);
            $msg = '입학일이 저장되었습니다.';

        } elseif ($action === 'set_enrollment_bulk') {
            $grade = (int)$_POST['grade']; $ban = (int)$_POST['ban'];
            $date = trim($_POST['enrollment_date'] ?? '');
            $val = $date !== '' ? $date : null;
            $stmt = $db->prepare("UPDATE users SET enrollment_date=? WHERE role='student' AND grade=? AND class_no=?");
            $stmt->execute([$val, $grade, $ban]);
            $msg = "{$grade}학년 {$ban}반 학생 전원의 입학일이 일괄 저장되었습니다.";
        }
    } catch (Throwable $e) { $msg = '오류: ' . $e->getMessage(); }
}

$staff = $db->query("SELECT * FROM users WHERE role IN ('teacher','admin') ORDER BY role, name")->fetchAll();
$students = $db->query("SELECT * FROM users WHERE role='student' ORDER BY grade, class_no, student_no")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>계정 관리 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <h2 style="color:var(--navy);">계정 관리</h2>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

  <details class="form-box">
    <summary style="cursor:pointer;font-weight:bold;">+ 담임/관리자 계정 발급</summary>
    <form method="post" style="margin-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="create_staff">
      <div class="field-row">
        <div class="field"><label>역할</label><select name="role" required><option value="teacher">담임교사</option><option value="admin">관리자</option></select></div>
        <div class="field"><label>로그인 아이디</label><input type="text" name="login_id" required></div>
        <div class="field"><label>이름</label><input type="text" name="name" required></div>
        <div class="field"><label>임시 비밀번호 (비워두면 자동생성)</label><input type="text" name="temp_password"></div>
      </div>
      <button type="submit" class="btn-primary btn-gold">계정 생성</button>
    </form>
  </details>

  <div class="panel">
    <div class="panel-head"><h2>담임/관리자 계정</h2></div>
    <div class="panel-body">
      <table class="data-table grid">
        <thead><tr><th>역할</th><th>아이디</th><th>이름</th><th>상태</th><th>관리</th></tr></thead>
        <tbody>
        <?php foreach ($staff as $u): ?>
          <tr>
            <td><?= $u['role']==='admin'?'관리자':'담임교사' ?></td><td><?= h($u['login_id']) ?></td><td><?= h($u['name']) ?></td>
            <td><?= $u['status']==='active'?'활성':'비활성' ?></td>
            <td>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="reset_password"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="chip-btn" onclick="return confirm('비밀번호를 초기화할까요?')">PW초기화</button></form>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle_status"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="chip-btn" onclick="return confirm('상태를 변경할까요?')">상태변경</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>입학일 일괄 등록</h2></div>
    <div class="panel-body">
      <div class="hint">필수 2종(컴활/전산회계) 외 자격증은 이 입학일보다 이전 취득분이 자동으로 점수 계산에서 제외됩니다.</div>
      <form method="post" class="field-row" style="align-items:flex-end; margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="set_enrollment_bulk">
        <div class="field" style="max-width:110px;"><label>학년</label>
          <select name="grade" id="bulkGrade" onchange="fillBulkBan()"><option value="1">1학년</option><option value="2">2학년</option><option value="3">3학년</option></select>
        </div>
        <div class="field" style="max-width:110px;"><label>반</label><select name="ban" id="bulkBan"></select></div>
        <div class="field" style="max-width:170px;"><label>입학일</label><input type="date" name="enrollment_date" required></div>
        <div class="field"><button type="submit" class="btn-blue" onclick="return confirm('이 반 학생 전원의 입학일을 일괄 설정할까요? 개별 설정된 값도 덮어씁니다.')">일괄 적용</button></div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>학생 계정 (<?= count($students) ?>명)</h2></div>
    <div class="panel-body">
      <div class="scroll-x" style="max-height:50vh;">
        <table class="grid">
          <thead><tr><th>학년</th><th>반</th><th>번호</th><th>이름</th><th>입학일</th><th>상태</th><th>관리</th></tr></thead>
          <tbody>
          <?php foreach ($students as $u): ?>
            <tr>
              <td><?= h($u['grade']) ?></td><td><?= h($u['class_no']) ?></td><td><?= h($u['student_no']) ?></td><td><?= h($u['name']) ?></td>
              <td>
                <form method="post" style="display:flex; gap:4px; align-items:center;">
                  <?= csrf_field() ?><input type="hidden" name="form_action" value="set_enrollment"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <input type="date" name="enrollment_date" value="<?= h($u['enrollment_date']) ?>" style="width:135px; padding:4px;">
                  <button type="submit" class="chip-btn">저장</button>
                </form>
              </td>
              <td><?= $u['status']==='active'?'활성':'비활성' ?></td>
              <td>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="reset_password"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="chip-btn" onclick="return confirm('비밀번호를 초기화할까요?')">PW초기화</button></form>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle_status"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="chip-btn" onclick="return confirm('상태를 변경할까요?')">상태변경</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<script>
const BAN_COUNTS = <?= json_encode(get_ban_counts()) ?>;
function fillBulkBan(){
  const grade = document.getElementById('bulkGrade').value;
  const sel = document.getElementById('bulkBan');
  const count = BAN_COUNTS[grade] || 10;
  sel.innerHTML = '';
  for(let i=1;i<=count;i++){ const o=document.createElement('option'); o.value=i; o.textContent=i+'반'; sel.appendChild(o); }
}
fillBulkBan();
</script>
</body></html>
