<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$db = get_db();
$cycle = get_current_cycle(); // _nav.php 상단 표시용
$msg = '';

$schoolYear = (int)($_POST['school_year'] ?? $_GET['school_year'] ?? ($cycle['school_year'] ?? date('Y')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    if ($action === 'save_semester_deadlines') {
        foreach (['1', '2'] as $sem) {
            $raw = trim($_POST["deadline_{$sem}"] ?? '');
            $val = $raw !== '' ? date('Y-m-d H:i:s', strtotime($raw)) : null;
            // 학기 주기가 없으면 새로 생성(is_current는 건드리지 않음), 있으면 마감일만 갱신
            $stmt = $db->prepare(
                "INSERT INTO cycles (school_year, semester, is_current, deadline_at) VALUES (?, ?, 0, ?)
                 ON DUPLICATE KEY UPDATE deadline_at = VALUES(deadline_at)"
            );
            $stmt->execute([$schoolYear, $sem, $val]);
        }
        $msg = "{$schoolYear}학년도 1학기·2학기 마감일이 저장되었습니다.";
    }
}

$rows = $db->prepare("SELECT * FROM cycles WHERE school_year = ?");
$rows->execute([$schoolYear]);
$bySemester = ['1' => null, '2' => null];
foreach ($rows->fetchAll() as $r) { $bySemester[$r['semester']] = $r; }
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>학기별 마감일 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <div class="panel">
    <div class="panel-head"><h2>학기별 기능장 충족 마감일 설정</h2></div>
    <div class="panel-body">
      <div class="hint">1학기와 2학기 제출 마감일을 각각 미리 설정해둘 수 있습니다. 마감일이 지나면 학생은 더 이상 입력·수정·제출할 수 없습니다 (담임/관리자는 계속 열람 가능). 실제로 학생들이 입력하게 되는 "현재 취합 학기"는 [학기 설정] 메뉴에서 전환합니다.</div>

      <form method="get" class="field-row" style="align-items:flex-end; margin:14px 0;">
        <div class="field" style="max-width:160px;"><label>학년도</label>
          <input type="number" name="school_year" value="<?= h($schoolYear) ?>">
        </div>
        <div class="field"><button type="submit" class="btn-outline" style="color:var(--navy); border-color:var(--line);">조회</button></div>
      </form>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

      <form method="post" class="form-box" style="max-width:520px;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="save_semester_deadlines">
        <input type="hidden" name="school_year" value="<?= h($schoolYear) ?>">

        <label><?= h($schoolYear) ?>학년도 1학기 마감일시 <?= $bySemester['1'] && $bySemester['1']['is_current'] ? '<span class="status-badge approved">현재 주기</span>' : '' ?></label>
        <input type="datetime-local" name="deadline_1"
               value="<?= $bySemester['1'] && $bySemester['1']['deadline_at'] ? date('Y-m-d\TH:i', strtotime($bySemester['1']['deadline_at'])) : '' ?>">

        <label style="margin-top:14px;"><?= h($schoolYear) ?>학년도 2학기 마감일시 <?= $bySemester['2'] && $bySemester['2']['is_current'] ? '<span class="status-badge approved">현재 주기</span>' : '' ?></label>
        <input type="datetime-local" name="deadline_2"
               value="<?= $bySemester['2'] && $bySemester['2']['deadline_at'] ? date('Y-m-d\TH:i', strtotime($bySemester['2']['deadline_at'])) : '' ?>">

        <button type="submit" class="btn-gold" style="margin-top:16px;">두 학기 마감일 저장</button>
        <div class="hint">비워두고 저장하면 해당 학기는 마감일 없이(제출 무제한) 처리됩니다.</div>
      </form>

      <table class="grid" style="margin-top:18px;">
        <thead><tr><th>학기</th><th>마감일시</th><th>상태</th><th>현재 취합 주기</th></tr></thead>
        <tbody>
          <?php foreach (['1','2'] as $sem): $r = $bySemester[$sem]; ?>
            <tr>
              <td><?= h($schoolYear) ?>학년도 <?= $sem ?>학기</td>
              <td><?= $r && $r['deadline_at'] ? h(date('Y-m-d H:i', strtotime($r['deadline_at']))) : '미설정' ?></td>
              <td><?= $r && $r['deadline_at'] ? (strtotime($r['deadline_at']) < time() ? '<span class="status-badge rejected">마감됨</span>' : '<span class="status-badge submitted">진행중</span>') : '-' ?></td>
              <td><?= $r && $r['is_current'] ? '✓' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body></html>
