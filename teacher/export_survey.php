<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/export.php';
$user = bootstrap_page('teacher');
$cycle = get_current_cycle();
$assign = get_db()->prepare("SELECT * FROM teacher_assignments WHERE teacher_id=?");
$assign->execute([$user['id']]);
$myClass = $assign->fetch();
if (!$myClass || !$cycle) { die('데이터가 없습니다.'); }
$grade = (int)$myClass['grade']; $ban = (int)$myClass['class_no'];

$stmt = get_db()->prepare(
    "SELECT u.name, u.student_no AS no, u.enrollment_date, s.counts_json, s.other_cert_name
     FROM users u LEFT JOIN submissions s ON s.student_id=u.id AND s.cycle_id=?
     WHERE u.role='student' AND u.grade=? AND u.class_no=? ORDER BY u.student_no"
);
$stmt->execute([$cycle['id'], $grade, $ban]);
$students = array_map(function($r) {
    $r['counts'] = get_scored_counts($r['counts_json'], $r['enrollment_date']);
    return $r;
}, $stmt->fetchAll());

render_survey_xls($students, $grade, $ban, $user['name'], "자격증취득현황표_{$grade}학년_{$ban}반.xls");
