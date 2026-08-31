<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class PrintBatchItem
{
    public const STATUS_VALID = 'VALID';
    public const STATUS_INVALID = 'INVALID';
    public const STATUS_CORRUPTED = 'CORRUPTED';
    public const STATUS_MISSING = 'MISSING';

    public function __construct(
        public ?int $id,
        public int $batch_id,
        public int $id_card_id,
        public ?int $approved_version_id,
        public ?int $employee_id,
        public string $employee_name,
        public int $sequence_number = 1,
        public string $validation_status = self::STATUS_VALID,
        public ?string $failure_reason = null,
        public int $included_in_output = 1,
        public int $is_printed = 0,
        public ?string $printed_at = null,
        // Joined metadata
        public ?string $card_reference = null,
        public ?string $staff_id = null,
        public ?string $department_name = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            batch_id: (int)($data['batch_id'] ?? 0),
            id_card_id: (int)($data['id_card_id'] ?? 0),
            approved_version_id: isset($data['approved_version_id']) ? (int)$data['approved_version_id'] : null,
            employee_id: isset($data['employee_id']) ? (int)$data['employee_id'] : null,
            employee_name: (string)($data['employee_name'] ?? ''),
            sequence_number: (int)($data['sequence_number'] ?? 1),
            validation_status: (string)($data['validation_status'] ?? self::STATUS_VALID),
            failure_reason: isset($data['failure_reason']) ? (string)$data['failure_reason'] : null,
            included_in_output: (int)($data['included_in_output'] ?? 1),
            is_printed: (int)($data['is_printed'] ?? 0),
            printed_at: isset($data['printed_at']) ? (string)$data['printed_at'] : null,
            card_reference: $data['card_reference'] ?? null,
            staff_id: $data['staff_id'] ?? $data['employee_staff_id'] ?? null,
            department_name: $data['department_name'] ?? null
        );
    }
}
