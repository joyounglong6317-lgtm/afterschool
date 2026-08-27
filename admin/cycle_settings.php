<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    if ($action === 'save_deadline') {
        $cycleId = (int)$_POST['cycle_id'];
        $val = $_POST['deadline'] ?: null;
        $stmt = $db->prepare("UPDATE cycles SET deadline_at=? WHERE id=?");
        $stmt->execute([$val ? date('Y-m-d H:i:s', strtotime($val)) : null, $cycleId]);
        $msg = '마감일이 저장되었습니다.';
    } elseif ($action === 'clear_deadline') {
        $cycleId = (int)$_POST['cycle_id'];
        $db->prepare("UPDATE cycles SET deadline_at=NULL WHERE id=?")->execute([$cycleId]);
        $msg = '마감일이 해제되었습니다.';
    } elseif ($action === 'ban_counts') {
        foreach ([1,2,3] as $g) {
            $c = max(1, (int)($_POST["ban_$g"] ?? 10));
            $stmt = $db->prepare("INSERT INTO ban_counts (grade, class_count) VALUES (?,?) ON DUPLICATE KEY UPDATE class_count=VALUES(class_count)");
            $stmt->execute([$g, $c]);
        }
        $msg = '학년별 반 개수가 저장되었습니다.';
    } elseif ($action === 'new_cycle') {
        $year = (int)$_POST['school_year']; $sem = $_POST['semester'] === '2' ? '2' : '1';
        $stmt = $db->prepare("INSERT INTO cycles (school_year, semester, is_current) VALUES (?,?,0)
                               ON DUPLICATE KEY UPDATE id=id");
        $stmt->execute([$year, $sem]);
        $db->exec("UPDATE cycles SET is_current=0");
        $db->prepare("UPDATE cycles SET is_current=1 WHERE school_year=? AND semester=?")->execute([$year, $sem]);
        $msg = "{$year}학년도 {$sem}학기 취합 주기로 전환되었습니다. (학생들은 이 학기에 새로 입력하게 됩니다)";
    } elseif ($action === 'switch_cycle') {
        $cycleId = (int)$_POST['cycle_id'];
        $db->exec("UPDATE cycles SET is_current=0");
        $db->prepare("UPDATE cycles SET is_current=1 WHERE id=?")->execute([$cycleId]);
        $msg = '현재 취합 주기가 변경되었습니다.';
    }
}

$cycle = get_current_cycle();
$allCycles = $db->query("SELECT * FROM cycles ORDER BY school_year DESC, semester DESC")->fetchAll();
$banCounts = get_ban_counts();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>학기 설정 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2>현재 취합 주기</h2></div>
    <div class="panel-body">
      <?php if ($cycle): ?>
        <p style="font-size:15px; font-weight:700; color:var(--navy);"><?= h(cycle_label($cycle)) ?></p>
        <form method="post" class="field-row" style="align-items:flex-end;">
          <?= csrf_field() ?>
          <input type="hidden" name="form_action" value="save_deadline">
          <input type="hidden" name="cycle_id" value="<?= $cycle['id'] ?>">
          <div class="field"><label>제출 마감일시</label>
            <input type="datetime-local" name="deadline" value="<?= $cycle['deadline_at'] ? date('Y-m-d\TH:i', strtotime($cycle['deadline_at'])) : '' ?>">
          </div>
          <div class="field"><button type="submit" class="btn-blue">마감일 저장</button></div>
        </form>
        <form method="post" style="margin-top:6px;" onsubmit="return confirm('마감일 설정을 해제할까요?')">
          <?= csrf_field() ?><input type="hidden" name="form_action" value="clear_deadline"><input type="hidden" name="cycle_id" value="<?= $cycle['id'] ?>">
          <button type="submit" class="chip-btn">마감일 해제</button>
        </form>
        <p class="hint"><?= $cycle['deadline_at'] ? '현재 마감: ' . h(format_deadline($cycle)) . (is_past_deadline($cycle)?' (이미 지남)':'') : '마감일 미설정 (제출 잠금 없음)' ?></p>
      <?php else: ?>
        <p>취합 주기가 없습니다. 아래에서 새로 생성해주세요.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>새 학기 취합 시작 / 주기 전환</h2></div>
    <div class="panel-body">
      <div class="hint">새 학기를 시작하면 학생들은 해당 학기에 새로 자격증을 입력하게 됩니다 (이전 학기 데이터는 그대로 보존됩니다).</div>
      <form method="post" class="field-row" style="align-items:flex-end; margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="new_cycle">
        <div class="field" style="max-width:140px;"><label>학년도</label><input type="number" name="school_year" value="<?= date('Y') ?>" required></div>
        <div class="field" style="max-width:140px;"><label>학기</label><select name="semester"><option value="1">1학기</option><option value="2">2학기</option></select></div>
        <div class="field"><button type="submit" class="btn-gold" onclick="return confirm('새 취합 주기로 전환할까요?')">새 학기 시작</button></div>
      </form>

      <table class="grid" style="margin-top:14px;">
        <thead><tr><th>학년도</th><th>학기</th><th>현재 주기</th><th>마감일</th><th>전환</th></tr></thead>
        <tbody>
        <?php foreach ($allCycles as $c): ?>
          <tr>
            <td><?= h($c['school_year']) ?></td><td><?= h($c['semester']) ?>학기</td>
            <td><?= $c['is_current'] ? '✓ 현재' : '' ?></td>
            <td><?= $c['deadline_at'] ? h(date('Y-m-d H:i', strtotime($c['deadline_at']))) : '-' ?></td>
            <td>
              <?php if (!$c['is_current']): ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="switch_cycle"><input type="hidden" name="cycle_id" value="<?= $c['id'] ?>">
                <button type="submit" class="chip-btn" onclick="return confirm('이 주기로 전환할까요?')">전환</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>학년별 반 개수</h2></div>
    <div class="panel-body">
      <form method="post" class="field-row" style="align-items:flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="ban_counts">
        <div class="field" style="max-width:110px;"><label>1학년 반 수</label><input type="number" name="ban_1" min="1" max="30" value="<?= $banCounts[1] ?>"></div>
        <div class="field" style="max-width:110px;"><label>2학년 반 수</label><input type="number" name="ban_2" min="1" max="30" value="<?= $banCounts[2] ?>"></div>
        <div class="field" style="max-width:110px;"><label>3학년 반 수</label><input type="number" name="ban_3" min="1" max="30" value="<?= $banCounts[3] ?>"></div>
        <div class="field"><button type="submit" class="btn-blue">저장</button></div>
      </form>
    </div>
  </div>
</main>
</body></html>
