<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class IdVersion
{
    public function __construct(
        public ?int $id,
        public int $id_card_id,
        public int $version_number,
        public string $file_path,
        public string $original_filename,
        public int $file_size,
        public string $file_sha256,
        public string $mime_type = 'application/pdf',
        public int $uploaded_by_user_id = 1,
        public ?int $correction_request_id = null,
        public int $is_approved = 0,
        public ?string $uploaded_at = null,
        public ?string $uploader_name = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: (int)($data['id_card_id'] ?? 0),
            version_number: (int)($data['version_number'] ?? 1),
            file_path: (string)($data['file_path'] ?? ''),
            original_filename: (string)($data['original_filename'] ?? ''),
            file_size: (int)($data['file_size'] ?? 0),
            file_sha256: (string)($data['file_sha256'] ?? ''),
            mime_type: (string)($data['mime_type'] ?? 'application/pdf'),
            uploaded_by_user_id: (int)($data['uploaded_by_user_id'] ?? 1),
            correction_request_id: isset($data['correction_request_id']) ? (int)$data['correction_request_id'] : null,
            is_approved: (int)($data['is_approved'] ?? 0),
            uploaded_at: isset($data['uploaded_at']) ? (string)$data['uploaded_at'] : null,
            uploader_name: $data['uploader_name'] ?? null,
        );
    }
}
