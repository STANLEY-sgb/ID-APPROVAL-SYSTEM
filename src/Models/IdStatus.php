<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class IdStatus
{
    public const DRAFT = 'DRAFT';
    public const UPLOADED = 'UPLOADED';
    public const PENDING_HR_APPROVAL = 'PENDING_HR_APPROVAL';
    public const CORRECTION_REQUESTED = 'CORRECTION_REQUESTED';
    public const APPROVED = 'APPROVED';
    public const PRINTED = 'PRINTED';
    public const COLLECTED = 'COLLECTED';
    public const IMPORT_REVIEW_REQUIRED = 'IMPORT_REVIEW_REQUIRED';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::UPLOADED,
            self::PENDING_HR_APPROVAL,
            self::CORRECTION_REQUESTED,
            self::APPROVED,
            self::PRINTED,
            self::COLLECTED,
            self::IMPORT_REVIEW_REQUIRED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::UPLOADED => 'Uploaded',
            self::PENDING_HR_APPROVAL => 'Pending HR Approval',
            self::CORRECTION_REQUESTED => 'Correction Requested',
            self::APPROVED => 'Approved',
            self::PRINTED => 'Printed',
            self::COLLECTED => 'Collected',
            self::IMPORT_REVIEW_REQUIRED => 'Import Review Required',
            default => str_replace('_', ' ', $status),
        };
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'badge-secondary',
            self::UPLOADED => 'badge-info',
            self::PENDING_HR_APPROVAL => 'badge-warning',
            self::CORRECTION_REQUESTED => 'badge-danger',
            self::APPROVED => 'badge-success',
            self::PRINTED => 'badge-primary',
            self::COLLECTED => 'badge-dark',
            self::IMPORT_REVIEW_REQUIRED => 'badge-orange',
            default => 'badge-secondary',
        };
    }

    public static function stepIndex(string $status): int
    {
        return match ($status) {
            self::DRAFT => 1,
            self::UPLOADED => 2,
            self::PENDING_HR_APPROVAL => 3,
            self::CORRECTION_REQUESTED => 3,
            self::APPROVED => 4,
            self::PRINTED => 5,
            self::COLLECTED => 6,
            default => 0,
        };
    }
}
