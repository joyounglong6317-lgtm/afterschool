<?php
require_once __DIR__ . '/includes/functions.php';
secure_session_start();
enforce_ip_restriction();

if ($user = current_user()) { header('Location: ' . $user['role'] . '/dashboard.php'); exit; }

$tab = $_GET['tab'] ?? 'student';
if (!in_array($tab, ['student','teacher','admin'], true)) $tab = 'student';
$mode = $_GET['mode'] ?? 'login'; // student: login|signup

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$banCounts = get_ban_counts();

function do_login(string $loginId, string $password, string $expectRole): array {
    $stmt = get_db()->prepare("SELECT * FROM users WHERE login_id = ? AND role = ? LIMIT 1");
    $stmt->execute([$loginId, $expectRole]);
    $row = $stmt->fetch();
    if (!$row) { record_login_attempt(null, $loginId, false, '존재하지 않음'); return [false, '아이디 또는 비밀번호가 올바르지 않습니다.']; }
    if ($row['status'] !== 'active') { record_login_attempt($row['id'], $loginId, false, '비활성'); return [false, '비활성화된 계정입니다.']; }
    if (is_account_locked($row)) { record_login_attempt($row['id'], $loginId, false, '잠금'); return [false, '로그인 실패 횟수 초과로 계정이 잠겼습니다. ' . LOGIN_LOCK_MINUTES . '분 후 다시 시도하세요.']; }
    if (!password_verify($password, $row['password_hash'])) {
        record_login_attempt($row['id'], $loginId, false, '비번불일치'); handle_failed_login($row);
        return [false, '아이디 또는 비밀번호가 올바르지 않습니다.'];
    }
    session_regenerate_id(true);
    reset_login_fail_count($row['id']);
    record_login_attempt($row['id'], $loginId, true);
    $_SESSION['user'] = [
        'id'=>$row['id'], 'role'=>$row['role'], 'name'=>$row['name'], 'login_id'=>$row['login_id'],
        'grade'=>$row['grade'], 'class_no'=>$row['class_no'], 'student_no'=>$row['student_no'],
    ];
    return [true, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'student_login') {
        $grade = (int)$_POST['grade']; $ban = (int)$_POST['ban']; $no = trim($_POST['no']);
        $pw = $_POST['password'] ?? '';
        $loginId = "s{$grade}{$ban}" . str_pad($no, 2, '0', STR_PAD_LEFT);
        [$ok, $msg] = do_login($loginId, $pw, 'student');
        if ($ok) { header('Location: student/dashboard.php'); exit; }
        $error = $msg; $tab = 'student'; $mode = 'login';

    } elseif ($action === 'student_signup') {
        $grade = (int)$_POST['grade']; $ban = (int)$_POST['ban']; $no = trim($_POST['no']);
        $name = trim($_POST['name']); $pw = $_POST['password']; $pw2 = $_POST['password2'];
        $tab = 'student'; $mode = 'signup';
        if ($no === '' || $name === '') { $error = '번호와 이름을 입력해주세요.'; }
        elseif (strlen($pw) < 6) { $error = '비밀번호는 6자리 이상이어야 합니다.'; }
        elseif ($pw !== $pw2) { $error = '비밀번호가 일치하지 않습니다.'; }
        else {
            // 명단 대조 (업로드되어 있다면)
            $r = get_db()->prepare("SELECT name FROM roster WHERE grade=? AND class_no=? AND student_no=?");
            $r->execute([$grade, $ban, (int)$no]);
            $rosterRow = $r->fetch();
            if ($rosterRow && str_replace(' ', '', $rosterRow['name']) !== str_replace(' ', '', $name)) {
                $error = "명단상 {$grade}학년 {$ban}반 {$no}번은 \"{$rosterRow['name']}\"입니다. 이름을 다시 확인해주세요.";
            } else {
                $loginId = "s{$grade}{$ban}" . str_pad($no, 2, '0', STR_PAD_LEFT);
                $chk = get_db()->prepare("SELECT id FROM users WHERE login_id=?");
                $chk->execute([$loginId]);
                if ($chk->fetch()) {
                    $error = '이미 가입된 학번입니다. 로그인해주세요.'; $mode = 'login';
                } else {
                    $ins = get_db()->prepare(
                        "INSERT INTO users (role, login_id, password_hash, name, grade, class_no, student_no) VALUES ('student', ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->execute([$loginId, password_hash($pw, PASSWORD_DEFAULT), $name, $grade, $ban, (int)$no]);
                    [$ok, $msg] = do_login($loginId, $pw, 'student');
                    if ($ok) { header('Location: student/dashboard.php'); exit; }
                    $error = $msg;
                }
            }
        }

    } elseif ($action === 'staff_login') {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'teacher';
        $loginId = trim($_POST['login_id']); $pw = $_POST['password'] ?? '';
        [$ok, $msg] = do_login($loginId, $pw, $role);
        if ($ok) { header('Location: ' . $role . '/dashboard.php'); exit; }
        $error = $msg; $tab = $role;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>로그인 - <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="emblem login-emblem"><span style="font-size:11px;">HANIL</span></div>
  <h2>기능장 자격증 취합·판별 시스템</h2>
  <p class="desc">한일여고 · 자격증 취득 현황 취합 및 기능장(<?= MIN_SCORE ?>점 이상) 대상자 자동 판별</p>

  <div class="role-tabs">
    <a href="?tab=student" class="<?= $tab==='student'?'active':'' ?>">학생</a>
    <a href="?tab=teacher" class="<?= $tab==='teacher'?'active':'' ?>">담임교사</a>
    <a href="?tab=admin" class="<?= $tab==='admin'?'active':'' ?>">관리자</a>
  </div>

  <?php if ($error): ?><div class="alert alert-error" style="margin-top:0;"><?= h($error) ?></div><?php endif; ?>

  <?php if ($tab === 'student' && $mode === 'login'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="student_login">
      <div class="field-row">
        <div class="field"><label>학년</label>
          <select name="grade" id="loginGrade" onchange="fillBan('loginGrade','loginBan')">
            <option value="1">1학년</option><option value="2">2학년</option><option value="3">3학년</option>
          </select>
        </div>
        <div class="field"><label>반</label><select name="ban" id="loginBan"></select></div>
        <div class="field"><label>번호</label><input type="text" name="no" placeholder="예: 23" inputmode="numeric" required></div>
      </div>
      <div class="field"><label>비밀번호</label><input type="password" name="password" required></div>
      <button class="btn-gold submit" type="submit">로그인</button>
    </form>
    <div class="switch-link"><a href="?tab=student&mode=signup">처음이신가요? 회원가입</a></div>
    <div class="login-hint" style="text-align:center;">학년/반/번호가 곧 아이디입니다. 비밀번호를 잊었다면 담임 선생님께 문의하세요.</div>

  <?php elseif ($tab === 'student' && $mode === 'signup'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="student_signup">
      <div class="field-row">
        <div class="field"><label>학년</label>
          <select name="grade" id="signGrade" onchange="fillBan('signGrade','signBan')">
            <option value="1">1학년</option><option value="2">2학년</option><option value="3">3학년</option>
          </select>
        </div>
        <div class="field"><label>반</label><select name="ban" id="signBan"></select></div>
        <div class="field"><label>번호</label><input type="text" name="no" placeholder="예: 23" inputmode="numeric" required></div>
      </div>
      <div class="field"><label>이름</label><input type="text" name="name" required></div>
      <div class="field"><label>비밀번호 (6자리 이상)</label><input type="password" name="password" required></div>
      <div class="field"><label>비밀번호 확인</label><input type="password" name="password2" required></div>
      <button class="btn-gold submit" type="submit">회원가입 후 시작하기</button>
    </form>
    <div class="switch-link"><a href="?tab=student&mode=login">이미 계정이 있어요, 로그인하기</a></div>

  <?php elseif ($tab === 'teacher'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="staff_login">
      <input type="hidden" name="role" value="teacher">
      <div class="field"><label>교사 아이디</label><input type="text" name="login_id" required autofocus></div>
      <div class="field"><label>비밀번호</label><input type="password" name="password" required></div>
      <button class="btn-blue submit" type="submit">우리 반 확인하기</button>
    </form>
    <div class="login-hint" style="text-align:center;">계정은 관리자가 발급합니다. 담당 학급도 관리자가 배정합니다.</div>

  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="staff_login">
      <input type="hidden" name="role" value="admin">
      <div class="field"><label>관리자 아이디</label><input type="text" name="login_id" required autofocus></div>
      <div class="field"><label>비밀번호</label><input type="password" name="password" required></div>
      <button class="btn-blue submit" type="submit">전교 대시보드 열기</button>
    </form>
  <?php endif; ?>
</div>

<script>
const BAN_COUNTS = <?= json_encode($banCounts) ?>;
function fillBan(gradeId, banId){
  const grade = document.getElementById(gradeId).value;
  const sel = document.getElementById(banId);
  const count = BAN_COUNTS[grade] || 10;
  const prev = sel.value;
  sel.innerHTML = '';
  for(let i=1;i<=count;i++){
    const o = document.createElement('option'); o.value=i; o.textContent = i+'반';
    sel.appendChild(o);
  }
  if (prev && parseInt(prev) <= count) sel.value = prev;
}
['loginGrade','signGrade'].forEach(id=>{
  if(document.getElementById(id)) fillBan(id, id.replace('Grade','Ban'));
});
</script>
</body>
</html>
