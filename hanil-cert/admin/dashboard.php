<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
$db = get_db();

if (!$cycle) { die('취합 학기가 설정되지 않았습니다. [학기 설정]에서 생성해주세요.'); }

// 학급별 학생+제출 데이터 조회
$stmt = $db->prepare(
    "SELECT u.id, u.name, u.grade, u.class_no, u.student_no AS no, u.enrollment_date,
            s.counts_json, s.status, s.past_winner, s.national, s.gyeongnam
     FROM users u LEFT JOIN submissions s ON s.student_id=u.id AND s.cycle_id=?
     WHERE u.role='student' ORDER BY u.grade, u.class_no, u.student_no"
);
$stmt->execute([$cycle['id']]);
$all = $stmt->fetchAll();

$byClass = [];
foreach ($all as $r) {
    $key = $r['grade'] . '_' . $r['class_no'];
    $byClass[$key]['grade'] = $r['grade']; $byClass[$key]['ban'] = $r['class_no'];
    $byClass[$key]['students'][] = $r;
}

$teacherRows = $db->query(
    "SELECT ta.grade, ta.class_no, u.name FROM teacher_assignments ta JOIN users u ON u.id=ta.teacher_id"
)->fetchAll();
$teacherMap = [];
foreach ($teacherRows as $t) $teacherMap[$t['grade'].'_'.$t['class_no']] = $t['name'];

$classRows = ''; $eligibleRows = ''; $totalStudents=0; $totalPending=0; $totalEligible=0;
foreach ($byClass as $key => $c) {
    $students = $c['students'];
    $totalStudents += count($students);
    $submitted = count(array_filter($students, fn($s)=>$s['status']==='submitted'));
    $approved = array_filter($students, fn($s)=>$s['status']==='approved');
    $rejected = count(array_filter($students, fn($s)=>$s['status']==='rejected'));
    $totalPending += $submitted; $totalEligible += count($approved);
    $classRows .= '<tr><td>'.h($c['grade']).'</td><td>'.h($c['ban']).'</td><td>'.count($students).'</td><td>'.$submitted.'</td><td>'.count($approved).'</td><td>'.$rejected.'</td>
        <td><a href="delete_class.php?grade='.$c['grade'].'&ban='.$c['ban'].'" class="chip-btn" style="color:var(--red);border-color:var(--red);">삭제</a></td></tr>';
    foreach ($approved as $s) {
        $counts = get_scored_counts($s['counts_json'], $s['enrollment_date']);
        $ev = eval_eligibility(['counts'=>$counts, 'past_winner'=>$s['past_winner'], 'national'=>$s['national'], 'gyeongnam'=>$s['gyeongnam']]);
        $sid = sprintf('%d%02d%02d', $c['grade'], $c['ban'], $s['no']);
        $eligibleRows .= '<tr><td>'.h($c['grade']).'</td><td>'.h($c['ban']).'</td><td>'.h($sid).'</td><td>'.h($s['name']).'</td><td>'.count_types($counts).'</td><td>'.$ev['score'].'</td><td style="font-size:10.5px;">'.h($ev['reason']).'</td></tr>';
    }
}

// 명단 대비 미가입자
$roster = $db->query("SELECT grade, class_no, student_no, name FROM roster ORDER BY grade, class_no, student_no")->fetchAll();
$registered = [];
foreach ($all as $r) $registered[$r['grade'].'_'.$r['class_no'].'_'.$r['no']] = true;
$missing = array_filter($roster, fn($r) => !isset($registered[$r['grade'].'_'.$r['class_no'].'_'.$r['student_no']]));
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>관리자 대시보드 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <div class="stat-cards">
    <div class="stat-card"><div class="num"><?= $totalStudents ?></div><div class="label">전체 등록 학생</div></div>
    <div class="stat-card"><div class="num"><?= $totalPending ?></div><div class="label">담임 확인 대기</div></div>
    <div class="stat-card gold"><div class="num"><?= $totalEligible ?></div><div class="label">전교 기능장(승인)</div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>반별 현황</h2></div>
    <div class="panel-body">
      <div class="scroll-x" style="max-height:280px;">
        <table class="grid"><thead><tr><th>학년</th><th>반</th><th>학생수</th><th>제출됨</th><th>승인(대상)</th><th>반려</th><th>관리</th></tr></thead>
        <tbody><?= $classRows ?: '<tr><td colspan="7" class="empty-state">등록된 학생이 없습니다.</td></tr>' ?></tbody></table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>명단 대비 가입 현황 <span class="sm" style="font-weight:400;color:#999;">(미가입자)</span></h2></div>
    <div class="panel-body">
      <div class="scroll-x" style="max-height:260px;">
        <table class="grid"><thead><tr><th>학년</th><th>반</th><th>번호</th><th>이름</th></tr></thead>
        <tbody>
        <?php if (!$missing): ?><tr><td colspan="4" class="empty-state"><?= $roster ? '전원 가입 완료' : '업로드된 명단이 없습니다. [명단 관리]에서 업로드해주세요.' ?></td></tr><?php endif; ?>
        <?php foreach ($missing as $r): ?><tr><td><?= h($r['grade']) ?></td><td><?= h($r['class_no']) ?></td><td><?= h($r['student_no']) ?></td><td><?= h($r['name']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h2>전교 기능장 대상자 명단 <span class="sm" style="font-weight:400;color:#999;">(담임 승인 완료 건만)</span></h2>
      <div class="toolbar">
        <button class="btn-gold" onclick="location.href='export_eligible.php'">기능장 대상자만 (공식양식)</button>
        <button class="btn-green" onclick="location.href='export_all.php'">전체 명단 CSV 다운로드</button>
      </div>
    </div>
    <div class="panel-body">
      <div class="scroll-x" style="max-height:60vh;">
        <table class="grid"><thead><tr><th>학년</th><th>반</th><th>학번</th><th>이름</th><th>보유종수</th><th>환산점수</th><th>비고</th></tr></thead>
        <tbody><?= $eligibleRows ?: '<tr><td colspan="7" class="empty-state">승인된 기능장 대상자가 아직 없습니다.</td></tr>' ?></tbody></table>
      </div>
    </div>
  </div>
</main>
<footer class="foot">한일여자고등학교 · 기능장 자격증 취합 시스템 · <?= h(cycle_label($cycle)) ?> 기준</footer>
</body></html>
