<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class CorrectionRequest
{
    public function __construct(
        public ?int $id,
        public int $id_card_id,
        public int $version_id,
        public int $requested_by_user_id,
        public string $reason,
        public string $status = 'PENDING',
        public ?int $resolved_version_id = null,
        public ?string $requested_at = null,
        public ?string $resolved_at = null,
        public ?string $requester_name = null,
        public ?string $requester_email = null,
        public ?int $version_number = null,
        public ?int $resolved_version_number = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: (int)($data['id_card_id'] ?? 0),
            version_id: (int)($data['version_id'] ?? 0),
            requested_by_user_id: (int)($data['requested_by_user_id'] ?? 0),
            reason: (string)($data['reason'] ?? ''),
            status: (string)($data['status'] ?? 'PENDING'),
            resolved_version_id: isset($data['resolved_version_id']) ? (int)$data['resolved_version_id'] : null,
            requested_at: isset($data['requested_at']) ? (string)$data['requested_at'] : null,
            resolved_at: isset($data['resolved_at']) ? (string)$data['resolved_at'] : null,
            requester_name: $data['requester_name'] ?? null,
            requester_email: $data['requester_email'] ?? null,
            version_number: isset($data['version_number']) ? (int)$data['version_number'] : null,
            resolved_version_number: isset($data['resolved_version_number']) ? (int)$data['resolved_version_number'] : null,
        );
    }
}
