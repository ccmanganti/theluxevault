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
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

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
                                    $query = Warehouse::query()->orderBy('name');

                                    if (! auth()->user()?->hasRole('Superadmin')) {
                                        $query->where('user_id', auth()->id());
                                    }

                                    return $query->pluck('name', 'id')->toArray();
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
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('warehouse.user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Attribute')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => str($state)->replace('_', ' ')->title())
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
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_filterable')
                    ->label('Filterable')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_variant')
                    ->label('Variant')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable()
                    ->tooltip('Lower numbers are shown first.')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'text' => 'Text',
                        'number' => 'Number',
                        'select' => 'Select',
                        'boolean' => 'Boolean',
                        'textarea' => 'Textarea',
                        'date' => 'Date',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                TernaryFilter::make('is_required')
                    ->label('Required'),

                TernaryFilter::make('is_filterable')
                    ->label('Filterable'),

                TernaryFilter::make('is_variant')
                    ->label('Variant'),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Attribute $record): string => static::getUrl('view', ['record' => $record])),

                    Action::make('quickEdit')
                        ->label('Quick edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record))
                        ->fillForm(fn (Attribute $record): array => [
                            'name' => $record->name,
                            'type' => $record->type,
                            'unit' => $record->unit,
                            'sort_order' => $record->sort_order,
                            'is_required' => $record->is_required,
                            'is_filterable' => $record->is_filterable,
                            'is_variant' => $record->is_variant,
                            'status' => $record->status,
                        ])
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('type')
                                    ->required()
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'select' => 'Select',
                                        'boolean' => 'Boolean',
                                        'textarea' => 'Textarea',
                                        'date' => 'Date',
                                    ]),

                                TextInput::make('unit')
                                    ->maxLength(50),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_required')
                                    ->label('Required'),

                                Toggle::make('is_filterable')
                                    ->label('Filterable'),

                                Toggle::make('is_variant')
                                    ->label('Used for Variants'),

                                Select::make('status')
                                    ->required()
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->modalHeading(fn (Attribute $record): string => "Quick edit: {$record->name}")
                        ->action(function (Attribute $record, array $data): void {
                            $record->update([
                                'name' => $data['name'],
                                'slug' => Str::slug($data['name']),
                                'type' => $data['type'],
                                'unit' => $data['unit'] ?? null,
                                'sort_order' => $data['sort_order'] ?? 0,
                                'is_required' => (bool) ($data['is_required'] ?? false),
                                'is_filterable' => (bool) ($data['is_filterable'] ?? false),
                                'is_variant' => (bool) ($data['is_variant'] ?? false),
                                'status' => $data['status'],
                            ]);
                        }),

                    Action::make('edit')
                        ->label('Full edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Attribute $record): string => static::getUrl('edit', ['record' => $record]))
                        ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record)),

                    Action::make('activate')
                        ->label('Set active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record))
                        ->visible(fn (Attribute $record): bool => $record->status !== 'active')
                        ->action(fn (Attribute $record) => $record->update(['status' => 'active'])),

                    Action::make('deactivate')
                        ->label('Set inactive')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record))
                        ->visible(fn (Attribute $record): bool => $record->status !== 'inactive')
                        ->action(fn (Attribute $record) => $record->update(['status' => 'inactive'])),

                    Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->disabled(fn (Attribute $record): bool => ! static::userOwnsAttribute($record))
                        ->action(fn (Attribute $record) => $record->delete()),
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
                                ->filter(fn (Attribute $record) => static::userOwnsAttribute($record))
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
                                ->filter(fn (Attribute $record) => static::userOwnsAttribute($record))
                                ->each
                                ->update(['status' => 'inactive']);
                        }),

                    BulkAction::make('setFilterable')
                        ->label('Mark filterable')
                        ->icon('heroicon-o-funnel')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Attribute $record) => static::userOwnsAttribute($record))
                                ->each
                                ->update(['is_filterable' => true]);
                        }),

                    DeleteBulkAction::make(),
                ]),
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