<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use Mengo\IdApproval\Services\IdIngestionService;
use Mengo\IdApproval\Support\Config;

echo "========================================================\n";
echo " MENGO HOSPITAL ID SYSTEM — 287 PDF INGESTION PIPELINE\n";
echo "========================================================\n\n";

Config::load();

$workspaceDir = dirname(__DIR__);
echo "Scanning workspace for PDF templates: {$workspaceDir}\n";

$ingestor = new IdIngestionService();
$report = $ingestor->ingestWorkspacePdfs($workspaceDir);

echo "\n--- INGESTION SUMMARY REPORT ---\n";
echo "Total PDFs Found:       {$report['total_found']}\n";
echo "Successfully Ingested:  {$report['successfully_ingested']}\n";
echo "Already Existing:       {$report['already_existing']}\n";
echo "Needs Manual Review:    {$report['needs_review']}\n";
echo "Errors:                 " . count($report['errors']) . "\n";

echo "\nInitial Lifecycle Status Distribution:\n";
foreach ($report['status_distribution'] as $st => $count) {
    echo sprintf("  %-25s : %d\n", $st, $count);
}

if (!empty($report['errors'])) {
    echo "\nErrors Encountered:\n";
    foreach ($report['errors'] as $err) {
        echo "  - File '{$err['file']}': {$err['error']}\n";
    }
}

echo "\nFull detailed report saved to: storage/logs/ingestion_report.json\n";
echo "PDF ingestion finished.\n";
