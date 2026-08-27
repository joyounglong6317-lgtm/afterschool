<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('teacher');
$cycle = get_current_cycle();
$assign = get_db()->prepare("SELECT * FROM teacher_assignments WHERE teacher_id=?");
$assign->execute([$user['id']]);
$myClass = $assign->fetch();
if (!$myClass || !$cycle) { die('데이터가 없습니다.'); }
$grade = (int)$myClass['grade']; $ban = (int)$myClass['class_no'];

$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
if (!$ids) { die('선택된 학생이 없습니다.'); }
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = get_db()->prepare(
    "SELECT u.id, u.name, u.student_no AS no, u.enrollment_date, s.counts_json, s.past_winner, s.national, s.gyeongnam
     FROM users u LEFT JOIN submissions s ON s.student_id=u.id AND s.cycle_id=?
     WHERE u.role='student' AND u.grade=? AND u.class_no=? AND u.id IN ($placeholders)
     ORDER BY u.student_no"
);
$stmt->execute(array_merge([$cycle['id'], $grade, $ban], $ids));
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><title>점수표 인쇄</title><link rel="stylesheet" href="../assets/style.css">
<style>
@media print{
  body > *:not(#printArea){display:block !important;}
}
</style>
</head>
<body onload="window.print()">
<?php foreach ($students as $s):
    $raw = decode_counts_json($s['counts_json']);
    $counts = apply_enrollment_filter($raw, $s['enrollment_date']);
    $eff = get_effective_counts($counts);
    $ev = eval_eligibility(['counts'=>$counts, 'past_winner'=>$s['past_winner'], 'national'=>$s['national'], 'gyeongnam'=>$s['gyeongnam']]);
    $held = array_filter(all_items(), fn($it) => !empty($it['tracking']) ? false : ((int)($raw[$it['id']]['n'] ?? 0)) > 0);
?>
<div class="print-page">
  <h2 style="text-align:center;margin-bottom:4px;"><?= h(cycle_label($cycle)) ?> 기능장 점수합계표</h2>
  <p style="text-align:center;color:#555;margin-top:0;"><?= $grade ?>학년 <?= $ban ?>반 <?= h($s['no']) ?>번 &nbsp; <?= h($s['name']) ?> &nbsp; (학번 <?= sprintf('%d%02d%02d',$grade,$ban,$s['no']) ?>)</p>
  <table>
    <thead><tr><th>자격증명</th><th>개수</th><th>취득일</th><th>배점</th><th>취득점수</th></tr></thead>
    <tbody>
    <?php if (!$held): ?><tr><td colspan="5" style="text-align:center;">취득 자격증 없음</td></tr><?php endif; ?>
    <?php foreach ($held as $it):
        $id = $it['id'];
        $rawN = (int)($raw[$id]['n'] ?? 0);
        $date = $raw[$id]['d'] ?? '';
        $isRequired = in_array($id, REQUIRED_ITEM_IDS, true);
        $enrollExcluded = !$isRequired && $s['enrollment_date'] && $date && $date < $s['enrollment_date'];
        $tierExcluded = !$enrollExcluded && ($eff[$id] ?? 0) === 0 && ($counts[$id] ?? 0) > 0;
        $cell = $enrollExcluded ? '제외(입학 전 취득)' : ($tierExcluded ? '제외(상위급수 보유)' : ($it['pts']*$rawN . '점')); ?>
      <tr><td><?= h($it['name']) ?><?= !empty($raw[$id]['note']) ? ' — '.h($raw[$id]['note']) : '' ?></td><td style="text-align:center;"><?= $rawN ?></td><td style="text-align:center;"><?= h($date) ?></td><td style="text-align:center;"><?= $it['pts'] ?>점</td>
        <td style="text-align:center;"><?= $cell ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p style="margin-top:16px; font-size:14px;"><b>보유 종수: <?= count_types($counts) ?>종</b> &nbsp;|&nbsp; <b>환산점수: <?= $ev['score'] ?>점</b> &nbsp;|&nbsp; <b>기능장 대상 여부: <?= $ev['eligible']?'대상':'미대상' ?></b> (<?= h($ev['reason']) ?>)</p>
  <p style="margin-top:60px; text-align:right; font-size:12px; color:#555;">발행일: <?= date('Y-m-d') ?> &nbsp; 담임: <?= h($user['name']) ?></p>
</div>
<?php endforeach; ?>
</body></html>
