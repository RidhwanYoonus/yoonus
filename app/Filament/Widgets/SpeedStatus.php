<?php

namespace App\Filament\Widgets;

use App\Support\SpeedCheck;
use Filament\Widgets\Widget;

class SpeedStatus extends Widget
{
    protected static string $view = 'filament.widgets.speed-status';

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_admin && SpeedCheck::problems() !== [];
    }

    protected function getViewData(): array
    {
        return ['problems' => SpeedCheck::problems()];
    }
}
