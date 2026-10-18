<?php

namespace App\Filament\Concerns;

use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Model;

/** Resource access from Staff & permissions; the resource sets protected static string $staffArea. */
trait HasStaffArea
{
    public static function staffArea(): string
    {
        return static::$staffArea;
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::current(static::$staffArea, 'view');
    }

    public static function canView(Model $record): bool
    {
        return StaffAccess::current(static::$staffArea, 'view');
    }

    public static function canCreate(): bool
    {
        return StaffAccess::current(static::$staffArea, 'create');
    }

    public static function canEdit(Model $record): bool
    {
        return StaffAccess::current(static::$staffArea, 'edit');
    }

    public static function canDelete(Model $record): bool
    {
        return StaffAccess::current(static::$staffArea, 'delete');
    }

    public static function canDeleteAny(): bool
    {
        return StaffAccess::current(static::$staffArea, 'delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return StaffAccess::current(static::$staffArea, 'delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return StaffAccess::current(static::$staffArea, 'delete');
    }
}
