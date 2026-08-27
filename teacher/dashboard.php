<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('teacher');
$cycle = get_current_cycle();

$assign = get_db()->prepare("SELECT * FROM teacher_assignments WHERE teacher_id=?");
$assign->execute([$user['id']]);
$myClass = $assign->fetch();
if (!$myClass) { die('아직 담당 학급이 배정되지 않았습니다. 관리자에게 문의하세요.'); }
$grade = (int)$myClass['grade']; $ban = (int)$myClass['class_no'];

if (!$cycle) { die('현재 취합 학기가 설정되지 않았습니다. 관리자에게 문의하세요.'); }

$stmt = get_db()->prepare(
    "SELECT u.id AS student_id, u.name, u.student_no, u.enrollment_date, s.id AS submission_id,
            s.counts_json, s.status, s.past_winner, s.national, s.gyeongnam
     FROM users u
     LEFT JOIN submissions s ON s.student_id = u.id AND s.cycle_id = ?
     WHERE u.role='student' AND u.grade=? AND u.class_no=?
     ORDER BY u.student_no ASC"
);
$stmt->execute([$cycle['id'], $grade, $ban]);
$rows = $stmt->fetchAll();

$students = [];
foreach ($rows as $r) {
    $counts = get_scored_counts($r['counts_json'], $r['enrollment_date']);
    $status = $r['status'] ?? 'draft';
    $sub = ['counts'=>$counts, 'past_winner'=>$r['past_winner'] ?? 0, 'national'=>$r['national'] ?? 0, 'gyeongnam'=>$r['gyeongnam'] ?? null];
    $ev = eval_eligibility($sub);
    $students[] = [
        'id'=>$r['student_id'], 'name'=>$r['name'], 'no'=>$r['student_no'], 'status'=>$status,
        'types'=>count_types($counts), 'score'=>$ev['score'], 'eligible'=>$ev['eligible'], 'past'=>$r['past_winner'] ?? 0,
        'enrollment_date'=>$r['enrollment_date'],
    ];
}

$filter = $_GET['f'] ?? 'all';
$counts = ['all'=>count($students), 'draft'=>0, 'submitted'=>0, 'approved'=>0, 'rejected'=>0];
foreach ($students as $s) { if (isset($counts[$s['status']])) $counts[$s['status']]++; }
$filtered = $filter === 'all' ? $students : array_filter($students, fn($s) => $s['status'] === $filter);
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>담임 대시보드 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<header class="top">
  <div style="display:flex; align-items:center; gap:14px;">
    <div class="emblem header-emblem"><span style="font-size:9px;">HANIL</span></div>
    <div>
      <div style="display:flex;align-items:center;gap:8px;"><span class="badge">기능장 <?= MIN_SCORE ?></span><h1>자격증 취합·판별 시스템</h1></div>
      <div class="sub"><?= h($grade) ?>학년 <?= h($ban) ?>반 담임 <?= h($user['name']) ?> 선생님 · <?= h(cycle_label($cycle)) ?></div>
    </div>
  </div>
  <div class="top-actions">
    <button class="btn-outline" onclick="location.href='export_official.php'">기능장 점수합계표</button>
    <button class="btn-outline" onclick="location.href='export_survey.php'">자격증 취득 현황표</button>
    <button class="btn-outline" onclick="location.href='../logout.php'">로그아웃</button>
  </div>
</header>
<main>
  <div class="stat-cards">
    <a class="stat-card <?= $filter==='all'?'filter-active':'' ?>" href="?f=all"><div class="num"><?= $counts['all'] ?></div><div class="label">전체 등록 학생</div></a>
    <a class="stat-card <?= $filter==='draft'?'filter-active':'' ?>" href="?f=draft"><div class="num"><?= $counts['draft'] ?></div><div class="label">임시저장(미제출)</div></a>
    <a class="stat-card <?= $filter==='submitted'?'filter-active':'' ?>" href="?f=submitted"><div class="num"><?= $counts['submitted'] ?></div><div class="label">확인 대기(제출됨)</div></a>
    <a class="stat-card gold <?= $filter==='approved'?'filter-active':'' ?>" href="?f=approved"><div class="num"><?= $counts['approved'] ?></div><div class="label">기능장 대상(승인)</div></a>
    <a class="stat-card <?= $filter==='rejected'?'filter-active':'' ?>" href="?f=rejected"><div class="num"><?= $counts['rejected'] ?></div><div class="label">반려</div></a>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2><?= h($grade) ?>학년 <?= h($ban) ?>반 학생 현황</h2>
      <form method="get" action="print.php" target="_blank">
        <button type="submit" class="btn-gold" formtarget="_blank" onclick="return collectChecked(event)">선택 학생 자격증·점수표 인쇄</button>
        <input type="hidden" name="ids" id="printIds">
      </form>
    </div>
    <div class="panel-body">
      <div class="sm" style="margin-bottom:8px; color:#767268;">
        <?= $filter==='all' ? "전체 {$counts['all']}명 표시 중" : "필터링 — " . count($filtered) . "명 표시 중 (전체 {$counts['all']}명)" ?>
      </div>
      <div class="scroll-x" style="max-height:65vh;">
        <table class="grid">
          <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.chk').forEach(c=>c.checked=this.checked)"></th><th>번호</th><th>이름</th><th>보유종수</th><th>환산점수</th><th>기준</th><th>상태</th></tr></thead>
          <tbody>
            <?php if (!$filtered): ?>
              <tr><td colspan="7" class="empty-state">해당 조건의 학생이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($filtered as $s): ?>
              <tr style="cursor:pointer;" onclick="if(event.target.type!=='checkbox') location.href='review.php?student_id=<?= $s['id'] ?>'">
                <td onclick="event.stopPropagation()"><input type="checkbox" class="chk" value="<?= $s['id'] ?>"></td>
                <td><?= h($s['no']) ?></td>
                <td><?= h($s['name']) ?><?= $s['past'] ? ' <span class="status-badge draft">이전수상</span>' : '' ?></td>
                <td><?= $s['types'] ?></td>
                <td><?= $s['score'] ?></td>
                <td><span class="status-badge <?= $s['eligible']?'approved':'draft' ?>"><?= $s['eligible']?'충족':'미충족' ?></span></td>
                <td><?= status_badge($s['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<footer class="foot">한일여자고등학교 · 기능장 자격증 취합 시스템 · <?= h(cycle_label($cycle)) ?> 기준</footer>
<script>
function collectChecked(e){
  const ids = Array.from(document.querySelectorAll('.chk:checked')).map(c=>c.value);
  if(ids.length===0){ alert('인쇄할 학생을 한 명 이상 선택해주세요.'); e.preventDefault(); return false; }
  document.getElementById('printIds').value = ids.join(',');
  return true;
}
</script>
</body></html>
