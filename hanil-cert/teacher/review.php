<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('teacher');
$cycle = get_current_cycle();

$assign = get_db()->prepare("SELECT * FROM teacher_assignments WHERE teacher_id=?");
$assign->execute([$user['id']]);
$myClass = $assign->fetch();
if (!$myClass) { die('담당 학급이 배정되지 않았습니다.'); }
$grade = (int)$myClass['grade']; $ban = (int)$myClass['class_no'];

$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

$chk = get_db()->prepare("SELECT * FROM users WHERE id=? AND role='student' AND grade=? AND class_no=?");
$chk->execute([$studentId, $grade, $ban]);
$student = $chk->fetch();
if (!$student) { http_response_code(403); die('해당 학생에 대한 열람 권한이 없습니다.'); }
$enrollmentDate = $student['enrollment_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $sub = get_or_create_submission($studentId, (int)$cycle['id'], $enrollmentDate);
    $db = get_db();
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE submissions SET status='approved', reviewer_id=?, reviewed_at=NOW(), teacher_comment=NULL WHERE id=?");
        $stmt->execute([$user['id'], $sub['id']]);
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        $stmt = $db->prepare("UPDATE submissions SET status='rejected', reviewer_id=?, reviewed_at=NOW(), teacher_comment=? WHERE id=?");
        $stmt->execute([$user['id'], $reason, $sub['id']]);
    } elseif ($action === 'toggle_past') {
        $val = !empty($_POST['past']) ? 1 : 0;
        $stmt = $db->prepare("UPDATE submissions SET past_winner=? WHERE id=?");
        $stmt->execute([$val, $sub['id']]);
    }
    header('Location: review.php?student_id=' . $studentId); exit;
}

$sub = get_or_create_submission($studentId, (int)$cycle['id'], $enrollmentDate);
$ev = eval_eligibility(['counts'=>$sub['counts'], 'past_winner'=>$sub['past_winner'], 'national'=>$sub['national'], 'gyeongnam'=>$sub['gyeongnam']]);
$typesDetail = get_held_types_detail($sub['counts']);
$effCounts = get_effective_counts($sub['counts']);
$rawHeld = array_filter(all_items(), fn($it) => (int)($sub['raw_counts'][$it['id']]['n'] ?? 0) > 0);
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($student['name']) ?> 검토 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<header class="top">
  <div style="display:flex; align-items:center; gap:14px;">
    <div class="emblem header-emblem"><span style="font-size:9px;">HANIL</span></div>
    <div><h1><?= h($student['name']) ?> 학생 검토</h1><div class="sub"><?= h($grade) ?>학년 <?= h($ban) ?>반 <?= h($student['student_no']) ?>번 · <?= h(cycle_label($cycle)) ?> · 입학일: <?= h($enrollmentDate ?: '미등록') ?></div></div>
  </div>
  <div class="top-actions"><button class="btn-outline" onclick="location.href='dashboard.php'">목록으로</button></div>
</header>
<main>
  <div class="panel">
    <div class="panel-body">
      <?php if (!$enrollmentDate): ?>
        <div class="alert" style="background:var(--red-light); color:var(--red);">
          ⚠ 이 학생은 입학일이 등록되지 않아 재학중 취득 여부가 자동 판정되지 않고 있습니다 (모든 항목이 인정된 것으로 계산됨). 관리자에게 [계정 관리]에서 입학일 등록을 요청하세요.
        </div>
      <?php endif; ?>

      <div class="score-banner <?= $ev['eligible']?'eligible':'' ?>">
        <div><div class="big"><?= $ev['score'] ?></div><div class="sm">환산점수</div></div>
        <div><div class="big"><?= count_types($sub['counts']) ?></div><div class="sm">보유 종수(참고용)</div></div>
        <div style="flex:1;text-align:right;">
          <div style="font-weight:800;font-size:14px;"><?= $ev['eligible']?'기능장 대상 기준 충족':'기준 미충족' ?></div>
          <div class="sm"><?= h($ev['reason']) ?></div>
        </div>
      </div>

      <?php if ($typesDetail): ?>
        <div style="background:#eef3fa; border:1px solid #c9d7ec; border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:12px;">
          <b>보유 종수 <?= count($typesDetail) ?>종 상세</b>
          <ol style="margin:6px 0 0; padding-left:18px; line-height:1.7;">
            <?php foreach ($typesDetail as $g): ?>
              <li><?= h($g['label']) ?><?php if (count($g['items'])>1): ?> <span style="color:#767268;">(<?= h(implode(', ', $g['items'])) ?>)</span><?php endif; ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>

      <div class="item-list" style="font-size:12.5px; line-height:2;">
        <?php if (!$rawHeld): ?>
          <div style="color:#a39d8f;">입력된 자격증이 없습니다.</div>
        <?php endif; ?>
        <?php foreach ($rawHeld as $it):
          $id = $it['id'];
          $rawEntry = $sub['raw_counts'][$id] ?? ['n'=>0,'d'=>null,'note'=>null];
          $rawN = (int)$rawEntry['n'];
          $date = $rawEntry['d'] ?? null;
          $note = $rawEntry['note'] ?? null;
          $noteWarnLabel = $it['grp'] === '대회입상' ? '(수상 종목 미입력)' : '(명칭 미입력)';
          $noteLabel = $note ? ' — "' . h($note) . '"' : (in_array($id, NOTE_ITEM_IDS, true) ? ' <span style="color:var(--red);">'.h($noteWarnLabel).'</span>' : '');
          $isRequired = in_array($id, REQUIRED_ITEM_IDS, true);
          $enrollExcluded = !empty($it['tracking']) ? false : (!$isRequired && $enrollmentDate && $date && $date < $enrollmentDate);
          $scoredN = $sub['counts'][$id] ?? 0;
          $tierExcluded = !$enrollExcluded && ($effCounts[$id] ?? 0) === 0 && $scoredN > 0; ?>
          <?php if (!empty($it['tracking'])): ?>
            <div style="color:#a39d8f;"><?= h($it['name']) ?><?= $noteLabel ?> × <?= $rawN ?> <span class="status-badge draft">참고용·0점</span></div>
          <?php elseif ($enrollExcluded): ?>
            <div style="color:#a39d8f;"><?= h($it['name']) ?><?= $noteLabel ?> × <?= $rawN ?> (취득일 <?= h($date) ?>) <span style="text-decoration:line-through;">= <?= $it['pts']*$rawN ?>점</span> <span class="status-badge draft" style="background:var(--red-light);color:var(--red);">입학 전 취득으로 제외</span></div>
          <?php elseif ($tierExcluded): ?>
            <div style="color:#a39d8f;"><?= h($it['name']) ?><?= $noteLabel ?> × <?= $rawN ?><?= $date ? ' (취득일 '.h($date).')' : '' ?> <span style="text-decoration:line-through;">= <?= $it['pts']*$rawN ?>점</span> <span class="status-badge draft">상위급수 보유로 제외</span></div>
          <?php else: ?>
            <div><?= h($it['name']) ?><?= $noteLabel ?> × <?= $rawN ?><?= $date ? ' (취득일 '.h($date).')' : '' ?> = <b><?= $it['pts']*$rawN ?>점</b></div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($sub['other_cert_name']): ?><div style="margin-top:6px;color:#a39d8f;">기타자격증(참고): <?= h($sub['other_cert_name']) ?></div><?php endif; ?>
      </div>

      <div style="margin-top:10px; font-size:12px; padding:8px 12px; border-radius:6px; background:<?= $sub['confirmed_enrolled']?'var(--green-light)':'var(--red-light)' ?>; color:<?= $sub['confirmed_enrolled']?'var(--green)':'var(--red)' ?>;">
        <?= $sub['confirmed_enrolled'] ? '✓ 학생이 취득일자 확인 체크함' : '⚠ 학생이 취득일자 확인을 체크하지 않았습니다' ?>
      </div>

      <form method="post" style="display:flex; align-items:center; gap:8px; margin-top:10px; background:#f6efe2; padding:9px 12px; border-radius:6px;">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <input type="hidden" name="action" value="toggle_past">
        <input type="checkbox" name="past" id="pastChk" onchange="this.form.submit()" <?= $sub['past_winner']?'checked':'' ?> style="width:auto;">
        <label for="pastChk" style="margin:0; font-size:12.5px;">이 학생은 이전 학기에 이미 기능장을 수상했습니다 (자동으로 대상에서 제외됩니다)</label>
      </form>

      <div style="background:#f1f0ea; color:#666; font-size:11.5px; padding:9px 12px; border-radius:6px; margin-top:10px; line-height:1.6;">
        승인 전 [나이스]-[학급담임]-[학생부]-[학교생활기록부]-[학생부 항목별 조회]-[자격증/인증 취득상황]에서 실제 취득일자를 대조해주세요. 입학 전 취득분은 필수 2종(컴활/전산회계) 외에는 시스템이 자동으로 점수 계산에서 제외합니다.
      </div>

      <form method="post" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <label style="font-size:12px;font-weight:700;color:#555;display:block;margin-bottom:6px;">반려 사유 (반려 시 입력)</label>
        <textarea name="reject_reason" style="width:100%; min-height:70px;" placeholder="예: 전산회계 2급 사본이 누락되었습니다."></textarea>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
          <button type="button" class="btn-ghost" onclick="location.href='dashboard.php'">닫기</button>
          <button type="submit" name="action" value="reject" class="btn-red">반려(수정요청)</button>
          <button type="submit" name="action" value="approve" class="btn-gold">승인</button>
        </div>
      </form>
    </div>
  </div>
</main>
</body></html>
