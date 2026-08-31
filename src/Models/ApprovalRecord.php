<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class ApprovalRecord
{
    public function __construct(
        public ?int $id,
        public int $id_card_id,
        public int $version_id,
        public int $hr_user_id,
        public string $hr_name,
        public string $hr_email,
        public string $hr_role = Role::HR_MANAGER,
        public int $checklist_photo = 1,
        public int $checklist_name = 1,
        public int $checklist_staff_no = 1,
        public int $checklist_department = 1,
        public int $checklist_designation = 1,
        public int $checklist_layout = 1,
        public ?string $approval_notes = null,
        public string $file_sha256_at_approval = '',
        public ?string $approved_at = null,
        public ?int $version_number = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: (int)($data['id_card_id'] ?? 0),
            version_id: (int)($data['version_id'] ?? 0),
            hr_user_id: (int)($data['hr_user_id'] ?? 0),
            hr_name: (string)($data['hr_name'] ?? ''),
            hr_email: (string)($data['hr_email'] ?? ''),
            hr_role: (string)($data['hr_role'] ?? Role::HR_MANAGER),
            checklist_photo: (int)($data['checklist_photo'] ?? 1),
            checklist_name: (int)($data['checklist_name'] ?? 1),
            checklist_staff_no: (int)($data['checklist_staff_no'] ?? 1),
            checklist_department: (int)($data['checklist_department'] ?? 1),
            checklist_designation: (int)($data['checklist_designation'] ?? 1),
            checklist_layout: (int)($data['checklist_layout'] ?? 1),
            approval_notes: isset($data['approval_notes']) ? (string)$data['approval_notes'] : null,
            file_sha256_at_approval: (string)($data['file_sha256_at_approval'] ?? ''),
            approved_at: isset($data['approved_at']) ? (string)$data['approved_at'] : null,
            version_number: isset($data['version_number']) ? (int)$data['version_number'] : null,
        );
    }
}
