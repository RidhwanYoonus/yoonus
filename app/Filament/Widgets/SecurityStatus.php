<?php

namespace App\Filament\Widgets;

use App\Support\SecurityCheck;
use Filament\Widgets\Widget;

class SecurityStatus extends Widget
{
    protected static string $view = 'filament.widgets.security-status';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_admin && SecurityCheck::problems() !== [];
    }

    protected function getViewData(): array
    {
        return ['problems' => SecurityCheck::problems()];
    }
}
