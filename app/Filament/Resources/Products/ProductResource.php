<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::RectangleGroup;
    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Management';

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
                                    $query = Warehouse::query()->orderBy('name');

                                    if (! auth()->user()?->hasRole('Superadmin')) {
                                        $query->where('user_id', auth()->id());
                                    }

                                    return $query->pluck('name', 'id')->toArray();
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
                            ->acceptedFileTypes(['image/*'])
                            ->extraInputAttributes([
                                'accept' => 'image/*',
                                'capture' => 'environment', // rear camera on supported phones
                            ])
                            ->maxFiles(10)
                            ->imagePreviewHeight('150')
                            ->panelLayout('grid')
                            ->reorderable()
                            ->preserveFilenames()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '4:3',
                            ])
                            ->helperText('On supported phones, this can open the camera directly.')
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
            ->defaultSort('name')
            ->columns([
                TextColumn::make('warehouse.user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->placeholder('-')
                    ->toggleable(),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record)),

                TextColumn::make('is_featured')
                    ->label('Featured')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('selling_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock')
                    ->label('Quantity')
                    ->sortable(),

                TextColumn::make('reserved_stock')
                    ->label('Reserved')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('damaged_stock')
                    ->label('Damaged')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(function (): array {
                        $query = Warehouse::query()->orderBy('name');

                        if (! auth()->user()?->hasRole('Superadmin')) {
                            $query->where('user_id', auth()->id());
                        }

                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category')
                    ->label('Category')
                    ->relationship(
                        'categories',
                        'name',
                        fn (Builder $query) => $query->orderBy('name')
                    )
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Filter::make('in_stock')
                    ->label('In stock only')
                    ->query(fn (Builder $query): Builder => $query->where('stock', '>', 0)),

                Filter::make('out_of_stock')
                    ->label('Out of stock')
                    ->query(fn (Builder $query): Builder => $query->where('stock', '<=', 0)),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                ActionGroup::make([
                    Action::make('quickEdit')
                        ->label('Quick edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->fillForm(fn (Product $record): array => [
                            'name' => $record->name,
                            'brand' => $record->brand,
                            'selling_price' => $record->selling_price,
                            'stock' => $record->stock,
                            'sku' => $record->sku,
                            'is_active' => $record->is_active,
                            'is_featured' => $record->is_featured,
                        ])
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('brand')
                                    ->maxLength(255),

                                TextInput::make('selling_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('stock')
                                    ->numeric(),

                                TextInput::make('sku')
                                    ->maxLength(100),

                                Grid::make(2)->schema([
                                    Toggle::make('is_active')
                                        ->label('Active'),

                                    Toggle::make('is_featured')
                                        ->label('Featured'),
                                ])->columnSpanFull(),
                            ]),
                        ])
                        ->modalHeading(fn (Product $record): string => "Quick edit: {$record->name}")
                        ->action(function (Product $record, array $data): void {
                            $record->update([
                                'name' => $data['name'],
                                'brand' => $data['brand'] ?? null,
                                'selling_price' => $data['selling_price'] ?? null,
                                'stock' => $data['stock'] ?? 0,
                                'sku' => $data['sku'] ?? null,
                                'is_active' => (bool) ($data['is_active'] ?? false),
                                'is_featured' => (bool) ($data['is_featured'] ?? false),
                            ]);
                        }),

                    Action::make('edit')
                        ->label('Full edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Product $record): string => static::getUrl('edit', ['record' => $record]))
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record)),

                    Action::make('toggleVisibility')
                        ->label(fn (Product $record) => $record->is_active ? 'Hide' : 'Show')
                        ->icon(fn (Product $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->action(function (Product $record): void {
                            $record->update([
                                'is_active' => ! $record->is_active,
                            ]);
                        }),

                    Action::make('adjustPrice')
                        ->label('Adjust price')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->schema([
                            TextInput::make('selling_price')
                                ->label('New price')
                                ->numeric()
                                ->required(),
                        ])
                        ->fillForm(fn (Product $record): array => [
                            'selling_price' => $record->selling_price,
                        ])
                        ->action(function (Product $record, array $data): void {
                            $record->update([
                                'selling_price' => $data['selling_price'],
                            ]);
                        }),

                    Action::make('adjustStock')
                        ->label('Adjust stock')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->disabled(fn (Product $record): bool => ! static::userOwnsProduct($record))
                        ->schema([
                            TextInput::make('stock')
                                ->label('New stock quantity')
                                ->numeric()
                                ->required(),
                        ])
                        ->fillForm(fn (Product $record): array => [
                            'stock' => $record->stock,
                        ])
                        ->action(function (Product $record, array $data): void {
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activateSelected')
                        ->label('Activate selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Product $record) => static::userOwnsProduct($record))
                                ->each
                                ->update(['is_active' => true]);
                        }),

                    BulkAction::make('deactivateSelected')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Product $record) => static::userOwnsProduct($record))
                                ->each
                                ->update(['is_active' => false]);
                        }),

                    BulkAction::make('markFeatured')
                        ->label('Mark featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Product $record) => static::userOwnsProduct($record))
                                ->each
                                ->update(['is_featured' => true]);
                        }),

                    DeleteBulkAction::make()
                        ->visible(fn (): bool => true),
                ]),
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
