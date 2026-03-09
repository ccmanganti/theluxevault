<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\Pages\ViewCategory;
use App\Filament\Resources\Categories\Schemas\CategoryInfolist;
use App\Models\Category;
use App\Models\User;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ListBullet;
    protected static string|UnitEnum|null $navigationGroup = 'Warehouse Management';

    protected static function userOwnsCategory(?Category $record): bool
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
                Section::make('Category Information')
                    ->description('Basic category details')
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
                                ->preload(),

                            Select::make('parent_id')
                                ->label('Parent Category')
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: function (Builder $query, ?Category $record) {
                                        if (! auth()->user()?->hasRole('Superadmin')) {
                                            $query->whereHas('warehouse', function (Builder $warehouseQuery) {
                                                $warehouseQuery->where('user_id', auth()->id());
                                            });
                                        }

                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }
                                    }
                                )
                                ->searchable()
                                ->preload()
                                ->placeholder('No Parent Category'),

                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255),

                            Select::make('type')
                                ->required()
                                ->options([
                                    'room' => 'Room',
                                    'style' => 'Style',
                                    'extra' => 'Extra',
                                    'product_type' => 'Product Type',
                                ]),

                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0),
                        ]),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->required()
                            ->default('active')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ]),
                    ])
                    ->columnSpan(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CategoryInfolist::configure($schema);
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

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (?string $state): string => match ($state) {
                        'room' => 'info',
                        'style' => 'primary',
                        'extra' => 'success',
                        'product_type' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),

                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'room' => 'Room',
                        'style' => 'Style',
                        'extra' => 'Extra',
                        'product_type' => 'Product Type',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(function (): array {
                        $query = Category::query()->orderBy('name');

                        if (! auth()->user()?->hasRole('Superadmin')) {
                            $query->whereHas('warehouse', function (Builder $warehouseQuery) {
                                $warehouseQuery->where('user_id', auth()->id());
                            });
                        }

                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All'),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Category $record): string => static::getUrl('view', ['record' => $record])),

                    Action::make('quickEdit')
                        ->label('Quick edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->disabled(fn (Category $record): bool => ! static::userOwnsCategory($record))
                        ->fillForm(fn (Category $record): array => [
                            'name' => $record->name,
                            'type' => $record->type,
                            'status' => $record->status,
                            'sort_order' => $record->sort_order,
                            'parent_id' => $record->parent_id,
                            'description' => $record->description,
                        ])
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('type')
                                    ->required()
                                    ->options([
                                        'room' => 'Room',
                                        'style' => 'Style',
                                        'extra' => 'Extra',
                                        'product_type' => 'Product Type',
                                    ]),

                                Select::make('status')
                                    ->required()
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ]),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->options(function (?Category $record): array {
                                        $query = Category::query()->orderBy('name');

                                        if (! auth()->user()?->hasRole('Superadmin')) {
                                            $query->whereHas('warehouse', function (Builder $warehouseQuery) {
                                                $warehouseQuery->where('user_id', auth()->id());
                                            });
                                        }

                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }

                                        return $query->pluck('name', 'id')->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('No Parent Category')
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->modalHeading(fn (Category $record): string => "Quick edit: {$record->name}")
                        ->action(function (Category $record, array $data): void {
                            $record->update([
                                'name' => $data['name'],
                                'slug' => Str::slug($data['name']),
                                'type' => $data['type'],
                                'status' => $data['status'],
                                'sort_order' => $data['sort_order'] ?? 0,
                                'parent_id' => $data['parent_id'] ?? null,
                                'description' => $data['description'] ?? null,
                            ]);
                        }),

                    Action::make('edit')
                        ->label('Full edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Category $record): string => static::getUrl('edit', ['record' => $record]))
                        ->disabled(fn (Category $record): bool => ! static::userOwnsCategory($record)),

                    Action::make('activate')
                        ->label('Set active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->disabled(fn (Category $record): bool => ! static::userOwnsCategory($record))
                        ->visible(fn (Category $record): bool => $record->status !== 'active')
                        ->action(fn (Category $record) => $record->update(['status' => 'active'])),

                    Action::make('deactivate')
                        ->label('Set inactive')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->disabled(fn (Category $record): bool => ! static::userOwnsCategory($record))
                        ->visible(fn (Category $record): bool => $record->status !== 'inactive')
                        ->action(fn (Category $record) => $record->update(['status' => 'inactive'])),

                    Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->disabled(fn (Category $record): bool => ! static::userOwnsCategory($record))
                        ->action(fn (Category $record) => $record->delete()),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('setActive')
                        ->label('Set active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Category $record) => static::userOwnsCategory($record))
                                ->each
                                ->update(['status' => 'active']);
                        }),

                    BulkAction::make('setInactive')
                        ->label('Set inactive')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Category $record) => static::userOwnsCategory($record))
                                ->each
                                ->update(['status' => 'inactive']);
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canEdit($record): bool
    {
        return static::userOwnsCategory($record);
    }

    public static function canDelete($record): bool
    {
        return static::userOwnsCategory($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'view' => ViewCategory::route('/{record}'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}