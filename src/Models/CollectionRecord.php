<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class CollectionRecord
{
    public function __construct(
        public ?int $id,
        public int $id_card_id,
        public int $hr_user_id,
        public string $collected_by_name,
        public string $collected_by_relationship = 'SELF',
        public ?string $recipient_national_id_or_phone = null,
        public ?string $collection_reference = null,
        public ?string $notes = null,
        public ?string $collected_at = null,
        public ?string $hr_name = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: (int)($data['id_card_id'] ?? 0),
            hr_user_id: (int)($data['hr_user_id'] ?? 0),
            collected_by_name: (string)($data['collected_by_name'] ?? ''),
            collected_by_relationship: (string)($data['collected_by_relationship'] ?? 'SELF'),
            recipient_national_id_or_phone: isset($data['recipient_national_id_or_phone']) ? (string)$data['recipient_national_id_or_phone'] : null,
            collection_reference: isset($data['collection_reference']) ? (string)$data['collection_reference'] : null,
            notes: isset($data['notes']) ? (string)$data['notes'] : null,
            collected_at: isset($data['collected_at']) ? (string)$data['collected_at'] : null,
            hr_name: $data['hr_name'] ?? null,
        );
    }
}
