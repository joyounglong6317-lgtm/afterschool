<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/export.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
if (!$cycle) die('취합 학기가 없습니다.');
$db = get_db();

$stmt = $db->prepare(
    "SELECT u.grade, u.class_no, u.student_no AS no, u.name, u.enrollment_date, s.counts_json, s.status, s.past_winner, s.national, s.gyeongnam
     FROM users u JOIN submissions s ON s.student_id=u.id AND s.cycle_id=?
     WHERE u.role='student' AND s.status='approved' ORDER BY u.grade, u.class_no, u.student_no"
);
$stmt->execute([$cycle['id']]);
$rows = $stmt->fetchAll();

$eligible = [];
foreach ($rows as $r) {
    $counts = get_scored_counts($r['counts_json'], $r['enrollment_date']);
    $ev = eval_eligibility(['counts'=>$counts, 'past_winner'=>$r['past_winner'], 'national'=>$r['national'], 'gyeongnam'=>$r['gyeongnam']]);
    if (!$ev['eligible']) continue;
    $r['counts'] = $counts;
    $eligible[] = $r;
}
if (!$eligible) die('승인된 기능장 대상자가 아직 없습니다.');

// 학년-반별로 그룹화하여 반별 시트를 이어붙인 하나의 xls로 출력
$byClass = [];
foreach ($eligible as $r) { $byClass[$r['grade'].'_'.$r['class_no']][] = $r; }

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="기능장_대상자_전교(공식양식).xls"');
echo "\xEF\xBB\xBF<html><head><meta charset='UTF-8'><style>table{border-collapse:collapse;margin-bottom:24px;} td,th{border:1px solid #999;padding:4px 6px;font-size:11px;text-align:center;} th{background:#f1f0ea;} .title{font-size:14px;font-weight:bold;} .left{text-align:left;}</style></head><body>";
foreach ($byClass as $key => $rows2) {
    [$grade, $ban] = explode('_', $key);
    $t = get_db()->prepare("SELECT u.name FROM teacher_assignments ta JOIN users u ON u.id=ta.teacher_id WHERE ta.grade=? AND ta.class_no=?");
    $t->execute([$grade, $ban]);
    $teacherName = $t->fetchColumn() ?: null;
    $students = array_map(fn($r) => ['name'=>$r['name'], 'no'=>$r['no'], 'counts'=>$r['counts'], 'past_winner'=>$r['past_winner'], 'national'=>$r['national'], 'gyeongnam'=>$r['gyeongnam']], $rows2);
    $cols = template_columns();
    echo '<table><tr><td class="title" colspan="' . (count($cols)+3) . '">' . h($grade) . '학년 ' . h($ban) . '반</td></tr>';
    echo '<tr><th>학번</th><th>이름</th>';
    foreach ($cols as $c) echo '<th>' . h($c['label']) . '</th>';
    echo '<th>점수</th></tr>';
    foreach ($students as $s) {
        $sid = sprintf('%d%02d%02d', $grade, $ban, $s['no']);
        echo '<tr><td>' . h($sid) . '</td><td class="left">' . h($s['name']) . '</td>';
        foreach ($cols as $c) { $sum=0; foreach($c['ids'] as $id) $sum += ($s['counts'][$id] ?? 0); echo '<td>' . ($sum?:'') . '</td>'; }
        $ev = eval_eligibility($s);
        echo '<td>' . $ev['score'] . '</td></tr>';
    }
    echo '</table>';
}
echo '</body></html>';
