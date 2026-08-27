<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = bootstrap_page('admin');
$cycle = get_current_cycle();
$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    if ($action === 'add') {
        $ip = trim($_POST['ip_address'] ?? ''); $desc = trim($_POST['description'] ?? '');
        if ($ip !== '') { $db->prepare("INSERT INTO allowed_ips (ip_address, description) VALUES (?,?)")->execute([$ip, $desc]); $msg = 'IP가 추가되었습니다.'; }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE allowed_ips SET is_active = NOT is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = '상태가 변경되었습니다.';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM allowed_ips WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = '삭제되었습니다.';
    }
}
$ips = $db->query("SELECT * FROM allowed_ips ORDER BY id DESC")->fetchAll();
$myIp = get_client_ip();
?>
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>접속 IP 관리 - <?= h(SITE_NAME) ?></title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main>
  <div class="panel"><div class="panel-body">
    <h2 style="color:var(--navy); margin-top:0;">접속 허용 IP 관리</h2>
    <p class="hint">현재 접속 중인 IP: <strong><?= h($myIp) ?></strong> (이 IP를 그대로 등록하면 됩니다)</p>
    <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

    <form method="post" class="form-box" style="max-width:460px;">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="add">
      <label>IP 주소 또는 대역</label>
      <input type="text" name="ip_address" placeholder="예: 210.100.50.10 / 210.100.50.0/24 / 210.100.50." required>
      <label>설명</label><input type="text" name="description" placeholder="예: 상업관 컴퓨터실">
      <button type="submit" class="btn-blue" style="margin-top:10px;">추가</button>
    </form>

    <table class="grid" style="margin-top:14px;">
      <thead><tr><th>IP/대역</th><th>설명</th><th>상태</th><th>등록일</th><th>관리</th></tr></thead>
      <tbody>
      <?php if (!$ips): ?><tr><td colspan="5" class="empty-state">등록된 IP가 없습니다. (config.php의 SCHOOL_FIXED_IPS만 적용 중)</td></tr><?php endif; ?>
      <?php foreach ($ips as $ip): ?>
        <tr><td><?= h($ip['ip_address']) ?></td><td><?= h($ip['description']) ?></td><td><?= $ip['is_active']?'활성':'비활성' ?></td><td><?= h($ip['created_at']) ?></td>
          <td>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="id" value="<?= $ip['id'] ?>"><button type="submit" class="chip-btn">토글</button></form>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= $ip['id'] ?>"><button type="submit" class="chip-btn" onclick="return confirm('삭제할까요?')">삭제</button></form>
          </td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint">⚠️ config/config.php의 <code>SCHOOL_FIXED_IPS</code>에 학교 고정 공인 IP를 등록해두면 DB 장애 시에도 최소한의 접속 제한이 유지됩니다.</p>
  </div></div>
</main>
</body></html>
