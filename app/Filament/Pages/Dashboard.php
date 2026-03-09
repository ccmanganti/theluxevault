<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'My Warehouse';

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Hi ' . auth()->user()->name;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filters')
                ->description('Search, filter, and sort inventory')
                ->icon('heroicon-o-funnel')
                ->collapsible()
                ->collapsed()
                ->persistCollapsed()
                ->columnSpanFull()
                ->schema([
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(function (): array {
                            $query = Warehouse::query()->orderBy('name');

                            if (! auth()->user()?->hasRole('Superadmin')) {
                                $query->where('user_id', auth()->id());
                            }

                            return $query->pluck('name', 'id')->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->live(),

                    TextInput::make('search')
                        ->label('Search')
                        ->placeholder('Search by name, brand, SKU, barcode...')
                        ->live(onBlur: false),

                    Select::make('category_id')
                        ->label('Category')
                        ->options(function (callable $get): array {
                            $warehouseId = $get('warehouse_id');

                            if (! $warehouseId) {
                                return [];
                            }

                            return Category::query()
                                ->where('warehouse_id', $warehouseId)
                                ->orderBy('type')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Category $category) => [
                                    $category->id => $category->name . ' (' . str($category->type)->replace('_', ' ')->title() . ')',
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->live(),

                    Select::make('category_type')
                        ->label('Category Type')
                        ->options([
                            'room' => 'Room',
                            'style' => 'Style',
                            'product_type' => 'Product Type',
                            'extra' => 'Extra',
                        ])
                        ->live(),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'featured' => 'Featured',
                            'in_stock' => 'In Stock',
                            'out_of_stock' => 'Out of Stock',
                        ])
                        ->live(),

                    Select::make('sort')
                        ->label('Sort')
                        ->options([
                            'name_asc' => 'Name A–Z',
                            'name_desc' => 'Name Z–A',
                            'price_low_high' => 'Price Low to High',
                            'price_high_low' => 'Price High to Low',
                            'stock_low_high' => 'Stock Low to High',
                            'stock_high_low' => 'Stock High to Low',
                            'latest' => 'Newest First',
                            'oldest' => 'Oldest First',
                        ])
                        ->default('name_asc')
                        ->live(),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ]),
        ]);
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\InventoryGallery::class,
        ];
    }
}