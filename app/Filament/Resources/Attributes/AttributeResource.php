<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\Pages\ViewAttribute;
use App\Filament\Resources\Attributes\Schemas\AttributeInfolist;
use App\Models\Attribute;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Tag;
    protected static string|UnitEnum|null $navigationGroup = 'Warehouse Management';

    protected static function userOwnsAttribute(?Attribute $record): bool
    {
        if (! $record) {
            return true;
        }

        return (int) $record->warehouse?->user_id === (int) auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attribute Information')
                    ->description('Define a product property such as color, size, weight, material, or expiration date.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('warehouse_id')
                                ->label('Warehouse')
                                ->options(function (): array {
                                    return Warehouse::query()
                                        ->where('user_id', auth()->id())
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required()
                                ->searchable()
                                ->preload()
                                ->helperText('Choose the warehouse that owns this attribute.'),

                            TextInput::make('name')
                                ->label('Attribute Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: Color, Size, Weight')
                                ->helperText('This is the display name users will see.')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('System-friendly version of the name, used internally. Example: color, size, weight.'),

                            Select::make('type')
                                ->label('Attribute Type')
                                ->required()
                                ->default('text')
                                ->options([
                                    'text' => 'Text',
                                    'number' => 'Number',
                                    'select' => 'Select',
                                    'boolean' => 'Boolean',
                                    'textarea' => 'Textarea',
                                    'date' => 'Date',
                                ])
                                ->helperText('Choose the kind of value this attribute should store.'),

                            TextInput::make('unit')
                                ->label('Unit')
                                ->maxLength(50)
                                ->placeholder('cm, in, kg, lb')
                                ->helperText('Optional. Use this for measurable values like weight, height, or length.'),

                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->helperText('Lower numbers appear first when attributes are listed.'),
                        ]),
                    ]),

                Section::make('Attribute Options')
                    ->description('Control how this attribute behaves in products, filters, and variants.')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_required')
                                ->label('Required')
                                ->default(false)
                                ->helperText('If enabled, products must have a value for this attribute.'),

                            Toggle::make('is_filterable')
                                ->label('Filterable')
                                ->default(true)
                                ->helperText('If enabled, this attribute can be used in product filters or search.'),

                            Toggle::make('is_variant')
                                ->label('Used for Variants')
                                ->default(false)
                                ->helperText('Enable this if the attribute creates product variations, like size or color.'),
                        ]),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->default('active')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->helperText('Only active attributes should be available for normal use.'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttributeInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('Superadmin')) {
            $query->whereHas('warehouse', function (Builder $warehouseQuery) {
                $warehouseQuery->where('user_id', auth()->id());
            });
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.user.name')
                    ->label('Owner')
                    ->searchable()
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin'))
                    ->placeholder('-'),

                TextColumn::make('name')
                    ->label('Attribute')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->description(fn ($record) => match ($record->type) {
                        'text' => 'Plain text value',
                        'number' => 'Numeric value',
                        'select' => 'Predefined option',
                        'boolean' => 'Yes / No value',
                        'textarea' => 'Long text value',
                        'date' => 'Date value',
                        default => null,
                    }),

                TextColumn::make('unit')
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),

                IconColumn::make('is_filterable')
                    ->label('Filterable')
                    ->boolean(),

                IconColumn::make('is_variant')
                    ->label('Variant')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable()
                    ->tooltip('Lower numbers are shown first.'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record)),
            ]);
    }

    public static function canEdit($record): bool
    {
        return static::userOwnsAttribute($record);
    }

    public static function canDelete($record): bool
    {
        return static::userOwnsAttribute($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'view' => ViewAttribute::route('/{record}'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}