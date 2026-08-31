<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class PrintBatch
{
    public const STATUS_PREPARING = 'PREPARING';
    public const STATUS_VALIDATING = 'VALIDATING';
    public const STATUS_MERGING = 'MERGING';
    public const STATUS_READY = 'READY';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_PARTIAL_SUCCESS = 'PARTIAL_SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public function __construct(
        public ?int $id,
        public string $batch_reference,
        public int $printing_user_id,
        public string $printing_user_name,
        public string $status = self::STATUS_READY,
        public int $total_cards = 1,
        public int $selected_count = 0,
        public int $valid_count = 0,
        public int $failed_count = 0,
        public int $page_count = 0,
        public int $file_size = 0,
        public string $orientation = 'ORIGINAL',
        public string $page_size = 'ORIGINAL',
        public ?string $output_filename = null,
        public ?string $output_path = null,
        public ?string $output_hash = null,
        public ?string $notes = null,
        public ?string $error_summary = null,
        public int $download_count = 0,
        public ?string $created_at = null,
        public ?string $completed_at = null,
        public ?string $expires_at = null,
        public array $items = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            batch_reference: (string)($data['batch_reference'] ?? ''),
            printing_user_id: (int)($data['printing_user_id'] ?? 0),
            printing_user_name: (string)($data['printing_user_name'] ?? ''),
            status: (string)($data['status'] ?? self::STATUS_READY),
            total_cards: (int)($data['total_cards'] ?? ($data['valid_count'] ?? 1)),
            selected_count: (int)($data['selected_count'] ?? 0),
            valid_count: (int)($data['valid_count'] ?? 0),
            failed_count: (int)($data['failed_count'] ?? 0),
            page_count: (int)($data['page_count'] ?? 0),
            file_size: (int)($data['file_size'] ?? 0),
            orientation: (string)($data['orientation'] ?? 'ORIGINAL'),
            page_size: (string)($data['page_size'] ?? 'ORIGINAL'),
            output_filename: isset($data['output_filename']) ? (string)$data['output_filename'] : null,
            output_path: isset($data['output_path']) ? (string)$data['output_path'] : null,
            output_hash: isset($data['output_hash']) ? (string)$data['output_hash'] : null,
            notes: isset($data['notes']) ? (string)$data['notes'] : null,
            error_summary: isset($data['error_summary']) ? (string)$data['error_summary'] : null,
            download_count: (int)($data['download_count'] ?? 0),
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            completed_at: isset($data['completed_at']) ? (string)$data['completed_at'] : null,
            expires_at: isset($data['expires_at']) ? (string)$data['expires_at'] : null,
            items: $data['items'] ?? []
        );
    }

    public function isReady(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_PARTIAL_SUCCESS, self::STATUS_COMPLETED], true)
            && !empty($this->output_path)
            && file_exists($this->output_path);
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) return true;
        if (!empty($this->expires_at) && strtotime($this->expires_at) < time()) return true;
        return false;
    }
}
