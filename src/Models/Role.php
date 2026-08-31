<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Models;

class Role
{
    public const DESIGNER = 'DESIGNER';
    public const HR_MANAGER = 'HR_MANAGER';
    public const PRINTING_OFFICER = 'PRINTING_OFFICER';
    public const ADMINISTRATOR = 'ADMINISTRATOR';

    public static function all(): array
    {
        return [
            self::DESIGNER,
            self::HR_MANAGER,
            self::PRINTING_OFFICER,
            self::ADMINISTRATOR,
        ];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::DESIGNER => 'ID Designer',
            self::HR_MANAGER => 'HR Manager',
            self::PRINTING_OFFICER => 'Printing Officer',
            self::ADMINISTRATOR => 'System Administrator',
            default => $role,
        };
    }
}
