<?php
declare(strict_types=1);
define('BASE_DIR', dirname(__DIR__));
require BASE_DIR . '/src/autoload.php';
use Mengo\IdApproval\Support\Database;
$pdo = Database::getConnection();

// Schema inspection
$tables = ['notifications', 'notification_outbox', 'print_batches', 'print_batch_items', 'audit_logs'];
foreach ($tables as $t) {
    echo "$t columns:\n";
    $cols = $pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  " . $c['name'] . " " . $c['type'] . "\n";
    }
    echo "\n";
}

// Batch 1 mixed content
echo "BATCH 1 contents:\n";
$rows = $pdo->query("SELECT pbi.*, c.card_reference, e.full_name FROM print_batch_items pbi LEFT JOIN id_cards c ON c.id=pbi.id_card_id LEFT JOIN employees e ON e.id=c.employee_id WHERE pbi.batch_id=1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  item_id={$r['id']} card_id={$r['id_card_id']} ref={$r['card_reference']} emp={$r['full_name']}\n";
}

echo "\nAll notifications:\n";
$rows = $pdo->query("SELECT * FROM notifications LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  " . json_encode($r) . "\n";
}

echo "\nAll notification_outbox:\n";
$rows = $pdo->query("SELECT * FROM notification_outbox LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  " . json_encode($r) . "\n";
}
