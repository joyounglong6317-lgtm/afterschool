<?php
/**
 * includes/scoring.php
 * 원본 "기능장 자격증 취합·판별 시스템"의 배점표/판별 로직을 그대로 이식했습니다.
 * 배점표를 수정해야 할 경우 이 파일의 CERT_ITEMS / TRACKING_ITEMS / COMPETITION_ITEMS 만 고치면 됩니다.
 */

const CERT_ITEMS = [
    ['id'=>'c1',  'name'=>'컴퓨터활용능력 2급(필수)', 'pts'=>5, 'grp'=>'필수'],
    ['id'=>'c2',  'name'=>'전산회계 2급(필수)',       'pts'=>5, 'grp'=>'필수'],
    ['id'=>'c3',  'name'=>'컴퓨터활용능력 1급',       'pts'=>6, 'grp'=>'IT/사무'],
    ['id'=>'c4',  'name'=>'전산회계 1급',             'pts'=>6, 'grp'=>'회계/세무'],
    ['id'=>'c5',  'name'=>'워드프로세서',             'pts'=>5, 'grp'=>'IT/사무'],
    ['id'=>'c6',  'name'=>'전산회계운용사 2급',       'pts'=>8, 'grp'=>'회계/세무'],
    ['id'=>'c7',  'name'=>'전산회계운용사 3급',       'pts'=>5, 'grp'=>'회계/세무'],
    ['id'=>'c8',  'name'=>'전자상거래관리사 2급',     'pts'=>5, 'grp'=>'IT/사무'],
    ['id'=>'c9',  'name'=>'전자상거래운용사',         'pts'=>4, 'grp'=>'IT/사무'],
    ['id'=>'itplus5','name'=>'기타 국가기술자격 IT+ (레벨5)','pts'=>4,'grp'=>'IT/사무','typeGroup'=>'itplus'],
    ['id'=>'itplus4','name'=>'기타 국가기술자격 IT+ (레벨4)','pts'=>4,'grp'=>'IT/사무','typeGroup'=>'itplus'],
    ['id'=>'itplus3','name'=>'기타 국가기술자격 IT+ (레벨3)','pts'=>4,'grp'=>'IT/사무','typeGroup'=>'itplus'],
    ['id'=>'itplus2','name'=>'기타 국가기술자격 IT+ (레벨2)','pts'=>4,'grp'=>'IT/사무','typeGroup'=>'itplus'],
    ['id'=>'itplus1','name'=>'기타 국가기술자격 IT+ (레벨1)','pts'=>4,'grp'=>'IT/사무','typeGroup'=>'itplus'],
    ['id'=>'c11', 'name'=>'멀티미디어콘텐츠제작전문가', 'pts'=>6, 'grp'=>'IT/사무'],
    ['id'=>'c12', 'name'=>'정보처리기능사',           'pts'=>8, 'grp'=>'IT/사무'],
    ['id'=>'c13', 'name'=>'정보기기운용기능사',       'pts'=>8, 'grp'=>'IT/사무'],
    ['id'=>'c14', 'name'=>'컴퓨터그래픽운용기능사',   'pts'=>8, 'grp'=>'디자인'],
    ['id'=>'c15', 'name'=>'전자계산기기능사',         'pts'=>8, 'grp'=>'IT/사무'],
    ['id'=>'c16', 'name'=>'전산응용기계제도기능사',   'pts'=>10,'grp'=>'IT/사무'],
    ['id'=>'c17', 'name'=>'기타 IT관련 국가기술 기능사','pts'=>6,'grp'=>'IT/사무'],
    ['id'=>'c18', 'name'=>'ITQ A(1)등급(과목당)',     'pts'=>2, 'grp'=>'IT/사무', 'multi'=>true],
    ['id'=>'c19', 'name'=>'ITQ B(2)등급(과목당)',     'pts'=>1, 'grp'=>'IT/사무', 'multi'=>true],
    ['id'=>'c20', 'name'=>'GTQ(포토샵) 1급',          'pts'=>4, 'grp'=>'디자인'],
    ['id'=>'c21', 'name'=>'GTQ(일러스트) 1급',        'pts'=>6, 'grp'=>'디자인'],
    ['id'=>'c22', 'name'=>'GTQ(포토샵) 2급',          'pts'=>3, 'grp'=>'디자인'],
    ['id'=>'c23', 'name'=>'GTQ(일러스트) 2급',        'pts'=>5, 'grp'=>'디자인'],
    ['id'=>'c24', 'name'=>'급수 있는 자격증 A(1)등급', 'pts'=>2, 'grp'=>'기타', 'multi'=>true],
    ['id'=>'c25', 'name'=>'급수 있는 자격증 B(2)등급', 'pts'=>1, 'grp'=>'기타', 'multi'=>true],
    ['id'=>'c26', 'name'=>'세무회계 2급',             'pts'=>8, 'grp'=>'회계/세무'],
    ['id'=>'c27', 'name'=>'전산세무2급(세무회계3급)','pts'=>7,'grp'=>'회계/세무'],
    ['id'=>'erp2_hr',  'name'=>'ERP 2급 (인사)', 'pts'=>4, 'grp'=>'회계/세무'],
    ['id'=>'erp2_acc', 'name'=>'ERP 2급 (회계)', 'pts'=>4, 'grp'=>'회계/세무'],
    ['id'=>'erp2_prod','name'=>'ERP 2급 (생산)', 'pts'=>4, 'grp'=>'회계/세무'],
    ['id'=>'erp2_log', 'name'=>'ERP 2급 (물류)', 'pts'=>4, 'grp'=>'회계/세무'],
    ['id'=>'c29', 'name'=>'ATC 1급',                  'pts'=>5, 'grp'=>'금융'],
    ['id'=>'c30', 'name'=>'ATC 2급',                  'pts'=>4, 'grp'=>'금융'],
    ['id'=>'c31', 'name'=>'펀드투자권유대행인',       'pts'=>8, 'grp'=>'금융'],
    ['id'=>'c32', 'name'=>'증권투자권유대행인',       'pts'=>8, 'grp'=>'금융'],
    ['id'=>'c33', 'name'=>'급수 없는 자격증',         'pts'=>1, 'grp'=>'기타'],
];

const TRACKING_ITEMS = [
    ['id'=>'pil_word', 'name'=>'[필기만 합격] 워드',      'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'pil_comp1','name'=>'[필기만 합격] 컴활 1급',  'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'pil_comp2','name'=>'[필기만 합격] 컴활 2급',  'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'pil_info', 'name'=>'[필기만 합격] 정보처리',  'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'gtqi1', 'name'=>'GTQi 1급', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'gtqi2', 'name'=>'GTQi 2급', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'itq_c', 'name'=>'ITQ C등급(과목당)', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true, 'multi'=>true],
    ['id'=>'item_max', 'name'=>'MAX', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
    ['id'=>'erp1_hr',  'name'=>'ERP 1급 (인사)', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true, 'typeGroup'=>'erp1'],
    ['id'=>'erp1_acc', 'name'=>'ERP 1급 (회계)', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true, 'typeGroup'=>'erp1'],
    ['id'=>'erp1_prod','name'=>'ERP 1급 (생산)', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true, 'typeGroup'=>'erp1'],
    ['id'=>'erp1_log', 'name'=>'ERP 1급 (물류)', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true, 'typeGroup'=>'erp1'],
    ['id'=>'jsm1', 'name'=>'전산세무 1급', 'pts'=>0, 'grp'=>'참고용(0점·기능장 미반영)', 'tracking'=>true],
];

const COMPETITION_ITEMS = [
    ['id'=>'k34', 'name'=>'지방기능경기대회(1~3위)', 'pts'=>30, 'grp'=>'대회입상'],
    ['id'=>'k35', 'name'=>'지방기능경기대회(4위)',   'pts'=>15, 'grp'=>'대회입상'],
    ['id'=>'k36', 'name'=>'지방상업경진대회 금상',   'pts'=>10, 'grp'=>'대회입상'],
    ['id'=>'k37', 'name'=>'지방상업경진대회 은상',   'pts'=>7,  'grp'=>'대회입상'],
    ['id'=>'k38', 'name'=>'지방상업경진대회 동상',   'pts'=>5,  'grp'=>'대회입상'],
    ['id'=>'k39', 'name'=>'지방상업경진대회 장려',   'pts'=>3,  'grp'=>'대회입상'],
    ['id'=>'k40', 'name'=>'각종 경진대회 대상(1위)', 'pts'=>5,  'grp'=>'대회입상', 'multi'=>true],
    ['id'=>'k41', 'name'=>'각종 경진대회 금상(2위)', 'pts'=>4,  'grp'=>'대회입상', 'multi'=>true],
    ['id'=>'k42', 'name'=>'각종 경진대회 은상(3위)', 'pts'=>3,  'grp'=>'대회입상', 'multi'=>true],
    ['id'=>'k43', 'name'=>'각종 경진대회 동상(4위)', 'pts'=>2,  'grp'=>'대회입상', 'multi'=>true],
];

function all_items(): array { return array_merge(CERT_ITEMS, TRACKING_ITEMS, COMPETITION_ITEMS); }

function item_by_id(string $id): ?array {
    foreach (all_items() as $it) { if ($it['id'] === $id) return $it; }
    return null;
}

// 1급/2급 등 상하위 급수가 모두 있으면 상위 급수만 인정
const TIER_PAIRS = [
    ['higher'=>'c3',  'lower'=>'c1'],
    ['higher'=>'c4',  'lower'=>'c2'],
    ['higher'=>'c20', 'lower'=>'c22'],
    ['higher'=>'c21', 'lower'=>'c23'],
    ['higher'=>'c24', 'lower'=>'c25'],
];

function get_effective_counts(array $counts): array {
    $eff = $counts;
    foreach (TIER_PAIRS as $p) {
        if (($eff[$p['higher']] ?? 0) > 0 && ($eff[$p['lower']] ?? 0) > 0) {
            $eff[$p['lower']] = 0;
        }
    }
    return $eff;
}

function compute_score(array $counts): int {
    $eff = get_effective_counts($counts);
    $total = 0;
    foreach (all_items() as $it) { $total += ($eff[$it['id']] ?? 0) * $it['pts']; }
    return $total;
}

function count_types(array $counts): int {
    $eff = get_effective_counts($counts);
    $groups = [];
    foreach (all_items() as $it) {
        if (!empty($it['tracking'])) continue;
        if (($eff[$it['id']] ?? 0) > 0) $groups[$it['typeGroup'] ?? $it['id']] = true;
    }
    return count($groups);
}

function has_required(array $counts): bool {
    $a = ($counts['c1'] ?? 0) > 0 || ($counts['c3'] ?? 0) > 0;
    $b = ($counts['c2'] ?? 0) > 0 || ($counts['c4'] ?? 0) > 0;
    return $a && $b;
}

/* ============================================================
 * 취득일자 기반 재학중 취득 판정
 * counts_json 저장 형식: { "item_id": {"n": 개수, "d": "YYYY-MM-DD"}, ... }
 * 필수 2종(컴활2급/1급, 전산회계2급/1급)은 재학 이전 취득이어도 항상 인정됩니다.
 * ============================================================ */
const REQUIRED_ITEM_IDS = ['c1', 'c2', 'c3', 'c4'];

/** 세부 명칭을 학생이 직접 입력해야 하는 항목들 (급수/과목이 통칭이라 구체적 명칭 파악이 필요) */
const NOTE_ITEM_IDS = ['c18', 'c19', 'c24', 'c25', 'c33', 'itq_c', 'k40', 'k41', 'k42', 'k43'];

/** counts_json(raw) 문자열을 {item_id: ['n'=>개수, 'd'=>취득일|null, 'note'=>세부명칭|null]} 배열로 변환 (구버전 단순 정수 저장 형식도 호환) */
function decode_counts_json(?string $json): array {
    $data = $json ? json_decode($json, true) : [];
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $id => $entry) {
        if (is_array($entry)) {
            $out[$id] = ['n' => (int)($entry['n'] ?? 0), 'd' => $entry['d'] ?? null, 'note' => $entry['note'] ?? null];
        } else {
            // 구버전(단순 개수만 저장) 호환
            $out[$id] = ['n' => (int)$entry, 'd' => null, 'note' => null];
        }
    }
    return $out;
}

/** 재학중 취득 판정을 적용해 단순 {item_id: 개수} 배열로 변환 (점수 계산에 바로 사용) */
function apply_enrollment_filter(array $decoded, ?string $enrollmentDate): array {
    $counts = [];
    foreach ($decoded as $id => $entry) {
        $n = (int)($entry['n'] ?? 0);
        $date = $entry['d'] ?? null;
        if ($n <= 0) { $counts[$id] = 0; continue; }
        $isRequired = in_array($id, REQUIRED_ITEM_IDS, true);
        if (!$isRequired && $enrollmentDate && $date && $date < $enrollmentDate) {
            $counts[$id] = 0; // 재학 전 취득으로 제외 (필수 2종은 예외)
        } else {
            $counts[$id] = $n;
        }
    }
    return $counts;
}

/** 재학 전 취득으로 제외된 항목 목록 (담임 검토 화면 표시용) */
function get_enrollment_excluded(array $decoded, ?string $enrollmentDate): array {
    $excluded = [];
    if (!$enrollmentDate) return $excluded;
    foreach ($decoded as $id => $entry) {
        $n = (int)($entry['n'] ?? 0);
        $date = $entry['d'] ?? null;
        if ($n > 0 && $date && $date < $enrollmentDate && !in_array($id, REQUIRED_ITEM_IDS, true)) {
            $excluded[$id] = $date;
        }
    }
    return $excluded;
}

/** counts_json 원본 + 학생 입학일로부터, 점수계산에 바로 쓸 수 있는 필터링된 counts 배열을 얻는 헬퍼 */
function get_scored_counts(?string $countsJson, ?string $enrollmentDate): array {
    return apply_enrollment_filter(decode_counts_json($countsJson), $enrollmentDate);
}

/** $submission: ['counts'=>array, 'past_winner'=>bool, 'national'=>bool, 'gyeongnam'=>int|null] */
function eval_eligibility(array $submission): array {
    $counts = $submission['counts'] ?? [];
    $score = compute_score($counts);
    if (!empty($submission['past_winner'])) return ['eligible'=>false, 'reason'=>'이전 수상자', 'score'=>$score];
    if (!empty($submission['national'])) return ['eligible'=>true, 'reason'=>'전국기능경기대회 참가(자동선발)', 'score'=>$score];
    $gyeongnam = $submission['gyeongnam'] ?? null;
    if ($gyeongnam && $gyeongnam <= 4 && $score >= MIN_SCORE) {
        return ['eligible'=>true, 'reason'=>"경남기능경기대회 {$gyeongnam}위(필수2종 면제)", 'score'=>$score];
    }
    if (has_required($counts) && $score >= MIN_SCORE) {
        return ['eligible'=>true, 'reason'=>'기준 충족', 'score'=>$score];
    }
    if (!has_required($counts)) $reason = '필수2종 미보유';
    else $reason = '점수 미달';
    return ['eligible'=>false, 'reason'=>$reason, 'score'=>$score];
}

/** 종수를 구성하는 세부 내역 (typeGroup으로 묶인 항목은 하나로 표시) */
function get_held_types_detail(array $counts): array {
    $eff = get_effective_counts($counts);
    $labels = ['itplus'=>'기타 국가기술자격 IT+ (레벨 무관 1종)'];
    $groups = [];
    foreach (all_items() as $it) {
        if (!empty($it['tracking'])) continue;
        if (($eff[$it['id']] ?? 0) > 0) {
            $key = $it['typeGroup'] ?? $it['id'];
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $it['typeGroup'] ?? null ? $labels[$it['typeGroup']] : $it['name'], 'items' => []];
            }
            $groups[$key]['items'][] = $it['name'];
        }
    }
    return array_values($groups);
}

/** 학교 공식 "기능장 대상자" 제출 양식 컬럼 매핑 */
function template_columns(): array {
    return [
        ['label'=>'컴퓨터활용능력 1급', 'ids'=>['c3'], 'pts'=>6],
        ['label'=>'컴퓨터활용능력 2급', 'ids'=>['c1'], 'pts'=>5],
        ['label'=>'전산회계 1급', 'ids'=>['c4'], 'pts'=>6],
        ['label'=>'전산회계 2급', 'ids'=>['c2'], 'pts'=>5],
        ['label'=>'워드프로세서', 'ids'=>['c5'], 'pts'=>5],
        ['label'=>'전산회계운용사 2급(세무회계 2급)', 'ids'=>['c6','c26'], 'pts'=>8],
        ['label'=>'전산회계운용사 3급', 'ids'=>['c7'], 'pts'=>5],
        ['label'=>'전자상거래관리사 2급', 'ids'=>['c8'], 'pts'=>5],
        ['label'=>'전자상거래운용사', 'ids'=>['c9'], 'pts'=>4],
        ['label'=>'멀티미디어콘텐츠제작전문가', 'ids'=>['c11'], 'pts'=>6],
        ['label'=>'기타 국가기술 자격증(IT+ level1~5)', 'ids'=>['itplus5','itplus4','itplus3','itplus2','itplus1'], 'pts'=>4],
        ['label'=>'정보처리기능사', 'ids'=>['c12'], 'pts'=>8],
        ['label'=>'정보기기운용기능사', 'ids'=>['c13'], 'pts'=>8],
        ['label'=>'컴퓨터그래픽운용기능사', 'ids'=>['c14'], 'pts'=>8],
        ['label'=>'전자계산기기능사', 'ids'=>['c15'], 'pts'=>8],
        ['label'=>'전산응용기계제도기능사', 'ids'=>['c16'], 'pts'=>10],
        ['label'=>'기타 IT관련 국가기술 기능사', 'ids'=>['c17'], 'pts'=>6],
        ['label'=>'ITQ A등급 과목수', 'ids'=>['c18'], 'pts'=>2],
        ['label'=>'GTQ(포토샵) 1급', 'ids'=>['c20'], 'pts'=>4],
        ['label'=>'GTQ(일러스트) 1급', 'ids'=>['c21'], 'pts'=>6],
        ['label'=>'ITQ B등급 과목수', 'ids'=>['c19'], 'pts'=>1],
        ['label'=>'GTQ(포토샵) 2급', 'ids'=>['c22'], 'pts'=>3],
        ['label'=>'GTQ(일러스트) 2급', 'ids'=>['c23'], 'pts'=>5],
        ['label'=>'급수 있는 자격증 A(1)등급', 'ids'=>['c24'], 'pts'=>2],
        ['label'=>'급수 있는 자격증 B(2)등급', 'ids'=>['c25'], 'pts'=>1],
        ['label'=>'전산세무2급(세무회계3급)', 'ids'=>['c27'], 'pts'=>7],
        ['label'=>'ERP(생산,회계,물류,인사) 2급', 'ids'=>['erp2_hr','erp2_acc','erp2_prod','erp2_log'], 'pts'=>4],
        ['label'=>'ATC 1급', 'ids'=>['c29'], 'pts'=>5],
        ['label'=>'ATC 2급', 'ids'=>['c30'], 'pts'=>4],
        ['label'=>'펀드투자권유대행인', 'ids'=>['c31'], 'pts'=>8],
        ['label'=>'증권투자권유대행인', 'ids'=>['c32'], 'pts'=>8],
        ['label'=>'급수 없는 자격증', 'ids'=>['c33'], 'pts'=>1],
        ['label'=>'지방기능경기대회(1~3위)', 'ids'=>['k34'], 'pts'=>30],
        ['label'=>'지방기능경기대회(4위)', 'ids'=>['k35'], 'pts'=>15],
        ['label'=>'각종 경진대회 대상(1위)', 'ids'=>['k40'], 'pts'=>5],
        ['label'=>'각종 경진대회 금상(2위)', 'ids'=>['k41'], 'pts'=>4],
        ['label'=>'각종 경진대회 은상(3위)', 'ids'=>['k42'], 'pts'=>3],
        ['label'=>'각종 경진대회 동상(4위)', 'ids'=>['k43'], 'pts'=>2],
        ['label'=>'지방상업경진대회 금상', 'ids'=>['k36'], 'pts'=>10],
        ['label'=>'지방상업경진대회 은상', 'ids'=>['k37'], 'pts'=>7],
        ['label'=>'지방상업경진대회 동상', 'ids'=>['k38'], 'pts'=>5],
        ['label'=>'지방상업경진대회 장려', 'ids'=>['k39'], 'pts'=>3],
    ];
}

/** "자격증 취득 현황표"(전수조사) 양식 컬럼 매핑 */
function survey_columns(): array {
    return [
        ['group'=>'필기만 합격','label'=>'워드','map'=>['pil_word']],
        ['group'=>'필기만 합격','label'=>'컴활1급','map'=>['pil_comp1']],
        ['group'=>'필기만 합격','label'=>'컴활2급','map'=>['pil_comp2']],
        ['group'=>'필기만 합격','label'=>'정보처리','map'=>['pil_info']],
        ['group'=>'실기까지 합격','label'=>'워드','map'=>['c5']],
        ['group'=>'실기까지 합격','label'=>'컴활1급','map'=>['c3']],
        ['group'=>'실기까지 합격','label'=>'컴활2급','map'=>['c1']],
        ['group'=>'실기까지 합격','label'=>'정보처리기능사','map'=>['c12']],
        ['group'=>'실기까지 합격','label'=>'그래픽스기능사','map'=>['c14']],
        ['group'=>'실기까지 합격','label'=>'정보기기','map'=>['c13']],
        ['group'=>'실기까지 합격','label'=>'기계제도기능사','map'=>['c16']],
        ['group'=>'GTQi','label'=>'1급','map'=>['gtqi1']],
        ['group'=>'GTQi','label'=>'2급','map'=>['gtqi2']],
        ['group'=>'GTQ','label'=>'1급','map'=>['c20','c21']],
        ['group'=>'GTQ','label'=>'2급','map'=>['c22','c23']],
        ['group'=>'ITQ(개수)','label'=>'A등급','map'=>['c18']],
        ['group'=>'ITQ(개수)','label'=>'B등급','map'=>['c19']],
        ['group'=>'ITQ(개수)','label'=>'C등급','map'=>['itq_c']],
        ['group'=>'IT PLUS(레벨)','label'=>'5','map'=>['itplus5']],
        ['group'=>'IT PLUS(레벨)','label'=>'4','map'=>['itplus4']],
        ['group'=>'IT PLUS(레벨)','label'=>'3','map'=>['itplus3']],
        ['group'=>'IT PLUS(레벨)','label'=>'2','map'=>['itplus2']],
        ['group'=>'IT PLUS(레벨)','label'=>'1','map'=>['itplus1']],
        ['group'=>'ATC','label'=>'','map'=>['c29','c30']],
        ['group'=>'MAX','label'=>'','map'=>['item_max']],
        ['group'=>'회계관련자격증','label'=>'ERP1급(인사)','map'=>['erp1_hr']],
        ['group'=>'회계관련자격증','label'=>'ERP1급(회계)','map'=>['erp1_acc']],
        ['group'=>'회계관련자격증','label'=>'ERP1급(생산)','map'=>['erp1_prod']],
        ['group'=>'회계관련자격증','label'=>'ERP1급(물류)','map'=>['erp1_log']],
        ['group'=>'회계관련자격증','label'=>'ERP2급(인사)','map'=>['erp2_hr']],
        ['group'=>'회계관련자격증','label'=>'ERP2급(회계)','map'=>['erp2_acc']],
        ['group'=>'회계관련자격증','label'=>'ERP2급(생산)','map'=>['erp2_prod']],
        ['group'=>'회계관련자격증','label'=>'ERP2급(물류)','map'=>['erp2_log']],
        ['group'=>'회계관련자격증','label'=>'전산세무(1급)','map'=>['jsm1']],
        ['group'=>'회계관련자격증','label'=>'전산회계(1급)','map'=>['c4']],
        ['group'=>'회계관련자격증','label'=>'전산회계(2급)','map'=>['c2']],
        ['group'=>'회계관련자격증','label'=>'전산회계운용사2급','map'=>['c6']],
        ['group'=>'회계관련자격증','label'=>'전산회계운용사3급','map'=>['c7']],
        ['group'=>'기타자격증','label'=>'','isText'=>true],
        ['group'=>'개인별 취득개수','label'=>'1종'],
        ['group'=>'개인별 취득개수','label'=>'2종'],
        ['group'=>'개인별 취득개수','label'=>'3종이상'],
    ];
}

function cycle_label(array $cycle): string {
    return $cycle['school_year'] . '학년도 ' . $cycle['semester'] . '학기';
}
