<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\User;
use App\Support\StaffAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Admin-only: staff logins and what each one may see and change in the admin panel. */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Staff & permissions';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'Staff & permissions';

    protected static ?int $navigationSort = -2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record->getKey() !== auth()->id();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_staff', true));
    }

    public static function form(Form $form): Form
    {
        $groups = collect(StaffAccess::AREAS)->groupBy(fn ($a) => $a[1], preserveKeys: true);

        return $form->schema([
            Forms\Components\Section::make('Login')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('password')->password()->revealable()
                    ->dehydrated(fn ($state) => filled($state))->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : 'At least 8 characters.')->minLength(8),
                Forms\Components\Radio::make('access')->label('Access')->required()->live()->default('staff')->columnSpanFull()
                    ->options([
                        'admin' => 'Admin — full access to every area, including Staff & permissions',
                        'staff' => 'Staff — only the areas ticked below',
                        'none' => 'No access (login switched off)',
                    ])
                    ->disableOptionWhen(fn (string $value, ?User $record) => $record?->getKey() === auth()->id() && $value !== 'admin')
                    ->helperText(fn (?User $record) => $record?->getKey() === auth()->id() ? 'You cannot remove your own admin access.' : null),
            ]),
            Forms\Components\Section::make('What this person can do')
                ->description('Tick per area. "Edit" includes changing order status, hand-over and updating gold rates; "View" alone is read-only.')
                ->visible(fn (Forms\Get $get) => $get('access') === 'staff')
                ->headerActions([])
                ->schema([
                    Forms\Components\Placeholder::make('admin_only')->label('Always admin only')
                        ->content(implode(' · ', StaffAccess::ADMIN_ONLY)),
                    Forms\Components\Select::make('preset')->label('Start from a job role (optional)')->dehydrated(false)->live()
                        ->options(collect(StaffAccess::PRESETS)->map(fn ($p) => $p[0]))
                        ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                            foreach (array_keys(StaffAccess::AREAS) as $area) {
                                $set("permissions.{$area}", StaffAccess::PRESETS[$state][1][$area] ?? []);
                            }
                        }),
                    ...$groups->map(fn ($areas, $group) => Forms\Components\Fieldset::make($group)->columns(1)->schema(
                        collect($areas)->map(fn ($a, $key) => Forms\Components\CheckboxList::make("permissions.{$key}")->label($a[0])
                            ->options(StaffAccess::ACTIONS)->columns(4)->gridDirection('row'))->values()->all()
                    ))->values()->all(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->description(fn (User $record) => $record->email),
            Tables\Columns\TextColumn::make('role')->badge()
                ->state(fn (User $record) => $record->is_admin ? 'Admin' : ($record->is_staff ? 'Staff' : 'No access'))
                ->color(fn (string $state) => ['Admin' => 'warning', 'Staff' => 'info'][$state] ?? 'gray'),
            Tables\Columns\TextColumn::make('areas')->label('Areas')->wrap()
                ->state(fn (User $record) => $record->is_admin ? 'Everything' : (collect($record->permissions ?? [])->filter()->keys()
                    ->map(fn ($k) => StaffAccess::AREAS[$k][0] ?? $k)->map(fn ($l) => str($l)->before(' (')->toString())->implode(', ') ?: '—')),
            Tables\Columns\TextColumn::make('updated_at')->label('Changed')->since(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    /** Form "access" choice ↔ is_admin / is_staff columns (not mass-assignable on purpose). */
    public static function toColumns(array $data): array
    {
        $access = $data['access'] ?? 'staff';
        unset($data['access'], $data['preset']);
        $data['is_admin'] = $access === 'admin';
        $data['is_staff'] = $access === 'staff';
        $data['permissions'] = $access === 'staff' ? array_filter($data['permissions'] ?? []) : null;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
