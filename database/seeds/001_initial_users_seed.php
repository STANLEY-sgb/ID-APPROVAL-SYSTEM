<?php
declare(strict_types=1);

use Mengo\IdApproval\Support\Config;
use Mengo\IdApproval\Support\Timezone;

return function (PDO $pdo): array {
    $now = Timezone::nowString();

    $designerPassword = (string)Config::get('INITIAL_DESIGNER_PASSWORD', 'MengoDesigner2026!');
    $hrPassword = (string)Config::get('INITIAL_HR_PASSWORD', 'MengoHR2026!');
    $printingPassword = (string)Config::get('INITIAL_PRINTING_PASSWORD', 'MengoPrint2026!');
    $adminPassword = (string)Config::get('INITIAL_ADMIN_PASSWORD', 'MengoAdmin2026!');

    $users = [
        [
            'staff_id' => 'MH-STAFF-00001',
            'name' => 'System Administrator',
            'email' => 'admin@mengohospital.org',
            'password' => $adminPassword,
            'role' => 'ADMINISTRATOR',
            'department' => 'Executive & Hospital ICT',
            'phone' => '+256 700 000 001',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ],
        [
            'staff_id' => 'MH-STAFF-00101',
            'name' => 'Jane Doe',
            'email' => 'designer@mengohospital.org',
            'password' => $designerPassword,
            'role' => 'DESIGNER',
            'department' => 'ICT & Communications',
            'phone' => '+256 701 234 567',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ],
        [
            'staff_id' => 'MH-STAFF-00201',
            'name' => 'Sarah Namukasa',
            'email' => 'sarah.namukasa@mengohospital.org',
            'password' => $hrPassword,
            'role' => 'HR_MANAGER',
            'department' => 'Human Resources',
            'phone' => '+256 772 345 678',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ],
        [
            'staff_id' => 'MH-STAFF-00202',
            'name' => 'David Kato',
            'email' => 'david.kato@mengohospital.org',
            'password' => $hrPassword,
            'role' => 'HR_MANAGER',
            'department' => 'Human Resources',
            'phone' => '+256 782 456 789',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ],
        [
            'staff_id' => 'MH-STAFF-00203',
            'name' => 'Grace Nakato',
            'email' => 'grace.nakato@mengohospital.org',
            'password' => $hrPassword,
            'role' => 'HR_MANAGER',
            'department' => 'Human Resources',
            'phone' => '+256 752 567 890',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ],
        [
            'staff_id' => 'MH-STAFF-00301',
            'name' => 'Peter Okello',
            'email' => 'printing@mengohospital.org',
            'password' => $printingPassword,
            'role' => 'PRINTING_OFFICER',
            'department' => 'Printing & Production Unit',
            'phone' => '+256 703 678 901',
            'status' => 'ACTIVE',
            'force_password_change' => 0
        ]
    ];

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtInsert = $pdo->prepare("
        INSERT INTO users (staff_id, name, email, password_hash, role, department, phone, status, force_password_change, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $created = [];
    foreach ($users as $u) {
        $stmtCheck->execute([$u['email']]);
        if (!$stmtCheck->fetch()) {
            $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmtInsert->execute([
                $u['staff_id'],
                $u['name'],
                $u['email'],
                $hash,
                $u['role'],
                $u['department'],
                $u['phone'],
                $u['status'],
                $u['force_password_change'],
                $now,
                $now
            ]);
            $created[] = $u['email'];
        }
    }

    return [
        'users_created' => count($created),
        'accounts' => $created
    ];
};
