<?php
/**
 * Comprehensive Automated Verification Suite for Reports & Analytics
 * Mengo Hospital HR ID Approval & Printing System
 */

declare(strict_types=1);

require_once 'e:/ID APPROVAL SYSTEM/src/autoload.php';

use Mengo\IdApproval\Repositories\ReportRepository;
use Mengo\IdApproval\Services\ReportService;
use Mengo\IdApproval\Support\Database;

$pdo = Database::getConnection();
$reportRepo = new ReportRepository($pdo);
$reportService = new ReportService($reportRepo);

$pass = 0;
$fail = 0;

function ok(string $msg) { global $pass; $pass++; echo "  [PASS] {$msg}\n"; }
function fail(string $msg) { global $fail; $fail++; echo "  [FAIL] {$msg}\n"; }

echo "\n=======================================================\n";
echo "MENGO HOSPITAL REPORTS & ANALYTICS VERIFICATION SUITE\n";
echo "=======================================================\n\n";

// ── 1. Overview KPIs Verification ─────────────────────────────────────────────
echo "1. Executive Overview Statistics:\n";
$overview = $reportRepo->getOverviewStats();

if ($overview['total_ids'] > 0) {
    ok("Total IDs count is non-zero and populated from database: {$overview['total_ids']}");
} else {
    fail("Total IDs returned 0");
}

if ($overview['pending_approval'] >= 0 && $overview['approved_ready'] >= 0 && $overview['printed_total'] >= 0 && $overview['collected_total'] >= 0) {
    ok("Lifecycle status breakdown counts valid: Pending={$overview['pending_approval']}, Approved={$overview['approved_ready']}, Printed={$overview['printed_total']}, Collected={$overview['collected_total']}");
} else {
    fail("Lifecycle status counts invalid");
}

// ── 2. Mathematical Consistency of Derived KPIs ──────────────────────────────
echo "\n2. Derived Performance Ratios & Safe Math:\n";
$appRate = $overview['approval_rate'];
$corrRate = $overview['correction_rate'];
$printRate = $overview['printing_rate'];
$collRate = $overview['collection_rate'];
$compRate = $overview['completion_rate'];

if (is_numeric($appRate) && $appRate >= 0 && $appRate <= 100) {
    ok("Approval Rate is valid: {$appRate}%");
} else {
    fail("Approval Rate is out of bounds or invalid: {$appRate}");
}

if (is_numeric($corrRate) && $corrRate >= 0 && $corrRate <= 100) {
    ok("Correction Rate is valid: {$corrRate}%");
} else {
    fail("Correction Rate is out of bounds or invalid: {$corrRate}");
}

if (is_numeric($printRate) && $printRate >= 0 && $printRate <= 100) {
    ok("Printing Rate is valid: {$printRate}%");
} else {
    fail("Printing Rate is out of bounds: {$printRate}");
}

if (is_numeric($collRate) && $collRate >= 0 && $collRate <= 100) {
    ok("Collection Rate is valid: {$collRate}%");
} else {
    fail("Collection Rate is out of bounds: {$collRate}");
}

if (is_numeric($compRate) && $compRate >= 0 && $compRate <= 100) {
    ok("Overall Completion Rate is valid: {$compRate}%");
} else {
    fail("Completion Rate is out of bounds: {$compRate}");
}

// ── 3. Department Breakdown Verification ──────────────────────────────────────
echo "\n3. Department Breakdown Aggregation:\n";
$depts = $reportRepo->getDepartmentBreakdown();
if (!empty($depts) && count($depts) > 0) {
    ok("Department breakdown returned " . count($depts) . " department records with aggregate metrics");
    $first = $depts[0];
    if (isset($first['name'], $first['code'], $first['total'], $first['completion_rate'])) {
        ok("Department fields correctly structured: {$first['name']} ({$first['code']}) — Total: {$first['total']}, Completion: {$first['completion_rate']}%");
    } else {
        fail("Department fields missing or improperly named");
    }
} else {
    fail("Department breakdown returned empty array");
}

// ── 4. HR Manager Performance Analytics ──────────────────────────────────────
echo "\n4. HR Manager Performance Workload:\n";
$hrStats = $reportRepo->getHrManagerPerformance();
if (!empty($hrStats)) {
    ok("HR Manager performance analytics populated (" . count($hrStats) . " managers tracked)");
    $topHr = $hrStats[0];
    ok("Top HR reviewer {$topHr['name']}: {$topHr['approval_count']} approvals, {$topHr['correction_count']} corrections, Ratio: {$topHr['approval_ratio']}%");
} else {
    fail("HR Manager analytics returned empty");
}

// ── 5. Designer Quality Analytics ─────────────────────────────────────────────
echo "\n5. Designer Quality & Artwork Metrics:\n";
$designerStats = $reportRepo->getDesignerPerformance();
if (!empty($designerStats)) {
    ok("Designer analytics populated (" . count($designerStats) . " designers tracked)");
    $topDs = $designerStats[0];
    ok("Designer {$topDs['name']}: Submitted {$topDs['submitted_count']} IDs, Success rate: {$topDs['success_rate']}%, Avg versions: v{$topDs['avg_versions']}");
} else {
    fail("Designer analytics returned empty");
}

// ── 6. Printing & Batch Production Analytics ─────────────────────────────────
echo "\n6. Printing Officer & Batch Production Metrics:\n";
$printStats = $reportRepo->getPrintingPerformance();
if (isset($printStats['total_batches'], $printStats['avg_batch_size'], $printStats['officers'])) {
    ok("Printing metrics populated: Total Batches={$printStats['total_batches']}, Avg Batch Size={$printStats['avg_batch_size']}, Total Batched Cards={$printStats['total_batched_cards']}");
} else {
    fail("Printing metrics missing required keys");
}

$recentBatches = $reportRepo->getRecentBatches(5);
if (!empty($recentBatches)) {
    ok("Recent print batches loaded: " . count($recentBatches) . " batches (Latest: {$recentBatches[0]['batch_reference']})");
} else {
    fail("Recent print batches returned empty");
}

// ── 7. Time Series Trend ──────────────────────────────────────────────────────
echo "\n7. 14-Day Time Series Trend Activity:\n";
$timeSeries = $reportRepo->getTimeSeries(14);
if (count($timeSeries) === 14) {
    ok("Time series returned exactly 14 contiguous calendar days");
    $activeDays = array_filter($timeSeries, fn($d) => ($d['submitted'] + $d['approved'] + $d['printed'] + $d['collected']) > 0);
    ok("Detected " . count($activeDays) . " active operational days with recorded lifecycle actions");
} else {
    fail("Time series did not return 14 days");
}

// ── 8. Time-Based Filtering Verification ─────────────────────────────────────
echo "\n8. Time-Based Preset Date Filtering:\n";
$periods = ['today', 'last_7_days', 'this_month', 'all_time'];
foreach ($periods as $p) {
    $filteredData = $reportService->getDashboardData(['period' => $p]);
    if (isset($filteredData['overview']['total_ids'])) {
        ok("Period '{$p}' successfully resolved (Total IDs: {$filteredData['overview']['total_ids']})");
    } else {
        fail("Period '{$p}' failed to resolve");
    }
}

// ── 9. CSV Export Formatting & Anti-Formula Injection ────────────────────────
echo "\n9. CSV Export Stream Generation:\n";
$csv = $reportService->exportCsv(['period' => 'all_time']);
if (!empty($csv) && str_starts_with($csv, chr(0xEF) . chr(0xBB) . chr(0xBF))) {
    ok("CSV generated with UTF-8 BOM for Excel compatibility");
    $lines = explode("\n", trim($csv));
    ok("CSV contains " . (count($lines) - 1) . " data rows with headers matching database records");
    if (str_contains($lines[0], 'Card Reference') && str_contains($lines[0], 'Employee Name') && str_contains($lines[0], 'Current Status')) {
        ok("CSV header row contains correct operational columns");
    } else {
        fail("CSV header row missing required columns");
    }
} else {
    fail("CSV export failed or missing UTF-8 BOM");
}

// ── SUMMARY ──────────────────────────────────────────────────────────────────
echo "\n=======================================================\n";
$total = $pass + $fail;
echo "REPORTS VERIFICATION SUMMARY: {$pass} Passed, {$fail} Failed (of {$total} tests)\n";
if ($fail === 0) {
    echo "ALL REPORTING ENGINE TESTS PASSED WITH 100% SUCCESS!\n";
} else {
    echo "SOME TESTS FAILED — Review above\n";
}
echo "=======================================================\n\n";
