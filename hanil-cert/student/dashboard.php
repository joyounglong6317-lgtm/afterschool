<?php
require_once __DIR__ . '/../includes/functions.php';
$user = bootstrap_page('student');
$cycle = get_current_cycle();
if (!$cycle) { die('현재 취합 학기가 설정되지 않았습니다. 관리자에게 문의하세요.'); }
$enrollmentDate = get_student_enrollment_date($user['id']);
$sub = get_or_create_submission($user['id'], (int)$cycle['id'], $enrollmentDate);

$locked = in_array($sub['status'], ['submitted','approved'], true);
$deadlinePassed = is_past_deadline($cycle) && in_array($sub['status'], ['draft','rejected'], true);
$disabled = $locked || $deadlinePassed;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $db = get_db();
    if ($action === 'submit' && !$disabled) {
        if (is_past_deadline($cycle)) { $_SESSION['flash'] = '제출 마감 시간이 지났습니다.'; }
        else {
            $stmt = $db->prepare("UPDATE submissions SET status='submitted', teacher_comment=NULL, submitted_at=NOW() WHERE id=?");
            $stmt->execute([$sub['id']]);
        }
        header('Location: dashboard.php'); exit;
    } elseif ($action === 'cancel') {
        if (!is_past_deadline($cycle)) {
            $stmt = $db->prepare("UPDATE submissions SET status='draft', teacher_comment=NULL WHERE id=?");
            $stmt->execute([$sub['id']]);
        }
        header('Location: dashboard.php'); exit;
    }
    header('Location: dashboard.php'); exit;
}

$ev = eval_eligibility(['counts'=>$sub['counts'], 'past_winner'=>$sub['past_winner'], 'national'=>$sub['national'], 'gyeongnam'=>$sub['gyeongnam']]);
$types = count_types($sub['counts']);
$excluded = get_enrollment_excluded($sub['raw_counts'], $enrollmentDate);

$grouped = [];
foreach (all_items() as $it) { $grouped[$it['grp']][] = $it; }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>학생 대시보드 - <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.grp-item{flex-wrap:wrap;}
.date-input{width:130px; margin-left:6px;}
.required-note{font-size:10px; color:#8a611f; background:var(--gold-light); padding:1px 6px; border-radius:8px; margin-left:6px;}
</style>
</head>
<body>
<header class="top">
  <div style="display:flex; align-items:center; gap:14px;">
    <div class="emblem header-emblem"><span style="font-size:9px;">HANIL</span></div>
    <div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="badge">기능장 <?= MIN_SCORE ?></span>
        <h1>자격증 입력</h1>
      </div>
      <div class="sub"><?= h($user['name']) ?> 학생 (<?= h($user['grade']) ?>학년 <?= h($user['class_no']) ?>반 <?= h($user['student_no']) ?>번) · <?= h(cycle_label($cycle)) ?></div>
    </div>
  </div>
  <div class="top-actions">
    <span class="save-indicator" id="saveIndicator"></span>
    <button class="btn-outline" onclick="location.href='pw_change.php'">비밀번호 변경</button>
    <button class="btn-outline" onclick="location.href='../logout.php'">로그아웃</button>
  </div>
</header>

<main>
  <div class="panel">
    <div class="panel-head">
      <h2>내 자격증 입력</h2>
      <div style="display:flex; align-items:center; gap:8px;">
        <?php if ($cycle['deadline_at']): ?>
          <span class="sm" style="color:<?= is_past_deadline($cycle) ? 'var(--red)' : '#a39d8f' ?>;">
            <?= is_past_deadline($cycle) ? '⏰ 제출 마감(' . h(format_deadline($cycle)) . ')됨' : '제출 마감: ' . h(format_deadline($cycle)) . '까지' ?>
          </span>
        <?php endif; ?>
        <?= status_badge($sub['status']) ?>
      </div>
    </div>
    <div class="panel-body">
      <?php if ($sub['status'] === 'rejected'): ?>
        <div class="alert" style="background:var(--red-light); color:var(--red);">
          <b>담임 선생님이 반려했습니다.</b> 사유: <?= h($sub['teacher_comment'] ?: '(사유 미기재)') ?>
        </div>
      <?php endif; ?>

      <div class="alert" style="background:var(--gold-light); color:#7a5518; line-height:1.6;">
        ❗ <b>필수 2종(컴활 2급 이상, 전산회계 2급 이상)은 재학 여부와 관계없이 인정됩니다.</b><br>
        그 외 자격증은 <b>본교 재학 중(입학일 이후)에 취득한 것만</b> 점수에 반영됩니다 — 항목마다 <b>취득일자</b>를 꼭 입력해주세요.<br>
        <?php if ($enrollmentDate): ?>
          내 입학일: <b><?= h($enrollmentDate) ?></b> (이보다 이전 취득분은 필수 2종 외에는 자동으로 점수 계산에서 제외됩니다)
        <?php else: ?>
          <span style="color:var(--red);">⚠ 입학일이 아직 등록되지 않아 재학중 취득 여부가 자동 판정되지 않습니다. 담임/관리자에게 입학일 등록을 요청하세요.</span>
        <?php endif; ?>
      </div>

      <?php if ($excluded): ?>
        <div class="alert" style="background:var(--red-light); color:var(--red);">
          아래 항목은 입학일 이전 취득일로 입력되어 점수·종수 계산에서 제외되었습니다:
          <?php foreach ($excluded as $id => $date): $it = item_by_id($id); ?>
            <div>· <?= h($it['name'] ?? $id) ?> (취득일 <?= h($date) ?>)</div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="score-banner <?= $ev['eligible']?'eligible':'' ?>" id="scoreBanner">
        <div><div class="big" id="sScoreVal"><?= $ev['score'] ?></div><div class="sm">환산점수 (<?= MIN_SCORE ?>점 이상 필요)</div></div>
        <div><div class="big" id="sTypeVal"><?= $types ?></div><div class="sm">보유 종수 (참고용)</div></div>
        <div style="flex:1; text-align:right;">
          <div id="sEligibleText" style="font-weight:800; font-size:14px;"><?= $ev['eligible']?'기능장 대상 기준 충족':'기준 미충족' ?></div>
          <div class="sm" id="sEligibleReason"><?= h($ev['reason']) ?></div>
        </div>
      </div>

      <form id="flagForm" class="field-row" style="margin-bottom:16px;">
        <div class="field" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" id="f_national" style="width:auto;" <?= $sub['national']?'checked':'' ?> <?= $disabled?'disabled':'' ?>>
          <label style="margin:0;" for="f_national">전국기능경기대회 참가자입니다</label>
        </div>
        <div class="field" style="max-width:220px;">
          <label>경남기능경기대회 순위 (해당시)</label>
          <input type="number" id="f_gyeongnam" min="1" max="99" value="<?= h($sub['gyeongnam']) ?>" <?= $disabled?'disabled':'' ?>>
        </div>
      </form>

      <div id="grpCardsWrap">
        <?php foreach (group_order() as $g): if (empty($grouped[$g])) continue; ?>
          <div class="grp-card">
            <div class="grp-title"><?= h($g) ?></div>
            <?php if ($g === '참고용(0점·기능장 미반영)'): ?>
              <div class="grp-note">학교 자격증 취득 현황 조사(전수조사) 제출용으로만 쓰이며, 기능장 점수·종수 계산에는 반영되지 않습니다.</div>
            <?php endif; ?>
            <div class="grp-items">
              <?php foreach ($grouped[$g] as $it):
                $entry = $sub['raw_counts'][$it['id']] ?? ['n'=>0,'d'=>null,'note'=>null];
                $val = (int)($entry['n'] ?? 0);
                $dateVal = $entry['d'] ?? '';
                $noteVal = $entry['note'] ?? '';
                $isRequired = in_array($it['id'], REQUIRED_ITEM_IDS, true);
                $needsNote = in_array($it['id'], NOTE_ITEM_IDS, true); ?>
                <div class="grp-item">
                  <div>
                    <div class="it-name"><?= h($it['name']) ?><?php if ($isRequired): ?><span class="required-note">재학무관 인정</span><?php endif; ?></div>
                    <div class="it-pts"><?= $it['pts'] ?>점</div>
                  </div>
                  <div style="display:flex; align-items:center; flex-wrap:wrap; gap:4px;">
                    <?php if (!empty($it['multi'])): ?>
                      <input type="number" min="0" max="20" class="cert-input" data-id="<?= h($it['id']) ?>"
                             value="<?= $val ?: '' ?>" <?= $disabled?'disabled':'' ?>>
                    <?php else: ?>
                      <input type="checkbox" class="cert-input" data-id="<?= h($it['id']) ?>"
                             <?= $val>0?'checked':'' ?> <?= $disabled?'disabled':'' ?>>
                    <?php endif; ?>
                    <?php if (empty($it['tracking'])): ?>
                      <input type="date" class="date-input cert-date" data-id="<?= h($it['id']) ?>"
                             value="<?= h($dateVal) ?>" title="취득일자" <?= $disabled?'disabled':'' ?>>
                    <?php endif; ?>
                    <?php if ($needsNote):
                      $notePlaceholder = $it['grp'] === '대회입상'
                        ? '수상 종목 입력 (예: 워드프로세서 실기, 전산회계)'
                        : '자격증명 입력 (예: 한자능력검정 3급)'; ?>
                      <input type="text" class="note-input cert-note" data-id="<?= h($it['id']) ?>"
                             value="<?= h($noteVal) ?>" placeholder="<?= h($notePlaceholder) ?>"
                             style="width:220px;" maxlength="100" <?= $disabled?'disabled':'' ?>>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if ($g === '참고용(0점·기능장 미반영)'): ?>
                <div class="grp-item" style="grid-column:1/-1;">
                  <div><div class="it-name">기타자격증 (명칭 직접 입력, 예: DIAT)</div></div>
                  <input type="text" id="f_othercert" style="width:180px; text-align:left;" value="<?= h($sub['other_cert_name']) ?>" <?= $disabled?'disabled':'' ?>>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex; align-items:center; gap:8px; margin-top:18px;">
        <input type="checkbox" id="f_confirm" <?= $sub['confirmed_enrolled']?'checked':'' ?> <?= $disabled?'disabled':'' ?>>
        <label for="f_confirm" style="margin:0; font-size:12.5px;">위 자격증의 취득일자를 정확히 입력했으며, 필수 2종 외에는 재학 중 취득한 것임을 확인합니다.</label>
      </div>

      <form method="post" style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; align-items:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="formAction" value="submit">
        <?php if (!$disabled): ?>
          <button type="submit" class="btn-gold" id="submitBtn" <?= (!$sub['confirmed_enrolled'])?'disabled':'' ?>>담임 선생님께 제출하기</button>
        <?php elseif ($deadlinePassed): ?>
          <button type="button" class="btn-gold" disabled>제출 마감됨</button>
        <?php elseif ($sub['status']==='submitted'): ?>
          <button type="button" class="btn-gold" disabled>담임 확인 대기 중</button>
        <?php else: ?>
          <button type="button" class="btn-gold" disabled>승인 완료됨</button>
        <?php endif; ?>

        <?php if (($sub['status']==='submitted' || $sub['status']==='approved') && !is_past_deadline($cycle)): ?>
          <button type="submit" class="btn-outline" style="color:var(--ink); border-color:var(--line);"
                  onclick="document.getElementById('formAction').value='cancel'; return confirm('<?= $sub['status']==='approved' ? '담임 선생님이 이미 승인한 내용입니다. 취소하면 다시 확인을 받아야 합니다. 계속할까요?' : '제출을 취소하고 수정 화면으로 돌아갈까요?' ?>');">
            제출 취소하고 수정하기
          </button>
        <?php endif; ?>
        <span class="sm" style="color:#a39d8f;">제출 후에도 취소 후 다시 수정할 수 있습니다.</span>
      </form>
    </div>
  </div>
</main>
<footer class="foot">한일여자고등학교 · 기능장 자격증 취합 시스템 · <?= h(cycle_label($cycle)) ?> 기준</footer>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function flashSave(status){
  const el = document.getElementById('saveIndicator');
  if(status==='saving'){ el.textContent='● 저장 중...'; el.classList.add('saving'); }
  else { el.textContent='● 저장됨'; el.classList.remove('saving'); }
}
async function saveField(payload){
  flashSave('saving');
  const res = await fetch('save.php', {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  flashSave('ok');
  if(data.ok){
    document.getElementById('sScoreVal').textContent = data.score;
    document.getElementById('sTypeVal').textContent = data.types;
    document.getElementById('sEligibleText').textContent = data.eligible ? '기능장 대상 기준 충족' : '기준 미충족';
    document.getElementById('sEligibleReason').textContent = data.reason;
    document.getElementById('scoreBanner').classList.toggle('eligible', data.eligible);
  }
}
document.querySelectorAll('.cert-input').forEach(el=>{
  el.addEventListener('change', ()=>{
    const id = el.dataset.id;
    const val = el.type==='checkbox' ? (el.checked?1:0) : (parseInt(el.value)||0);
    const dateEl = document.querySelector('.cert-date[data-id="'+id+'"]');
    saveField({type:'count', id, value: val, date: dateEl ? dateEl.value : null});
  });
});
document.querySelectorAll('.cert-date').forEach(el=>{
  el.addEventListener('change', ()=>{
    const id = el.dataset.id;
    saveField({type:'date', id, value: el.value});
  });
});
document.querySelectorAll('.cert-note').forEach(el=>{
  el.addEventListener('change', ()=>{
    const id = el.dataset.id;
    saveField({type:'note', id, value: el.value});
  });
});
const natEl = document.getElementById('f_national');
const gyEl = document.getElementById('f_gyeongnam');
if(natEl) natEl.addEventListener('change', ()=> saveField({type:'flags', national: natEl.checked?1:0, gyeongnam: gyEl.value||null}));
if(gyEl) gyEl.addEventListener('change', ()=> saveField({type:'flags', national: natEl.checked?1:0, gyeongnam: gyEl.value||null}));
const otherEl = document.getElementById('f_othercert');
if(otherEl) otherEl.addEventListener('change', ()=> saveField({type:'other_cert', value: otherEl.value}));
const confirmEl = document.getElementById('f_confirm');
if(confirmEl) confirmEl.addEventListener('change', ()=>{
  saveField({type:'confirm', value: confirmEl.checked?1:0});
  const btn = document.getElementById('submitBtn');
  if(btn) btn.disabled = !confirmEl.checked;
});
</script>
</body>
</html>
