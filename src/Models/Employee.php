<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class Employee
{
    public function __construct(
        public ?int $id,
        public string $staff_id,
        public string $full_name,
        public int $department_id,
        public string $designation,
        public ?string $blood_group = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $national_id = null,
        public string $status = 'ACTIVE',
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?string $department_name = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            staff_id: (string)($data['staff_id'] ?? ''),
            full_name: (string)($data['full_name'] ?? ''),
            department_id: (int)($data['department_id'] ?? 1),
            designation: (string)($data['designation'] ?? ''),
            blood_group: isset($data['blood_group']) ? (string)$data['blood_group'] : null,
            phone: isset($data['phone']) ? (string)$data['phone'] : null,
            email: isset($data['email']) ? (string)$data['email'] : null,
            national_id: isset($data['national_id']) ? (string)$data['national_id'] : null,
            status: (string)($data['status'] ?? 'ACTIVE'),
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            updated_at: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            department_name: isset($data['department_name']) ? (string)$data['department_name'] : null,
        );
    }
}
