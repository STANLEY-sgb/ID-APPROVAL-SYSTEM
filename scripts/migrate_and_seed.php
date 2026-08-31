<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use Mengo\IdApproval\Database\Migrator;
use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Database;

echo "========================================================\n";
echo " MENGO HOSPITAL ID SYSTEM — DATABASE MIGRATION & SEEDING\n";
echo "========================================================\n\n";

Config::load();
$pdo = Database::getConnection();

// 1. Run Migrations
echo "[1/3] Running Database Migrations...\n";
$migrator = new Migrator($pdo);
$migrationResult = $migrator->run();
echo "  - Previously executed: {$migrationResult['previously_executed']}\n";
echo "  - Applied now: " . count($migrationResult['applied_now']) . "\n";
foreach ($migrationResult['applied_now'] as $mig) {
    echo "    * {$mig}\n";
}
echo "  - Database Integrity: {$migrationResult['integrity']}\n\n";

// 2. Run Seeds
echo "[2/3] Seeding Initial Data...\n";

// Seed 001: Users
$seedUsers = require __DIR__ . '/../database/seeds/001_initial_users_seed.php';
$userResult = $seedUsers($pdo);
echo "  - Users Seeded: {$userResult['users_created']}\n";
foreach ($userResult['accounts'] as $acc) {
    echo "    * {$acc}\n";
}

// Seed 002: Departments
$seedDepts = require __DIR__ . '/../database/seeds/002_departments_seed.php';
$deptResult = $seedDepts($pdo);
echo "  - Departments Seeded: {$deptResult['departments_seeded']}\n\n";

// 3. Final Integrity Check
echo "[3/3] Performing Final Verification...\n";
$integrity = Database::checkIntegrity();
echo "  - Status: " . strtoupper($integrity['status']) . "\n";
if ($integrity['status'] !== 'ok') {
    echo "  - Issues:\n";
    print_r($integrity);
    exit(1);
}

echo "\nDatabase migration and initial seeding completed successfully!\n";
