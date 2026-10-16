<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

/**
 * Mrs. X sample answers extracted from Excel (same as verify_16pf_vs_excel.py).
 * Run: python scripts/verify_16pf_vs_excel.py first to dump if needed; this uses inline JSON file.
 */

$answersPath = __DIR__ . '/../storage/app/psychometric/16pf_excel_sample_answers.json';
if (!is_file($answersPath)) {
    fwrite(STDERR, "Missing {$answersPath}. Run: python scripts/dump_16pf_excel_sample.py\n");
    exit(1);
}

$answers = json_decode((string) file_get_contents($answersPath), true);
$expectedPath = __DIR__ . '/../storage/app/psychometric/16pf_excel_sample_expected.json';
$expected = json_decode((string) file_get_contents($expectedPath), true);

$engine = app(\App\Services\SalaryAdjustmentAssessmentEngine::class);
$questions = (new ReflectionClass($engine))->getMethod('pf16Questions');
$questions->setAccessible(true);
$questionList = $questions->invoke($engine);

$employee = (object) [
    'jenis_kelamin' => 'L',
    'pendidikan' => 'SMK',
];

$scorer = app(\App\Services\SixteenPfScoringService::class);
$result = $scorer->score($questionList, $answers, $employee);

$order = array_merge(['MD'], ['A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4']);
$rawBad = [];
$stenBad = [];

echo "Norm group (app): {$result['norm_group']}\n";
echo "Answered: {$result['answered']}/105\n\n";

foreach ($order as $fac) {
    $cr = (int) ($result['raw_scores'][$fac] ?? 0);
    $cs = (int) ($result['factors'][$fac]['sten'] ?? 0);
    $er = (int) ($expected[$fac]['raw'] ?? -1);
    $es = (int) ($expected[$fac]['sten'] ?? -1);
    $rOk = $cr === $er ? 'OK' : 'FAIL';
    $sOk = $cs === $es ? 'OK' : 'FAIL';
    if ($rOk === 'FAIL') {
        $rawBad[] = $fac;
    }
    if ($sOk === 'FAIL') {
        $stenBad[] = $fac;
    }
    echo sprintf("%-4s raw %2d vs %2d [%s]  STEN %2d vs %2d [%s]\n", $fac, $cr, $er, $rOk, $cs, $es, $sOk);
}

if ($rawBad || $stenBad) {
    echo "\nPHP vs Excel: MISMATCH raw=" . implode(',', $rawBad) . " sten=" . implode(',', $stenBad) . "\n";
    exit(1);
}

echo "\nPHP SixteenPfScoringService: cocok dengan Excel.\n";
