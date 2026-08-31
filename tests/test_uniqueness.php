<?php
/**
 * Targeted regression test: Email and Username uniqueness validation
 * Tests the three scenarios mandated by the prompt:
 *  1. Edit user 5 keeping their own email -> PASS (allowed)
 *  2. Try to assign another user's email to user 5 -> FAIL (rejected before SQL)
 *  3. Assign a genuinely unique email -> PASS (allowed)
 */

require_once 'e:/ID APPROVAL SYSTEM/src/autoload.php';

use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Support\Database;

$db = Database::getConnection();
$userRepo = new UserRepository($db);
$auditRepo = new AuditLogRepository($db);

$pass = 0;
$fail = 0;

function ok(string $msg) { global $pass; $pass++; echo "  [PASS] {$msg}\n"; }
function fail(string $msg) { global $fail; $fail++; echo "  [FAIL] {$msg}\n"; }

echo "\n=======================================================\n";
echo "EMAIL/USERNAME UNIQUENESS REGRESSION TEST\n";
echo "=======================================================\n\n";

// ── Load user 5 and another user ─────────────────────────────────────────────
$user5 = $userRepo->findById(5);
if (!$user5) {
    // Fallback: use first non-admin user
    $allUsers = $userRepo->all();
    $user5 = $allUsers[0] ?? null;
}
if (!$user5) { die("No test user found. Abort.\n"); }

// Find a different user with a different email to use as the conflict target
$conflictUser = null;
foreach ($userRepo->all() as $u) {
    if ($u->id !== $user5->id) { $conflictUser = $u; break; }
}
if (!$conflictUser) { die("Need at least 2 users.\n"); }

echo "Testing User: ID={$user5->id}, Username={$user5->username}, Email={$user5->email}\n";
echo "Conflict User: ID={$conflictUser->id}, Username={$conflictUser->username}, Email={$conflictUser->email}\n\n";

// ── SCENARIO 1: Own email update (same email, should be ALLOWED) ──────────────
echo "Scenario 1: Re-saving user {$user5->id}'s own email ({$user5->email}):\n";
$isConflict = $userRepo->isEmailTaken($user5->email, $user5->id);
if (!$isConflict) {
    ok("isEmailTaken correctly returns FALSE for user's own email (update allowed)");
} else {
    fail("isEmailTaken incorrectly returned TRUE for user's own email");
}

// ── SCENARIO 2: Another user's email (should be REJECTED) ────────────────────
echo "\nScenario 2: Attempting to assign {$conflictUser->email} (belongs to user {$conflictUser->id}) to user {$user5->id}:\n";
$isConflict = $userRepo->isEmailTaken($conflictUser->email, $user5->id);
if ($isConflict) {
    ok("isEmailTaken correctly returns TRUE for email belonging to another user (update rejected)");
} else {
    fail("isEmailTaken returned FALSE - this would cause a UNIQUE constraint violation");
}

// ── SCENARIO 3: Genuinely unique email (should be ALLOWED) ───────────────────
$uniqueEmail = 'unique_test_' . time() . '@mengohospital.org';
echo "\nScenario 3: Assigning genuinely unique email ({$uniqueEmail}) to user {$user5->id}:\n";
$isConflict = $userRepo->isEmailTaken($uniqueEmail, $user5->id);
if (!$isConflict) {
    ok("isEmailTaken correctly returns FALSE for unique new email (update allowed)");
} else {
    fail("isEmailTaken incorrectly rejected a unique email");
}

// ── SCENARIO 4: Username uniqueness own username ──────────────────────────────
echo "\nScenario 4: Re-saving user {$user5->id}'s own username ({$user5->username}):\n";
$isTaken = $userRepo->isUsernameTaken($user5->username, $user5->id);
if (!$isTaken) {
    ok("isUsernameTaken correctly returns FALSE for user's own username");
} else {
    fail("isUsernameTaken incorrectly rejected user's own username");
}

// ── SCENARIO 5: Another user's username ──────────────────────────────────────
echo "\nScenario 5: Attempting to assign {$conflictUser->username} (belongs to user {$conflictUser->id}) to user {$user5->id}:\n";
$isTaken = $userRepo->isUsernameTaken($conflictUser->username, $user5->id);
if ($isTaken) {
    ok("isUsernameTaken correctly returns TRUE for username belonging to another user");
} else {
    fail("isUsernameTaken returned FALSE - would cause UNIQUE constraint violation");
}

// ── SCENARIO 6: Case-insensitive email check ──────────────────────────────────
echo "\nScenario 6: Case-insensitive email conflict detection:\n";
$upperEmail = strtoupper($conflictUser->email);
$isTaken = $userRepo->isEmailTaken($upperEmail, $user5->id);
if ($isTaken) {
    ok("isEmailTaken correctly detects case-insensitive duplicate ({$upperEmail})");
} else {
    fail("isEmailTaken missed case-insensitive duplicate");
}

// ── SCENARIO 7: Actual UPDATE with valid data (transaction commit) ────────────
echo "\nScenario 7: Performing actual UPDATE with valid data (transaction test):\n";
$originalName = $user5->name;
$pdo = Database::getConnection();
$pdo->beginTransaction();
try {
    $updated = $userRepo->updateUser($user5->id, [
        'name'  => $originalName,
        'email' => $user5->email, // own email - no conflict
    ]);
    $auditRepo->create([
        'user_id'   => 6, // admin ID
        'user_name' => 'System Administrator',
        'user_role' => 'ADMINISTRATOR',
        'action'    => 'USER_ACCOUNT_UPDATED',
        'details'   => "Regression test update for user {$user5->id}.",
        'ip_address' => '127.0.0.1',
    ]);
    $pdo->commit();
    ok("Transaction committed successfully (no UNIQUE constraint violation)");
} catch (\Throwable $e) {
    $pdo->rollBack();
    fail("Transaction failed: " . $e->getMessage());
}

// ── SCENARIO 8: Previous audit bug (DATA_EXPORTED with null id_card_id) ───────
echo "\nScenario 8: Audit record with null id_card_id (previous FK violation test):\n";
$pdo->beginTransaction();
try {
    $auditRepo->create([
        'id_card_id' => null,
        'user_id'    => 6,
        'user_name'  => 'System Administrator',
        'user_role'  => 'ADMINISTRATOR',
        'action'     => 'DATA_EXPORTED',
        'entity_type'=> 'SYSTEM',
        'details'    => 'Regression test: DATA_EXPORTED with null id_card_id',
        'ip_address' => '127.0.0.1',
    ]);
    $pdo->commit();
    ok("DATA_EXPORTED audit record with null id_card_id inserted without FK violation");
} catch (\Throwable $e) {
    $pdo->rollBack();
    fail("FK violation re-occurred: " . $e->getMessage());
}

// ── SUMMARY ───────────────────────────────────────────────────────────────────
echo "\n=======================================================\n";
$total = $pass + $fail;
echo "UNIQUENESS REGRESSION TEST SUMMARY: {$pass} Passed, {$fail} Failed (of {$total} tests)\n";
if ($fail === 0) {
    echo "ALL UNIQUENESS TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED — review above\n";
}
echo "=======================================================\n\n";
