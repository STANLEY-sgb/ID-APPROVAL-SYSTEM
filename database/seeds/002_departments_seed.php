<?php
declare(strict_types=1);

use Mengo\IdApproval\Support\Timezone;

return function (PDO $pdo): array {
    $now = Timezone::nowString();

    $departments = [
        ['code' => 'CLIN', 'name' => 'Clinical Services & Medicine'],
        ['code' => 'NURS', 'name' => 'Nursing & Midwifery Services'],
        ['code' => 'PHAR', 'name' => 'Pharmacy & Therapeutics'],
        ['code' => 'LABS', 'name' => 'Laboratory & Pathology Services'],
        ['code' => 'RADI', 'name' => 'Radiology & Medical Imaging'],
        ['code' => 'ADMN', 'name' => 'Hospital Administration & Finance'],
        ['code' => 'HRES', 'name' => 'Human Resources & Staff Development'],
        ['code' => 'ICTS', 'name' => 'ICT & Health Informatics'],
        ['code' => 'ESTA', 'name' => 'Estates, Engineering & Facilities'],
        ['code' => 'SECU', 'name' => 'Security & Transport Services'],
        ['code' => 'CATE', 'name' => 'Catering & Hospitality']
    ];

    $stmtCheck = $pdo->prepare("SELECT id FROM departments WHERE code = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO departments (code, name, created_at) VALUES (?, ?, ?)");

    $count = 0;
    foreach ($departments as $d) {
        $stmtCheck->execute([$d['code']]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute([$d['code'], $d['name'], $now]);
            $count++;
        }
    }

    return [
        'departments_seeded' => $count
    ];
};
