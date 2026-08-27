<header class="top">
  <div style="display:flex; align-items:center; gap:14px;">
    <div class="emblem header-emblem"><span style="font-size:9px;">HANIL</span></div>
    <div>
      <div style="display:flex;align-items:center;gap:8px;"><span class="badge">기능장 <?= MIN_SCORE ?></span><h1>관리자 모드</h1></div>
      <div class="sub">전교 현황 · <?= isset($cycle) && $cycle ? h(cycle_label($cycle)) : '' ?></div>
    </div>
  </div>
  <div class="top-actions">
    <button class="btn-outline" onclick="location.href='dashboard.php'">전체 현황</button>
    <button class="btn-outline" onclick="location.href='users.php'">계정 관리</button>
    <button class="btn-outline" onclick="location.href='teacher_assign.php'">담임 배정</button>
    <button class="btn-outline" onclick="location.href='roster.php'">명단 관리</button>
    <button class="btn-outline" onclick="location.href='cycle_settings.php'">학기 설정</button>
    <button class="btn-outline" onclick="location.href='semester_deadlines.php'">학기별 마감일</button>
    <button class="btn-outline" onclick="location.href='ip_settings.php'">접속 IP</button>
    <button class="btn-outline" onclick="location.href='../logout.php'">로그아웃</button>
  </div>
</header>
