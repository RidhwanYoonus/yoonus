<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Every step of every visitor: customers, guests and staff. Filter to one customer to see all their movements. */
class ActivityLogResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'activity';

    protected static ?string $model = ActivityLog::class;

    protected static ?string $slug = 'activity-log';

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $pluralModelLabel = 'Activity log';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Link to the log filtered to one customer, guest e-mail or browser. */
    public static function urlFor(?int $userId = null, ?string $email = null, ?string $visitor = null): string
    {
        return static::getUrl('index', ['tableFilters' => array_filter([
            'person' => array_filter(['user_id' => $userId, 'email' => $email, 'visitor' => $visitor]) ?: null,
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i:s')->sortable()
                    ->description(fn (ActivityLog $r) => $r->created_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('who')->label('Who')->state(fn (ActivityLog $r) => $r->user?->name ?? 'Guest')
                    ->description(fn (ActivityLog $r) => $r->user?->email ?? $r->email)
                    ->color(fn (ActivityLog $r) => $r->is_staff ? 'warning' : null)
                    ->url(fn (ActivityLog $r) => self::urlFor($r->user_id, $r->user_id ? null : $r->email, $r->user_id || $r->email ? null : $r->visitor_id))
                    ->tooltip('Show only this person'),
                Tables\Columns\TextColumn::make('event')->label('What')->badge()
                    ->formatStateUsing(fn (string $state) => ActivityLog::EVENTS[$state] ?? $state)
                    ->color(fn (string $state) => ActivityLog::COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('subject')->label('Item / order')->placeholder('—')->searchable()->wrap()
                    ->description(fn (ActivityLog $r) => $r->details ? collect($r->details)->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->implode(' · ') : null),
                Tables\Columns\TextColumn::make('path')->label('Page')->limit(45)->tooltip(fn (ActivityLog $r) => $r->path)->searchable()
                    ->url(fn (ActivityLog $r) => $r->method === 'GET' ? url($r->path) : null, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('device')->label('Device')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('ip')->label('IP address')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('referrer')->label('Came from')->limit(35)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('method')->label('Type')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label('Result')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('visitor_id')->label('Browser ID')->limit(10)->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('person')->label('Customer')
                    ->form([
                        Forms\Components\Select::make('user_id')->label('Customer / account')->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                                ->limit(30)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.$u->email]))
                            ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? $u->name.' · '.$u->email : null),
                        Forms\Components\TextInput::make('email')->label('or e-mail (also guests)')->email(),
                        Forms\Components\TextInput::make('visitor')->label('or browser ID')->helperText('Click a guest\'s name to fill this.'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                        ->when($data['email'] ?? null, fn ($q, $email) => $q->where(fn ($q) => $q->where('email', $email)
                            ->orWhereIn('user_id', User::where('email', $email)->select('id'))
                            ->orWhereIn('visitor_id', ActivityLog::where('email', $email)->select('visitor_id'))))
                        ->when($data['visitor'] ?? null, fn ($q, $v) => $q->where('visitor_id', $v)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['user_id'] ?? null) ? 'Customer: '.(User::find($data['user_id'])?->name ?? '#'.$data['user_id']) : null,
                        ($data['email'] ?? null) ? 'E-mail: '.$data['email'] : null,
                        ($data['visitor'] ?? null) ? 'Browser: '.substr($data['visitor'], 0, 8).'…' : null,
                    ]))),
                Tables\Filters\SelectFilter::make('event')->label('What')->multiple()->options(ActivityLog::EVENTS),
                Tables\Filters\SelectFilter::make('who')->label('Visitor type')
                    ->options(['customers' => 'Signed-in customers', 'guests' => 'Guests', 'staff' => 'Admin & staff', 'not_staff' => 'Everyone except staff'])
                    ->default('not_staff')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'customers' => $query->whereNotNull('user_id')->where('is_staff', false),
                        'guests' => $query->whereNull('user_id'),
                        'staff' => $query->where('is_staff', true),
                        'not_staff' => $query->where('is_staff', false),
                        default => $query,
                    }),
                Tables\Filters\Filter::make('when')->label('Date')
                    ->form([Forms\Components\DateTimePicker::make('from')->seconds(false), Forms\Components\DateTimePicker::make('until')->seconds(false)])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['from'] ?? null) ? 'From '.\Illuminate\Support\Carbon::parse($data['from'])->format('d M H:i') : null,
                        ($data['until'] ?? null) ? 'Until '.\Illuminate\Support\Carbon::parse($data['until'])->format('d M H:i') : null,
                    ]))),
                Tables\Filters\Filter::make('ip')->form([Forms\Components\TextInput::make('ip')->label('IP address')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['ip'] ?? null, fn ($q, $v) => $q->where('ip', $v)))
                    ->indicateUsing(fn (array $data) => ($data['ip'] ?? null) ? 'IP '.$data['ip'] : null),
                Tables\Filters\TernaryFilter::make('important')->label('Only important steps')
                    ->queries(true: fn ($query) => $query->whereNotIn('event', ['page_view', 'browse', 'admin_page']), false: fn ($query) => $query, blank: fn ($query) => $query),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\Action::make('person')->label('All movements')->icon('heroicon-o-arrows-pointing-out')->color('gray')
                    ->url(fn (ActivityLog $r) => self::urlFor($r->user_id, null, $r->user_id ? null : $r->visitor_id)),
            ])
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Visits are recorded from now on. Switch it off or set how long to keep it in Settings → Security.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
