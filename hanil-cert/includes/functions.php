<?php
/**
 * includes/functions.php
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/scoring.php';

function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function get_current_cycle(): ?array {
    $row = get_db()->query("SELECT * FROM cycles WHERE is_current = 1 LIMIT 1")->fetch();
    return $row ?: null;
}

function get_ban_counts(): array {
    $rows = get_db()->query("SELECT grade, class_count FROM ban_counts")->fetchAll();
    $out = [1=>10, 2=>10, 3=>10];
    foreach ($rows as $r) $out[(int)$r['grade']] = (int)$r['class_count'];
    return $out;
}

/** 학생 1명의 특정 주기(cycle) 제출 데이터 조회, 없으면 draft 행을 생성해서 반환.
 *  'raw_counts' = {item_id: ['n'=>개수,'d'=>취득일]} 원본 (입력화면 표시용)
 *  'counts'     = 재학중 취득 판정을 적용한 단순 {item_id: 개수} (점수계산용, 필수2종은 예외)
 */
function get_or_create_submission(int $studentId, int $cycleId, ?string $enrollmentDate = null): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM submissions WHERE student_id=? AND cycle_id=?");
    $stmt->execute([$studentId, $cycleId]);
    $row = $stmt->fetch();
    if ($row) {
        $raw = decode_counts_json($row['counts_json']);
        $row['raw_counts'] = $raw;
        $row['counts'] = apply_enrollment_filter($raw, $enrollmentDate);
        return $row;
    }
    $ins = $db->prepare("INSERT INTO submissions (student_id, cycle_id, counts_json) VALUES (?, ?, '{}')");
    $ins->execute([$studentId, $cycleId]);
    return [
        'id' => $db->lastInsertId(), 'student_id' => $studentId, 'cycle_id' => $cycleId,
        'raw_counts' => [], 'counts' => [], 'national' => 0, 'gyeongnam' => null, 'past_winner' => 0,
        'other_cert_name' => '', 'confirmed_enrolled' => 0, 'status' => 'draft',
        'teacher_comment' => null,
    ];
}

/** 학생 계정의 입학일 조회 */
function get_student_enrollment_date(int $studentId): ?string {
    $stmt = get_db()->prepare("SELECT enrollment_date FROM users WHERE id=?");
    $stmt->execute([$studentId]);
    $v = $stmt->fetchColumn();
    return $v ?: null;
}


function status_label(string $status): string {
    return ['draft'=>'임시저장', 'submitted'=>'제출됨·확인대기', 'approved'=>'승인완료', 'rejected'=>'반려됨'][$status] ?? $status;
}
function status_badge(string $status): string {
    $cls = ['draft'=>'draft','submitted'=>'submitted','approved'=>'approved','rejected'=>'rejected'][$status] ?? 'draft';
    return '<span class="status-badge ' . $cls . '">' . h(status_label($status)) . '</span>';
}

function is_past_deadline(?array $cycle): bool {
    if (!$cycle || empty($cycle['deadline_at'])) return false;
    return strtotime($cycle['deadline_at']) < time();
}
function format_deadline(?array $cycle): string {
    if (!$cycle || empty($cycle['deadline_at'])) return '';
    return date('Y-m-d H:i', strtotime($cycle['deadline_at']));
}

/** 그룹 표시 순서 */
function group_order(): array {
    return ['필수','IT/사무','디자인','회계/세무','금융','기타','참고용(0점·기능장 미반영)','대회입상'];
}
