<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class IdCard
{
    public function __construct(
        public ?int $id,
        public string $card_reference,
        public int $employee_id,
        public string $current_status,
        public int $current_version_number = 1,
        public ?int $approved_version_id = null,
        public int $created_by_user_id = 1,
        public ?int $assigned_designer_id = null,
        public int $needs_import_review = 0,
        public ?string $import_notes = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        // Joined details
        public ?string $employee_name = null,
        public ?string $employee_staff_id = null,
        public ?string $department_name = null,
        public ?string $designation = null,
        public ?string $blood_group = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $national_id = null,
        public ?string $created_by_name = null,
        public ?string $assigned_designer_name = null,
        public ?string $latest_pdf_sha256 = null,
        public ?int $latest_pdf_size = null,
        public ?string $latest_pdf_path = null,
        public ?string $approved_by_name = null,
        public ?string $approved_at = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            card_reference: (string)($data['card_reference'] ?? ''),
            employee_id: (int)($data['employee_id'] ?? 0),
            current_status: (string)($data['current_status'] ?? IdStatus::DRAFT),
            current_version_number: (int)($data['current_version_number'] ?? 1),
            approved_version_id: isset($data['approved_version_id']) ? (int)$data['approved_version_id'] : null,
            created_by_user_id: (int)($data['created_by_user_id'] ?? 1),
            assigned_designer_id: isset($data['assigned_designer_id']) ? (int)$data['assigned_designer_id'] : null,
            needs_import_review: (int)($data['needs_import_review'] ?? 0),
            import_notes: isset($data['import_notes']) ? (string)$data['import_notes'] : null,
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            updated_at: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            employee_name: $data['employee_name'] ?? $data['full_name'] ?? null,
            employee_staff_id: $data['employee_staff_id'] ?? $data['staff_id'] ?? null,
            department_name: $data['department_name'] ?? null,
            designation: $data['designation'] ?? null,
            blood_group: $data['blood_group'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            national_id: $data['national_id'] ?? null,
            created_by_name: $data['created_by_name'] ?? null,
            assigned_designer_name: $data['assigned_designer_name'] ?? null,
            latest_pdf_sha256: $data['latest_pdf_sha256'] ?? $data['file_sha256'] ?? null,
            latest_pdf_size: isset($data['latest_pdf_size']) ? (int)$data['latest_pdf_size'] : (isset($data['file_size']) ? (int)$data['file_size'] : null),
            latest_pdf_path: $data['latest_pdf_path'] ?? $data['file_path'] ?? null,
            approved_by_name: $data['approved_by_name'] ?? null,
            approved_at: $data['approved_at'] ?? null,
        );
    }

    public function __get(string $name)
    {
        return match($name) {
            'staff_id' => $this->employee_staff_id,
            'approver_name' => $this->approved_by_name,
            default => null
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['staff_id', 'approver_name'], true) && $this->__get($name) !== null;
    }
}
