<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
if (!$cycle) die('취합 학기가 없습니다.');

$stmt = get_db()->prepare(
    "SELECT u.grade, u.class_no, u.student_no AS no, u.name, u.enrollment_date, s.counts_json, s.status, s.past_winner, s.national, s.gyeongnam
     FROM users u LEFT JOIN submissions s ON s.student_id=u.id AND s.cycle_id=?
     WHERE u.role='student' ORDER BY u.grade, u.class_no, u.student_no"
);
$stmt->execute([$cycle['id']]);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="전교_기능장_취합_' . $cycle['school_year'] . '_' . $cycle['semester'] . '학기.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['학년','반','번호','이름','상태','보유종수','환산점수','기능장대상여부','비고']);
foreach ($rows as $r) {
    $counts = get_scored_counts($r['counts_json'], $r['enrollment_date']);
    $ev = eval_eligibility(['counts'=>$counts, 'past_winner'=>$r['past_winner'], 'national'=>$r['national'], 'gyeongnam'=>$r['gyeongnam']]);
    fputcsv($out, [
        $r['grade'], $r['class_no'], $r['no'], $r['name'], status_label($r['status'] ?: 'draft'),
        count_types($counts), $ev['score'], $ev['eligible'] ? '대상' : '미대상', $ev['reason'],
    ]);
}
fclose($out);
