<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\CertificateLabResource\Pages;
use App\Models\CertificateLab;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Master list of diamond certificate labs, each with a small logo, picked on products. */
class CertificateLabResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'certificate_labs';

    protected static ?string $model = CertificateLab::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Certificate labs';

    protected static ?string $modelLabel = 'certificate lab';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema(self::fields());
    }

    /** Also used to add a lab straight from the product form. */
    public static function fields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label('Lab name')->required()->maxLength(120)->placeholder('Gemological Institute of America'),
            Forms\Components\TextInput::make('short_name')->label('Short name')->maxLength(32)->placeholder('GIA')->helperText('Shown next to the logo on the product page.'),
            Forms\Components\FileUpload::make('logo')->label('Small logo')->image()->disk('public')->directory('labs')->maxSize(1024)
                ->imageResizeMode('contain')->imageResizeTargetWidth('240')->imageResizeTargetHeight('120')
                ->helperText('PNG, SVG or WebP, ideally on a transparent background. Shown small (about 24 px high).')->columnSpanFull(),
            Forms\Components\TextInput::make('website')->url()->maxLength(255)->placeholder('https://www.gia.edu'),
            Forms\Components\TextInput::make('verify_url')->label('Report-check link')->maxLength(255)
                ->placeholder('https://www.gia.edu/report-check?reportno={number}')
                ->helperText('Optional. Put {number} where the certificate number goes; customers get a "Verify" link.'),
            Forms\Components\TextInput::make('sort_order')->label('Order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Active')->default(true)->inline(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label('')->disk('public')->height(24),
                Tables\Columns\TextColumn::make('short_name')->label('Short name')->badge()->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('verify_url')->label('Report-check link')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('products_count')->label('Products')->counts('products')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()
                ->disabled(fn (CertificateLab $record) => $record->products()->exists())
                ->tooltip(fn (CertificateLab $record) => $record->products()->exists() ? 'Used on products — switch it off instead.' : null)]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCertificateLabs::route('/')];
    }
}
