<?php

namespace App\Filament\Resources\MessageLogResource\Widgets;

use App\Models\NotificationLog;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MessageStats extends Base
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $month = fn () => NotificationLog::where('created_at', '>=', now()->startOfMonth());
        $today = fn () => NotificationLog::where('created_at', '>=', now()->startOfDay());
        $notSetUp = $month()->where('status', 'logged')->count();

        return [
            Stat::make('WhatsApp sent · this month', number_format($month()->where('channel', 'whatsapp')->where('status', 'sent')->count()))
                ->description($today()->where('channel', 'whatsapp')->where('status', 'sent')->count().' today')->color('success'),
            Stat::make('Emails sent · this month', number_format($month()->where('channel', 'email')->where('status', 'sent')->count()))
                ->description($today()->where('channel', 'email')->where('status', 'sent')->count().' today'),
            Stat::make('Failed · this month', number_format($month()->where('status', 'failed')->count()))
                ->color($month()->where('status', 'failed')->exists() ? 'danger' : 'gray')->description('Hover the status to see why'),
            Stat::make('Not sent · this month', number_format($notSetUp))
                ->description($notSetUp ? 'Email / WhatsApp not set up yet' : 'All channels set up'),
        ];
    }
}
