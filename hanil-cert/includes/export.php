<?php
/**
 * includes/export.php
 * 별도 라이브러리 없이 "HTML 테이블을 .xls 확장자로 저장" 방식을 사용합니다.
 * Excel에서 더블클릭하면 정상적으로 열리며, 병합 셀(colspan)도 그대로 반영됩니다.
 */
require_once __DIR__ . '/scoring.php';

function xls_header(string $filename): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style>
        table{border-collapse:collapse;} td,th{border:1px solid #999; padding:4px 6px; font-size:11px; text-align:center; white-space:pre-line;}
        th{background:#f1f0ea; font-weight:bold;} .title{font-size:14px; font-weight:bold;} .left{text-align:left;}
    </style></head><body>';
}
function xls_footer(): void { echo '</body></html>'; }

function current_cycle_label(): string {
    $c = get_current_cycle();
    return $c ? cycle_label($c) : '';
}

/** 학교 공식 "기능장 대상자" 점수합계표 (반 단위) */
function render_official_xls(array $students, int $grade, int $ban, ?string $teacherName, string $filename): void {
    $cols = template_columns();
    xls_header($filename);
    $colCount = 2 + count($cols) + 1;
    echo '<table>';
    echo '<tr><td class="title" colspan="' . $colCount . '">' . h(current_cycle_label()) . ' 기능장 대상자</td></tr>';
    echo '<tr><td colspan="' . $colCount . '">' . h("{$grade}학년 " . str_pad((string)$ban, 2, '0', STR_PAD_LEFT) . '반  담임 ' . ($teacherName ?: '미상')) . '</td></tr>';
    echo '<tr><th>학번</th><th>이름</th>';
    foreach ($cols as $c) echo '<th>' . h($c['label']) . '</th>';
    echo '<th>점수</th></tr>';
    echo '<tr><td></td><td></td>';
    foreach ($cols as $c) echo '<td>' . $c['pts'] . '</td>';
    echo '<td></td></tr>';
    foreach ($students as $s) {
        $counts = $s['counts'] ?? [];
        $sid = sprintf('%d%02d%02d', $grade, $ban, $s['no']);
        echo '<tr><td>' . h($sid) . '</td><td class="left">' . h($s['name']) . '</td>';
        foreach ($cols as $c) {
            $sum = 0; foreach ($c['ids'] as $id) $sum += ($counts[$id] ?? 0);
            echo '<td>' . ($sum ?: '') . '</td>';
        }
        $ev = eval_eligibility(['counts'=>$counts, 'past_winner'=>$s['past_winner'] ?? 0, 'national'=>$s['national'] ?? 0, 'gyeongnam'=>$s['gyeongnam'] ?? null]);
        echo '<td>' . $ev['score'] . '</td></tr>';
    }
    echo '</table>';
    echo '<p style="font-size:10px;color:#666;">1급과 2급이 모두 있는 경우 상위 급수(1급)의 점수만 반영됩니다. ERP는 생산/회계/물류/인사를 각각 1개로 계산합니다. 생성일시: ' . date('Y-m-d H:i') . '</p>';
    xls_footer();
}

/** 자격증 취득 현황표 (전수조사, 반 단위) */
function render_survey_xls(array $students, int $grade, int $ban, ?string $teacherName, string $filename): void {
    $cols = survey_columns();
    xls_header($filename);
    $colCount = 2 + count($cols);
    echo '<table>';
    echo '<tr><td class="title" colspan="' . $colCount . '">' . h(current_cycle_label()) . ' 자격증 취득 현황 (' . date('Y-m-d') . ' 기준)</td></tr>';
    echo '<tr><td colspan="' . $colCount . '">' . h("{$grade}학년 " . str_pad((string)$ban, 2, '0', STR_PAD_LEFT) . '반   담임 : ' . ($teacherName ?: '미상')) . '</td></tr>';

    echo '<tr><th></th><th></th>';
    $i = 0; $n = count($cols);
    while ($i < $n) {
        $j = $i; while ($j+1 < $n && $cols[$j+1]['group'] === $cols[$i]['group']) $j++;
        $span = $j - $i + 1;
        echo '<th' . ($span>1?' colspan="'.$span.'"':'') . '>' . h($cols[$i]['group']) . '</th>';
        $i = $j + 1;
    }
    echo '</tr>';
    echo '<tr><th>번호</th><th>이름</th>';
    foreach ($cols as $c) echo '<th>' . h($c['label']) . '</th>';
    echo '</tr>';

    $totals = array_fill(0, $n, 0);
    foreach ($students as $s) {
        $counts = $s['counts'] ?? [];
        $types = count_types($counts);
        echo '<tr><td>' . h($s['no']) . '</td><td class="left">' . h($s['name']) . '</td>';
        foreach ($cols as $idx => $c) {
            if (!empty($c['isText'])) { echo '<td class="left">' . h($s['other_cert_name'] ?? '') . '</td>'; continue; }
            if (empty($c['map'])) {
                $label = $c['label'];
                $val = ($label==='1종' && $types===1) || ($label==='2종' && $types===2) || ($label==='3종이상' && $types>=3) ? 1 : '';
                echo '<td>' . $val . '</td>';
                continue;
            }
            $sum = 0; foreach ($c['map'] as $id) $sum += ($counts[$id] ?? 0);
            if ($sum) $totals[$idx] += $sum;
            echo '<td>' . ($sum ?: '') . '</td>';
        }
        echo '</tr>';
    }
    echo '<tr><th>합계</th><th></th>';
    foreach ($cols as $idx => $c) echo '<td>' . (empty($c['isText']) && !empty($c['map']) ? ($totals[$idx] ?: '') : '') . '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p style="font-size:10px;color:#666;">필기만 합격/GTQi/ITQ C등급/IT PLUS 레벨별/ERP1급 모듈별/전산세무/기타자격증명은 시스템에 개별 수집되지 않아 빈 칸입니다. 생성일시: ' . date('Y-m-d H:i') . '</p>';
    xls_footer();
}
