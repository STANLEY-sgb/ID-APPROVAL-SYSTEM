<?php
declare(strict_types=1);

$report = [];

// 1. PHP Environment
$phpVersion = PHP_VERSION;
$extensions = get_loaded_extensions();
$sqliteVersion = class_exists('SQLite3') ? SQLite3::version()['versionString'] : 'N/A';
$pdoDrivers = PDO::getAvailableDrivers();

$requiredExtensions = ['pdo', 'pdo_sqlite', 'fileinfo', 'openssl', 'mbstring', 'session', 'json', 'gd', 'curl', 'zip'];
$extensionStatus = [];
foreach ($requiredExtensions as $ext) {
    $extensionStatus[$ext] = extension_loaded($ext);
}

echo "=== 1. PHP ENVIRONMENT ===" . PHP_EOL;
echo "PHP Version: " . $phpVersion . PHP_EOL;
echo "SQLite Version: " . $sqliteVersion . PHP_EOL;
echo "PDO Drivers: " . implode(', ', $pdoDrivers) . PHP_EOL;
echo "Required Extensions:" . PHP_EOL;
foreach ($extensionStatus as $ext => $loaded) {
    echo sprintf("  %-15s : %s\n", $ext, $loaded ? "[OK]" : "[MISSING]");
}

// Test SQLite WAL mode and Foreign Keys
try {
    $testDb = new PDO('sqlite::memory:');
    $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $testDb->exec('PRAGMA foreign_keys = ON');
    $fkStatus = $testDb->query('PRAGMA foreign_keys')->fetchColumn();
    echo "SQLite Foreign Keys Support: " . ($fkStatus ? "ENABLED" : "DISABLED") . PHP_EOL;
} catch (Exception $e) {
    echo "SQLite Test Failed: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== 2. PDF SCAN & METADATA INSPECTION ===" . PHP_EOL;

$workspaceDir = dirname(__DIR__);
$pdfFiles = glob($workspaceDir . '/*.pdf');
$totalPdfs = count($pdfFiles);

echo "Total PDFs located in workspace: " . $totalPdfs . PHP_EOL;

$validPdfs = 0;
$corruptedPdfs = [];
$duplicatesByHash = [];
$fileSizes = [];
$names = [];

$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach ($pdfFiles as $pdfPath) {
    $basename = basename($pdfPath);
    $filesize = filesize($pdfPath);
    $fileSizes[] = $filesize;

    // Check PDF header
    $fp = fopen($pdfPath, 'rb');
    $header = fread($fp, 1024);
    fclose($fp);

    $isPdfHeader = str_starts_with($header, '%PDF-');
    $mime = $finfo->file($pdfPath);

    if (!$isPdfHeader || $mime !== 'application/pdf') {
        $corruptedPdfs[] = [
            'file' => $basename,
            'size' => $filesize,
            'header' => substr($header, 0, 10),
            'mime' => $mime
        ];
        continue;
    }

    $hash = hash_file('sha256', $pdfPath);
    if (isset($duplicatesByHash[$hash])) {
        $duplicatesByHash[$hash][] = $basename;
    } else {
        $duplicatesByHash[$hash] = [$basename];
    }

    $validPdfs++;
    
    // Clean name from filename
    $rawName = preg_replace('/\.pdf$/i', '', $basename);
    $names[$basename] = trim($rawName);
}

echo "Valid readable PDFs: " . $validPdfs . PHP_EOL;
echo "Corrupted/Invalid PDFs: " . count($corruptedPdfs) . PHP_EOL;
if (!empty($corruptedPdfs)) {
    echo "Corrupted PDF List:" . PHP_EOL;
    foreach ($corruptedPdfs as $c) {
        echo "  - " . $c['file'] . " (Mime: {$c['mime']}, Size: {$c['size']})\n";
    }
}

$duplicateGroups = array_filter($duplicatesByHash, fn($group) => count($group) > 1);
echo "Duplicate PDF Content Groups (by SHA-256): " . count($duplicateGroups) . PHP_EOL;
foreach ($duplicateGroups as $hash => $files) {
    echo "  - Hash: " . substr($hash, 0, 12) . "... Files: " . implode(', ', $files) . PHP_EOL;
}

if (!empty($fileSizes)) {
    $minSize = min($fileSizes);
    $maxSize = max($fileSizes);
    $avgSize = array_sum($fileSizes) / count($fileSizes);
    echo sprintf("File Size Stats: Min = %.2f MB, Max = %.2f MB, Avg = %.2f MB\n", 
        $minSize / (1024*1024), $maxSize / (1024*1024), $avgSize / (1024*1024));
}

// Sample sample names extracted
echo PHP_EOL . "Sample 10 Extracted Employee Names from Filenames:" . PHP_EOL;
$sampleNames = array_slice($names, 0, 10, true);
foreach ($sampleNames as $file => $name) {
    echo "  - File: '{$file}' => Extracted: '{$name}'\n";
}

// Save detailed report JSON
$discoveryReport = [
    'php_version' => $phpVersion,
    'sqlite_version' => $sqliteVersion,
    'extensions' => $extensionStatus,
    'total_pdfs' => $totalPdfs,
    'valid_pdfs' => $validPdfs,
    'corrupted_pdfs' => $corruptedPdfs,
    'duplicate_hashes' => $duplicateGroups,
    'file_count' => count($names),
    'inspected_at' => date('Y-m-d H:i:s T')
];

if (!is_dir(__DIR__ . '/../storage/logs')) {
    mkdir(__DIR__ . '/../storage/logs', 0777, true);
}
file_put_contents(__DIR__ . '/../storage/logs/environment_discovery.json', json_encode($discoveryReport, JSON_PRETTY_PRINT));
echo PHP_EOL . "Discovery Report written to storage/logs/environment_discovery.json" . PHP_EOL;
