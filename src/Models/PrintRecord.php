<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class PrintRecord
{
    public function __construct(
        public ?int $id,
        public int $id_card_id,
        public int $version_id,
        public int $printing_user_id,
        public string $printing_user_name,
        public string $file_sha256_at_print,
        public ?string $print_notes = null,
        public ?string $printed_at = null,
        public ?int $version_number = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: (int)($data['id_card_id'] ?? 0),
            version_id: (int)($data['version_id'] ?? 0),
            printing_user_id: (int)($data['printing_user_id'] ?? 0),
            printing_user_name: (string)($data['printing_user_name'] ?? ''),
            file_sha256_at_print: (string)($data['file_sha256_at_print'] ?? ''),
            print_notes: isset($data['print_notes']) ? (string)$data['print_notes'] : null,
            printed_at: isset($data['printed_at']) ? (string)$data['printed_at'] : null,
            version_number: isset($data['version_number']) ? (int)$data['version_number'] : null,
        );
    }
}
