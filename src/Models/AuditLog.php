<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class AuditLog
{
    public const ACTION_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const ACTION_LOGIN_FAILED = 'LOGIN_FAILED';
    public const ACTION_PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const ACTION_ID_CREATED = 'ID_CREATED';
    public const ACTION_PDF_UPLOADED = 'PDF_UPLOADED';
    public const ACTION_CORRECTION_REQUESTED = 'CORRECTION_REQUESTED';
    public const ACTION_PDF_REUPLOADED = 'PDF_REUPLOADED';
    public const ACTION_ID_APPROVED = 'ID_APPROVED';
    public const ACTION_ID_PRINTED = 'ID_PRINTED';
    public const ACTION_ID_COLLECTED = 'ID_COLLECTED';
    public const ACTION_PDF_DOWNLOADED = 'PDF_DOWNLOADED';
    public const ACTION_PDF_VIEWED = 'PDF_VIEWED';
    public const ACTION_IMPORT_PROCESSED = 'IMPORT_PROCESSED';
    public const ACTION_DATA_EXPORTED = 'DATA_EXPORTED';

    public function __construct(
        public ?int $id,
        public ?int $id_card_id,
        public ?int $user_id,
        public string $user_name,
        public string $user_role,
        public string $action,
        public string $entity_type = 'ID_CARD',
        public ?int $entity_id = null,
        public ?string $previous_status = null,
        public ?string $new_status = null,
        public ?int $version_number = null,
        public ?string $ip_address = null,
        public ?string $user_agent = null,
        public ?string $details = null,
        public ?string $created_at = null,
        public ?string $card_reference = null,
        public ?string $employee_name = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            id_card_id: isset($data['id_card_id']) ? (int)$data['id_card_id'] : null,
            user_id: isset($data['user_id']) ? (int)$data['user_id'] : null,
            user_name: (string)($data['user_name'] ?? 'System'),
            user_role: (string)($data['user_role'] ?? 'SYSTEM'),
            action: (string)($data['action'] ?? ''),
            entity_type: (string)($data['entity_type'] ?? 'ID_CARD'),
            entity_id: isset($data['entity_id']) ? (int)$data['entity_id'] : null,
            previous_status: isset($data['previous_status']) ? (string)$data['previous_status'] : null,
            new_status: isset($data['new_status']) ? (string)$data['new_status'] : null,
            version_number: isset($data['version_number']) ? (int)$data['version_number'] : null,
            ip_address: isset($data['ip_address']) ? (string)$data['ip_address'] : null,
            user_agent: isset($data['user_agent']) ? (string)$data['user_agent'] : null,
            details: isset($data['details']) ? (string)$data['details'] : null,
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            card_reference: $data['card_reference'] ?? null,
            employee_name: $data['employee_name'] ?? null,
        );
    }
}
