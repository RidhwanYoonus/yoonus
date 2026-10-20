<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Who is on the site right now: one row per browser, with the page they are on. Refreshes every 10 seconds. */
class LiveVisitors extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Live visitors';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.live-visitors';

    public int $minutes = 5;

    public bool $withStaff = false;

    public static function canAccess(): bool
    {
        return \App\Support\StaffAccess::current('activity');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => ActivityLog::where('created_at', '>=', now()->subMinutes(5))->where('is_staff', false)->distinct()->count('visitor_id'), 0, false);

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Visitors in the last 5 minutes';
    }

    /** Latest step of each browser seen in the window. */
    protected function liveQuery(): Builder
    {
        $since = now()->subMinutes(max(1, $this->minutes));
        $latest = ActivityLog::query()->selectRaw('MAX(id)')->where('created_at', '>=', $since)
            ->when(! $this->withStaff, fn ($q) => $q->where('is_staff', false))->groupBy('visitor_id');

        return ActivityLog::query()->with('user')->whereIn('id', $latest)
            ->addSelect(['steps' => ActivityLog::query()->from('activity_log as a2')->selectRaw('COUNT(*)')
                ->whereColumn('a2.visitor_id', 'activity_log.visitor_id')->where('a2.created_at', '>=', now()->subMinutes(30))])
            ->addSelect(['first_seen' => ActivityLog::query()->from('activity_log as a3')->selectRaw('MIN(a3.created_at)')
                ->whereColumn('a3.visitor_id', 'activity_log.visitor_id')->where('a3.created_at', '>=', now()->subMinutes(30))]);
    }

    public function stats(): array
    {
        $rows = $this->liveQuery()->get(['id', 'user_id', 'event', 'visitor_id', 'path']);

        return [
            'Online now' => $rows->count(),
            'Signed in' => $rows->whereNotNull('user_id')->count(),
            'Guests' => $rows->whereNull('user_id')->count(),
            'In bag / checkout' => $rows->filter(fn ($r) => in_array($r->event, ['view_bag', 'add_to_bag', 'change_qty', 'checkout', 'checkout_failed'], true))->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->liveQuery())
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('who')->label('Visitor')->state(fn (ActivityLog $r) => $r->user?->name ?? 'Guest')
                    ->description(fn (ActivityLog $r) => $r->user?->email ?? $r->email)->weight('bold')
                    ->color(fn (ActivityLog $r) => $r->is_staff ? 'warning' : ($r->user_id ? 'success' : null)),
                Tables\Columns\TextColumn::make('event')->label('Now')->badge()
                    ->formatStateUsing(fn (string $state) => ActivityLog::EVENTS[$state] ?? $state)
                    ->color(fn (string $state) => ActivityLog::COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('path')->label('Page')->limit(40)->tooltip(fn (ActivityLog $r) => $r->path)
                    ->description(fn (ActivityLog $r) => $r->subject)->url(fn (ActivityLog $r) => url($r->path), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('created_at')->label('Last seen')->since(),
                Tables\Columns\TextColumn::make('first_seen')->label('On site for')
                    ->state(fn (ActivityLog $r) => $r->first_seen ? \Illuminate\Support\Carbon::parse($r->first_seen)->diffForHumans($r->created_at, true) : '—'),
                Tables\Columns\TextColumn::make('steps')->label('Steps (30 min)'),
                Tables\Columns\TextColumn::make('device')->label('Device')->placeholder('—'),
                Tables\Columns\TextColumn::make('ip')->label('IP address')->copyable()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('movements')->label('All movements')->icon('heroicon-o-arrows-pointing-out')->color('gray')
                    ->url(fn (ActivityLog $r) => ActivityLogResource::urlFor($r->user_id, null, $r->user_id ? null : $r->visitor_id)),
            ])
            ->emptyStateHeading('Nobody on the site right now')
            ->emptyStateDescription('Visitors appear here as soon as they open a page.')
            ->emptyStateIcon('heroicon-o-signal-slash');
    }

    public function updatedMinutes(): void
    {
        $this->resetTable();
    }

    public function updatedWithStaff(): void
    {
        $this->resetTable();
    }
}
