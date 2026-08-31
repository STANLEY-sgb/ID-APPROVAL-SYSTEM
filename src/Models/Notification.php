<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class Notification
{
    public const TYPE_ID_UPLOADED = 'ID_UPLOADED';
    public const TYPE_CORRECTION_REQUESTED = 'CORRECTION_REQUESTED';
    public const TYPE_ID_REUPLOADED = 'ID_REUPLOADED';
    public const TYPE_ID_APPROVED = 'ID_APPROVED';
    public const TYPE_ID_READY_FOR_PRINTING = 'ID_READY_FOR_PRINTING';
    public const TYPE_ID_PRINTED = 'ID_PRINTED';
    public const TYPE_ID_READY_FOR_COLLECTION = 'ID_READY_FOR_COLLECTION';
    public const TYPE_ID_COLLECTED = 'ID_COLLECTED';
    public const TYPE_SYSTEM_ALERT = 'SYSTEM_ALERT';

    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public ?string $role_target,
        public string $type,
        public string $title,
        public string $message,
        public ?int $id_card_id = null,
        public ?string $link_url = null,
        public int $is_read = 0,
        public ?string $created_at = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            user_id: isset($data['user_id']) ? (int)$data['user_id'] : null,
            role_target: isset($data['role_target']) ? (string)$data['role_target'] : null,
            type: (string)($data['type'] ?? self::TYPE_SYSTEM_ALERT),
            title: (string)($data['title'] ?? ''),
            message: (string)($data['message'] ?? ''),
            id_card_id: isset($data['id_card_id']) ? (int)$data['id_card_id'] : null,
            link_url: isset($data['link_url']) ? (string)$data['link_url'] : null,
            is_read: (int)($data['is_read'] ?? 0),
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
        );
    }
}
