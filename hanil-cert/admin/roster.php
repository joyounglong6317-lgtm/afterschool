<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['form_action'] ?? '') === 'upload' && !empty($_FILES['roster_csv']['tmp_name'])) {
        $fh = fopen($_FILES['roster_csv']['tmp_name'], 'r');
        if ($fh) {
            $count = 0;
            $stmt = $db->prepare("INSERT INTO roster (grade, class_no, student_no, name) VALUES (?,?,?,?)
                                   ON DUPLICATE KEY UPDATE name=VALUES(name)");
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) < 4) continue;
                [$grade, $classNo, $no, $name] = $row;
                if (!is_numeric($grade) || !is_numeric($classNo) || !is_numeric($no) || trim($name) === '') continue;
                $stmt->execute([(int)$grade, (int)$classNo, (int)$no, trim($name)]);
                $count++;
            }
            fclose($fh);
            $msg = "{$count}명 업로드(갱신) 완료";
        }
    } elseif (($_POST['form_action'] ?? '') === 'clear') {
        $db->exec("TRUNCATE TABLE roster");
        $msg = '명단이 전체 삭제되었습니다.';
    }
}

$roster = $db->query("SELECT * FROM roster ORDER BY grade, class_no, student_no")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>명단 관리 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <div class="panel">
    <div class="panel-head"><h2>전교 학생 명단 업로드</h2></div>
    <div class="panel-body">
      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <div class="hint">CSV 파일 (헤더 없이) 열 순서: <b>학년, 반, 번호, 이름</b>. 엑셀에서 "다른 이름으로 저장 → CSV"로 변환해 업로드하세요.
        학생 회원가입 시 이름 대조 및 대시보드의 "미가입자" 파악에 사용됩니다.</div>
      <form method="post" enctype="multipart/form-data" style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="upload">
        <input type="file" name="roster_csv" accept=".csv" required>
        <button type="submit" class="btn-blue">업로드</button>
      </form>
      <form method="post" style="margin-top:10px;" onsubmit="return confirm('전체 명단을 삭제할까요?')">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="clear">
        <button type="submit" class="chip-btn" style="color:var(--red);border-color:var(--red);">명단 전체 삭제</button>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>업로드된 명단 (<?= count($roster) ?>명)</h2></div>
    <div class="panel-body">
      <div class="scroll-x" style="max-height:50vh;">
        <table class="grid"><thead><tr><th>학년</th><th>반</th><th>번호</th><th>이름</th></tr></thead>
        <tbody>
        <?php if (!$roster): ?><tr><td colspan="4" class="empty-state">업로드된 명단이 없습니다.</td></tr><?php endif; ?>
        <?php foreach ($roster as $r): ?><tr><td><?= h($r['grade']) ?></td><td><?= h($r['class_no']) ?></td><td><?= h($r['student_no']) ?></td><td><?= h($r['name']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</main>
</body></html>
