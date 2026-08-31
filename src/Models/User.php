<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class User
{
    public function __construct(
        public ?int $id,
        public string $staff_id,
        public string $name,
        public string $email,
        public string $password_hash,
        public string $role,
        public string $department = 'Administration',
        public ?string $phone = null,
        public string $status = 'ACTIVE',
        public int $force_password_change = 0,
        public ?string $last_login_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?string $username = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            staff_id: (string)($data['staff_id'] ?? ''),
            name: (string)($data['name'] ?? ''),
            email: (string)($data['email'] ?? ''),
            password_hash: (string)($data['password_hash'] ?? ''),
            role: (string)($data['role'] ?? Role::DESIGNER),
            department: (string)($data['department'] ?? 'Administration'),
            phone: isset($data['phone']) ? (string)$data['phone'] : null,
            status: (string)($data['status'] ?? 'ACTIVE'),
            force_password_change: (int)($data['force_password_change'] ?? 0),
            last_login_at: isset($data['last_login_at']) ? (string)$data['last_login_at'] : null,
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            updated_at: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            username: isset($data['username']) ? (string)$data['username'] : (isset($data['email']) ? explode('@', $data['email'])[0] : null)
        );
    }

    public function isDesigner(): bool
    {
        return $this->role === Role::DESIGNER;
    }

    public function isHrManager(): bool
    {
        return $this->role === Role::HR_MANAGER;
    }

    public function isPrintingOfficer(): bool
    {
        return $this->role === Role::PRINTING_OFFICER;
    }

    public function isAdministrator(): bool
    {
        return $this->role === Role::ADMINISTRATOR;
    }
}
