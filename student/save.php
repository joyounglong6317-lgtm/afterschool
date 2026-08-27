<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('student');
header('Content-Type: application/json; charset=utf-8');

verify_csrf();

$cycle = get_current_cycle();
if (!$cycle) { echo json_encode(['ok'=>false, 'error'=>'취합 학기 없음']); exit; }
$enrollmentDate = get_student_enrollment_date($user['id']);
$sub = get_or_create_submission($user['id'], (int)$cycle['id'], $enrollmentDate);

if (in_array($sub['status'], ['submitted','approved'], true) || is_past_deadline($cycle)) {
    echo json_encode(['ok'=>false, 'error'=>'수정할 수 없는 상태입니다.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$type = $body['type'] ?? '';
$db = get_db();
$raw = $sub['raw_counts']; // {item_id: ['n'=>int,'d'=>date|null]}

function normalize_date($v): ?string {
    $v = trim((string)($v ?? ''));
    if ($v === '') return null;
    // YYYY-MM-DD 형식만 허용
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
    return $v;
}

if ($type === 'count') {
    $id = $body['id'] ?? '';
    $item = item_by_id($id);
    if (!$item) { echo json_encode(['ok'=>false, 'error'=>'알 수 없는 항목']); exit; }
    $val = max(0, (int)($body['value'] ?? 0));
    if (empty($item['multi'])) $val = $val > 0 ? 1 : 0;
    $existingDate = $raw[$id]['d'] ?? null;
    $existingNote = $raw[$id]['note'] ?? null;
    $newDate = array_key_exists('date', $body) ? normalize_date($body['date']) : $existingDate;
    $raw[$id] = ['n' => $val, 'd' => $newDate, 'note' => $existingNote];
    $stmt = $db->prepare("UPDATE submissions SET counts_json=? WHERE id=?");
    $stmt->execute([json_encode($raw, JSON_UNESCAPED_UNICODE), $sub['id']]);

} elseif ($type === 'date') {
    $id = $body['id'] ?? '';
    $item = item_by_id($id);
    if (!$item) { echo json_encode(['ok'=>false, 'error'=>'알 수 없는 항목']); exit; }
    $n = (int)($raw[$id]['n'] ?? 0);
    $note = $raw[$id]['note'] ?? null;
    $raw[$id] = ['n' => $n, 'd' => normalize_date($body['value'] ?? null), 'note' => $note];
    $stmt = $db->prepare("UPDATE submissions SET counts_json=? WHERE id=?");
    $stmt->execute([json_encode($raw, JSON_UNESCAPED_UNICODE), $sub['id']]);

} elseif ($type === 'note') {
    $id = $body['id'] ?? '';
    $item = item_by_id($id);
    if (!$item || !in_array($id, NOTE_ITEM_IDS, true)) { echo json_encode(['ok'=>false, 'error'=>'알 수 없는 항목']); exit; }
    $n = (int)($raw[$id]['n'] ?? 0);
    $date = $raw[$id]['d'] ?? null;
    $noteVal = mb_substr(trim((string)($body['value'] ?? '')), 0, 100);
    $raw[$id] = ['n' => $n, 'd' => $date, 'note' => $noteVal !== '' ? $noteVal : null];
    $stmt = $db->prepare("UPDATE submissions SET counts_json=? WHERE id=?");
    $stmt->execute([json_encode($raw, JSON_UNESCAPED_UNICODE), $sub['id']]);

} elseif ($type === 'flags') {
    $national = !empty($body['national']) ? 1 : 0;
    $gy = isset($body['gyeongnam']) && $body['gyeongnam'] !== null && $body['gyeongnam'] !== '' ? (int)$body['gyeongnam'] : null;
    $stmt = $db->prepare("UPDATE submissions SET national=?, gyeongnam=? WHERE id=?");
    $stmt->execute([$national, $gy, $sub['id']]);
    $sub['national'] = $national; $sub['gyeongnam'] = $gy;

} elseif ($type === 'other_cert') {
    $val = trim((string)($body['value'] ?? ''));
    $stmt = $db->prepare("UPDATE submissions SET other_cert_name=? WHERE id=?");
    $stmt->execute([$val, $sub['id']]);

} elseif ($type === 'confirm') {
    $val = !empty($body['value']) ? 1 : 0;
    $stmt = $db->prepare("UPDATE submissions SET confirmed_enrolled=? WHERE id=?");
    $stmt->execute([$val, $sub['id']]);

} else {
    echo json_encode(['ok'=>false, 'error'=>'invalid type']); exit;
}

$scoredCounts = apply_enrollment_filter($raw, $enrollmentDate);
$ev = eval_eligibility(['counts'=>$scoredCounts, 'past_winner'=>$sub['past_winner'], 'national'=>$sub['national'], 'gyeongnam'=>$sub['gyeongnam']]);
echo json_encode([
    'ok'=>true, 'score'=>$ev['score'], 'types'=>count_types($scoredCounts),
    'eligible'=>$ev['eligible'], 'reason'=>$ev['reason'],
], JSON_UNESCAPED_UNICODE);
