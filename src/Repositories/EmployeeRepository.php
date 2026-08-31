<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\Employee;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class EmployeeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findById(int $id): ?Employee
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name AS department_name 
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? Employee::fromArray($data) : null;
    }

    public function findByStaffId(string $staffId): ?Employee
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name AS department_name 
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.staff_id = ?
        ");
        $stmt->execute([trim($staffId)]);
        $data = $stmt->fetch();
        return $data ? Employee::fromArray($data) : null;
    }

    public function findByName(string $fullName): ?Employee
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name AS department_name 
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE UPPER(TRIM(e.full_name)) = UPPER(TRIM(?))
        ");
        $stmt->execute([$fullName]);
        $data = $stmt->fetch();
        return $data ? Employee::fromArray($data) : null;
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            INSERT INTO employees (
                staff_id, full_name, department_id, designation, blood_group, 
                phone, email, national_id, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['staff_id'],
            $data['full_name'],
            $data['department_id'] ?? 1,
            $data['designation'] ?? 'Staff',
            $data['blood_group'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['national_id'] ?? null,
            $data['status'] ?? 'ACTIVE',
            $now,
            $now
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $now = Timezone::nowString();
        $stmt = $this->pdo->prepare("
            UPDATE employees SET
                full_name = ?,
                department_id = ?,
                designation = ?,
                blood_group = ?,
                phone = ?,
                email = ?,
                national_id = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['full_name'],
            $data['department_id'],
            $data['designation'],
            $data['blood_group'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['national_id'] ?? null,
            $now,
            $id
        ]);
    }

    public function all(int $limit = 500, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name AS department_name 
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            ORDER BY e.full_name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => Employee::fromArray($r), $rows);
    }

    public function getDepartments(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM departments ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function findDepartmentByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM departments WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
