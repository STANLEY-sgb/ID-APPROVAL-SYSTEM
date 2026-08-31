<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Repositories;

use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Support\Database;
use Mengo\IdApproval\Support\Timezone;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([trim($email)]);
        $data = $stmt->fetch();
        return $data ? User::fromArray($data) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([trim($username)]);
        $data = $stmt->fetch();
        return $data ? User::fromArray($data) : null;
    }

    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $clean = trim($identifier);
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
        $stmt->execute([$clean, $clean]);
        $data = $stmt->fetch();
        return $data ? User::fromArray($data) : null;
    }

    public function isUsernameTaken(string $username, ?int $excludeUserId = null): bool
    {
        $clean = trim($username);
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
            $stmt->execute([$clean, $excludeUserId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $stmt->execute([$clean]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function isEmailTaken(string $email, ?int $excludeUserId = null): bool
    {
        $clean = strtolower(trim($email));
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
            $stmt->execute([$clean, $excludeUserId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE LOWER(email) = ? LIMIT 1");
            $stmt->execute([$clean]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function findByRole(string $role): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE role = ? AND status = 'ACTIVE' ORDER BY name ASC");
        $stmt->execute([$role]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => User::fromArray($r), $rows);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC");
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => User::fromArray($r), $rows);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?");
        $now = Timezone::nowString();
        $stmt->execute([$now, $now, $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET password_hash = ?, force_password_change = 0, updated_at = ? 
            WHERE id = ?
        ");
        $now = Timezone::nowString();
        $stmt->execute([$passwordHash, $now, $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = ?, updated_at = ? WHERE id = ?");
        $now = Timezone::nowString();
        $stmt->execute([$status, $now, $id]);
    }

    public function create(array $data): int
    {
        $now = Timezone::nowString();
        $username = trim($data['username'] ?? '');
        if (empty($username) && !empty($data['email'])) {
            $username = explode('@', $data['email'])[0];
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                staff_id, username, name, email, password_hash, role, department, phone, status, force_password_change, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['staff_id'],
            $username,
            $data['name'],
            $data['email'],
            $data['password_hash'],
            $data['role'],
            $data['department'] ?? 'Human Resources',
            $data['phone'] ?? null,
            $data['status'] ?? 'ACTIVE',
            $data['force_password_change'] ?? 0,
            $now,
            $now
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateUser(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowed = ['username', 'name', 'email', 'role', 'department', 'phone', 'status'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = ?";
                $params[] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $now = Timezone::nowString();
        $fields[] = "updated_at = ?";
        $params[] = $now;
        $params[] = $id;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function getHrManagersWithStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT u.*, 
                   COUNT(ar.id) AS approval_count
            FROM users u
            LEFT JOIN approval_records ar ON ar.hr_user_id = u.id
            WHERE u.role = 'HR_MANAGER'
            GROUP BY u.id
            ORDER BY u.status ASC, u.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
