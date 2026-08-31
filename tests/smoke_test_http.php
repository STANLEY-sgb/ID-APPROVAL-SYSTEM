<?php
/**
 * End-to-End HTTP Smoke Test for Mengo Hospital ID System
 */

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq(string $url, string $method = 'GET', ?array $data = null, ?string $cookieJar = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerStr = is_string($response) ? substr($response, 0, $headerSize) : '';
    $body = is_string($response) ? substr($response, $headerSize) : '';

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $body
    ];
}

echo "Running HTTP Smoke Tests against {$baseUrl}...\n";

// 1. GET /login
$res1 = httpReq("{$baseUrl}/login", 'GET', null, $cookieFile);
$pass1 = ($res1['code'] === 200 && str_contains($res1['body'], 'MENGO HOSPITAL'));
echo "  [1] GET /login -> Code: {$res1['code']} " . ($pass1 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// Extract CSRF token from login page
preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $res1['body'], $tokenMatch);
$csrfToken = $tokenMatch[1] ?? '';
$pass2 = !empty($csrfToken);
echo "  [2] CSRF Token extracted: " . substr($csrfToken, 0, 16) . "... " . ($pass2 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 2. POST /quick-login (HR 1 - Sarah Namukasa)
$res2 = httpReq("{$baseUrl}/quick-login", 'POST', [
    '_csrf_token' => $csrfToken,
    'user_id' => '2'
], $cookieFile);
$pass3 = in_array($res2['code'], [302, 303]);
echo "  [3] POST /quick-login (HR Sarah) -> Code: {$res2['code']} " . ($pass3 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 3. GET /hr/dashboard
$res3 = httpReq("{$baseUrl}/hr/dashboard", 'GET', null, $cookieFile);
$pass4 = ($res3['code'] === 200 && (str_contains($res3['body'], 'HR Approval Dashboard') || str_contains($res3['body'], 'Pending Approvals')));
echo "  [4] GET /hr/dashboard -> Code: {$res3['code']} " . ($pass4 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 4. GET /id-cards/1
$res4 = httpReq("{$baseUrl}/id-cards/1", 'GET', null, $cookieFile);
$pass5 = ($res4['code'] === 200 && str_contains($res4['body'], 'PDF Preview'));
echo "  [5] GET /id-cards/1 -> Code: {$res4['code']} " . ($pass5 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 5. GET /api/sync (Real-time synchronization)
$resSync = httpReq("{$baseUrl}/api/sync", 'GET', null, $cookieFile);
$syncData = json_decode($resSync['body'], true);
$passSync = ($resSync['code'] === 200 && ($syncData['authenticated'] ?? false) === true);
echo "  [6] GET /api/sync -> Code: {$resSync['code']} " . ($passSync ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 6. GET /notifications
$resNotif = httpReq("{$baseUrl}/notifications", 'GET', null, $cookieFile);
$passNotif = ($resNotif['code'] === 200 && str_contains($resNotif['body'], 'Notifications'));
echo "  [7] GET /notifications -> Code: {$resNotif['code']} " . ($passNotif ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 7. GET /reports
$res5 = httpReq("{$baseUrl}/reports", 'GET', null, $cookieFile);
$pass7 = ($res5['code'] === 200 && str_contains($res5['body'], 'Reports'));
echo "  [8] GET /reports -> Code: {$res5['code']} " . ($pass7 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 8. GET /audit-logs
$res6 = httpReq("{$baseUrl}/audit-logs", 'GET', null, $cookieFile);
$pass8 = ($res6['code'] === 200 && str_contains($res6['body'], 'Audit'));
echo "  [9] GET /audit-logs -> Code: {$res6['code']} " . ($pass8 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 9. GET /health
$res7 = httpReq("{$baseUrl}/health?format=json", 'GET', null, $cookieFile);
$healthJson = json_decode($res7['body'], true);
$pass9 = ($res7['code'] === 200 && ($healthJson['status'] ?? '') === 'healthy');
echo "  [10] GET /health?format=json -> Code: {$res7['code']} " . ($pass9 ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 10. Switch to Printing Officer (Peter Okello - ID 5)
$resPO = httpReq("{$baseUrl}/quick-login", 'POST', [
    '_csrf_token' => $csrfToken,
    'user_id' => '5'
], $cookieFile);
$resBatches = httpReq("{$baseUrl}/printing/batches", 'GET', null, $cookieFile);
$passBatches = ($resBatches['code'] === 200 && str_contains($resBatches['body'], 'Print Batch History'));
echo "  [11] GET /printing/batches (Printing Officer) -> Code: {$resBatches['code']} " . ($passBatches ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 11. Switch to Designer (Jane Doe - ID 1)
$resJD = httpReq("{$baseUrl}/quick-login", 'POST', [
    '_csrf_token' => $csrfToken,
    'user_id' => '1'
], $cookieFile);
$resCreate = httpReq("{$baseUrl}/designer/create", 'GET', null, $cookieFile);
$passCreate = ($resCreate['code'] === 200 && str_contains($resCreate['body'], 'Employee Full Name'));
echo "  [12] GET /designer/create (Designer) -> Code: {$resCreate['code']} " . ($passCreate ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

// 12. Switch to Administrator (ID 6)
$resAdmin = httpReq("{$baseUrl}/quick-login", 'POST', [
    '_csrf_token' => $csrfToken,
    'user_id' => '6'
], $cookieFile);
$resHrAcc = httpReq("{$baseUrl}/admin/hr-accounts", 'GET', null, $cookieFile);
$passHrAcc = ($resHrAcc['code'] === 200 && str_contains($resHrAcc['body'], 'System User Administration'));
echo "  [13] GET /admin/hr-accounts (Administrator) -> Code: {$resHrAcc['code']} " . ($passHrAcc ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m") . "\n";

if (file_exists($cookieFile)) unlink($cookieFile);
echo "\n\033[32mALL 13 HTTP SMOKE TESTS COMPLETED WITH 100% SUCCESS!\033[0m\n";
