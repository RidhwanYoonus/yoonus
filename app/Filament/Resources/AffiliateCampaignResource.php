<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Models\Affiliate;
use App\Models\AffiliateCampaign;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Boosters (× commission for a period / category) and targets (bonus for reaching a goal). */
class AffiliateCampaignResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'affiliate_campaigns';

    protected static ?string $model = AffiliateCampaign::class;

    protected static ?string $slug = 'affiliate-campaigns';

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Campaigns & targets';

    protected static ?string $modelLabel = 'campaign';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(120)->placeholder('Eid double commission'),
            Forms\Components\ToggleButtons::make('type')->required()->inline()->live()->default('booster')
                ->options(['booster' => 'Commission booster', 'target' => 'Target bonus'])
                ->helperText(fn (Forms\Get $get) => $get('type') === 'target'
                    ? 'One-off bonus when an affiliate reaches the goal with delivered orders placed in the period.'
                    : 'Commission × multiplier on orders placed in the period.'),
            Forms\Components\DateTimePicker::make('starts_at')->required()->default(now()->startOfDay()),
            Forms\Components\DateTimePicker::make('ends_at')->required()->after('starts_at')->default(now()->addDays(14)->endOfDay()),
            Forms\Components\Select::make('affiliate_ids')->label('Affiliates')->multiple()->searchable()->placeholder('All affiliates')
                ->options(fn () => Affiliate::with('user')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->user?->name]))->columnSpanFull(),
            Forms\Components\TextInput::make('multiplier')->label('Multiply commission by')->numeric()->minValue(1)->maxValue(10)->step(0.1)->default(2)->prefix('×')
                ->visible(fn (Forms\Get $get) => $get('type') !== 'target')->required(fn (Forms\Get $get) => $get('type') !== 'target'),
            Forms\Components\Select::make('category_ids')->label('Only these categories')->multiple()->placeholder('All categories')->options(fn () => Category::pluck('name', 'id'))
                ->visible(fn (Forms\Get $get) => $get('type') !== 'target'),
            Forms\Components\ToggleButtons::make('item_type')->label('Items')->inline()->default('all')->columnSpanFull()
                ->options(AffiliateCampaign::ITEM_TYPES)->visible(fn (Forms\Get $get) => $get('type') !== 'target'),
            Forms\Components\ToggleButtons::make('target_metric')->label('Goal measured in')->inline()->default('pieces')
                ->options(['pieces' => 'Pieces sold', 'sales' => 'Sales AED (excl. VAT)'])->visible(fn (Forms\Get $get) => $get('type') === 'target'),
            Forms\Components\TextInput::make('target_value')->label('Goal')->numeric()->minValue(1)
                ->visible(fn (Forms\Get $get) => $get('type') === 'target')->required(fn (Forms\Get $get) => $get('type') === 'target'),
            Forms\Components\TextInput::make('bonus_amount')->label('Bonus')->numeric()->minValue(0)->prefix('AED')
                ->visible(fn (Forms\Get $get) => $get('type') === 'target')->required(fn (Forms\Get $get) => $get('type') === 'target'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
            Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => $state === 'target' ? 'Target bonus' : 'Booster'),
            Tables\Columns\TextColumn::make('rule')->state(fn (AffiliateCampaign $r) => $r->type === 'target'
                ? ($r->target_metric === 'sales' ? 'AED '.number_format((float) $r->target_value) : number_format((float) $r->target_value).' pcs').' → AED '.$r->bonus_amount
                : '× '.rtrim(rtrim((string) $r->multiplier, '0'), '.').' on '.$r->coverageLabel())->wrap(),
            Tables\Columns\TextColumn::make('starts_at')->label('From')->date('d M Y'),
            Tables\Columns\TextColumn::make('ends_at')->label('Until')->date('d M Y'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => AffiliateCampaignResource\Pages\ManageAffiliateCampaigns::route('/')];
    }
}
