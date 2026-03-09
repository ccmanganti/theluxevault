<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::RectangleGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Warehouse Management';

    protected static function userOwnsProduct(?Product $record): bool
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
                Section::make('Product Information')
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
                                ->live(),

                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                            TextInput::make('brand')
                                ->label('Brand Name')
                                ->maxLength(255),

                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            TextInput::make('sku')
                                ->label('SKU')
                                ->maxLength(100)
                                ->unique(ignoreRecord: true),

                            TextInput::make('barcode')
                                ->maxLength(100),
                        ]),
                    ]),

                Section::make('Custom Attributes')
                    ->description('Only custom attributes from the selected warehouse will appear here.')
                    ->hidden(fn (Get $get) => blank($get('warehouse_id')))
                    ->schema([
                        Repeater::make('productAttributes')
                            ->relationship('productAttributes')
                            ->schema([
                                Select::make('attribute_id')
                                    ->label('Attribute')
                                    ->options(function (Get $get): array {
                                        $warehouseId = $get('../../warehouse_id');

                                        if (! $warehouseId) {
                                            return [];
                                        }

                                        return Attribute::query()
                                            ->where('warehouse_id', $warehouseId)
                                            ->where('is_system', false)
                                            ->where('status', 'active')
                                            ->whereHas(
                                                'warehouse',
                                                fn (Builder $warehouseQuery) => $warehouseQuery->where('user_id', auth()->id())
                                            )
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                TextInput::make('value')
                                    ->label('Value')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Attribute'),
                    ]),

                Section::make('Categories')
                    ->schema([
                        Select::make('categories')
                            ->relationship(
                                'categories',
                                'name',
                                modifyQueryUsing: function (Builder $query) {
                                    $query->whereHas('warehouse', function (Builder $warehouseQuery) {
                                        $warehouseQuery->where('user_id', auth()->id());
                                    });
                                }
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                if (auth()->user()?->hasRole('Superadmin')) {
                                    return ($record->warehouse?->user?->name ?? 'Unknown User') . ': ' . $record->name;
                                }

                                return $record->name;
                            }),
                    ]),

                Section::make('Pricing & Inventory')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('cost_price')
                                ->numeric(),

                            TextInput::make('selling_price')
                                ->numeric(),

                            TextInput::make('compare_at_price')
                                ->numeric(),

                            TextInput::make('stock')
                                ->numeric(),

                            TextInput::make('reserved_stock')
                                ->numeric(),

                            TextInput::make('damaged_stock')
                                ->numeric(),
                        ]),
                    ]),

                Section::make('Product Details')
                    ->schema([
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('details')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Images')
                    ->schema([
                        FileUpload::make('images')
                            ->multiple()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->image()
                            ->maxFiles(10)
                            ->imagePreviewHeight('100')
                            ->panelLayout('grid')
                            ->reorderable()
                            ->preserveFilenames()
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
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
                    ->sortable()
                    ->placeholder('-')
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin')),

                ImageColumn::make('images')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->size(50)
                    ->getStateUsing(function (Product $record) {
                        $images = $record->images;

                        if (is_array($images) && count($images)) {
                            return $images[0];
                        }

                        return null;
                    }),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('brand')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record)),

                TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Quantity')
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Product $record): string => static::getUrl('edit', ['record' => $record]))
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record)),

                    Action::make('toggleVisibility')
                        ->label(fn (Product $record) => $record->is_active ? 'Hide' : 'Show')
                        ->icon(fn (Product $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->action(function (Product $record) {
                            $record->update([
                                'is_active' => ! $record->is_active,
                            ]);
                        }),

                    Action::make('adjustPrice')
                        ->label('Adjust price')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->form([
                            TextInput::make('selling_price')
                                ->label('New price')
                                ->numeric()
                                ->required(),
                        ])
                        ->fillForm(fn (Product $record): array => [
                            'selling_price' => $record->selling_price,
                        ])
                        ->action(function (Product $record, array $data) {
                            $record->update([
                                'selling_price' => $data['selling_price'],
                            ]);
                        }),

                    Action::make('adjustStock')
                        ->label('Adjust stock')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->form([
                            TextInput::make('stock')
                                ->label('New stock quantity')
                                ->numeric()
                                ->required(),
                        ])
                        ->fillForm(fn (Product $record): array => [
                            'stock' => $record->stock,
                        ])
                        ->action(function (Product $record, array $data) {
                            $record->update([
                                'stock' => $data['stock'],
                            ]);
                        }),

                    Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->action(fn (Product $record) => $record->delete()),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->size('sm')
                    ->color('gray'),
            ]);
    }

    public static function canEdit($record): bool
    {
        return static::userOwnsProduct($record);
    }

    public static function canDelete($record): bool
    {
        return static::userOwnsProduct($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}