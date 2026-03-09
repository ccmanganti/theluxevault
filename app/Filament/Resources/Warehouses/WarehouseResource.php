<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Filament\Resources\Warehouses\Schemas\WarehouseInfolist;
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
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::HomeModern;
    protected static string|UnitEnum|null $navigationGroup = 'Warehouse Management';

    protected static function userOwnsWarehouse(?Warehouse $record): bool
    {
        if (! $record) {
            return true;
        }

        return (int) $record->user_id === (int) auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Warehouse Information')
                    ->description('Basic warehouse details')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('user_id')
                                ->label('Owner')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(fn () => auth()->id())
                                ->disabled(fn () => ! auth()->user()?->hasRole('Superadmin'))
                                ->dehydrated()
                                ->saveRelationshipsWhenDisabled(),

                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            TextInput::make('code')
                                ->label('Warehouse Code')
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
                        ]),
                    ])
                    ->columnSpan(2),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('contact_person')
                                ->maxLength(255),

                            TextInput::make('email')
                                ->email()
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(50),
                        ]),

                        Textarea::make('address')
                            ->rows(3),
                    ])
                    ->columnSpan(2),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columnSpan(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('Superadmin')) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin')),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('status')
                    ->label('Active')
                    ->disabled(fn (Warehouse $record): bool => ! static::userOwnsWarehouse($record)),

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
                SelectFilter::make('user_id')
                    ->label('Owner')
                    ->options(function (): array {
                        return User::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin')),

                TernaryFilter::make('status')
                    ->label('Active'),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Warehouse $record): string => static::getUrl('view', ['record' => $record])),

                    Action::make('quickEdit')
                        ->label('Quick edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->disabled(fn (Warehouse $record): bool => ! static::userOwnsWarehouse($record))
                        ->fillForm(fn (Warehouse $record): array => [
                            'name' => $record->name,
                            'code' => $record->code,
                            'contact_person' => $record->contact_person,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'status' => $record->status,
                        ])
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('code')
                                    ->label('Warehouse Code')
                                    ->maxLength(50),

                                TextInput::make('contact_person')
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(50),

                                Toggle::make('status')
                                    ->label('Active')
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->modalHeading(fn (Warehouse $record): string => "Quick edit: {$record->name}")
                        ->action(function (Warehouse $record, array $data): void {
                            $record->update([
                                'name' => $data['name'],
                                'code' => $data['code'] ?? null,
                                'contact_person' => $data['contact_person'] ?? null,
                                'email' => $data['email'] ?? null,
                                'phone' => $data['phone'] ?? null,
                                'status' => (bool) ($data['status'] ?? false),
                            ]);

                            if ($record->slug !== Str::slug($data['name'])) {
                                $record->update([
                                    'slug' => Str::slug($data['name']),
                                ]);
                            }
                        }),

                    Action::make('edit')
                        ->label('Full edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Warehouse $record): string => static::getUrl('edit', ['record' => $record]))
                        ->disabled(fn (Warehouse $record): bool => ! static::userOwnsWarehouse($record)),

                    Action::make('toggleStatus')
                        ->label(fn (Warehouse $record) => $record->status ? 'Deactivate' : 'Activate')
                        ->icon(fn (Warehouse $record) => $record->status ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->disabled(fn (Warehouse $record): bool => ! static::userOwnsWarehouse($record))
                        ->action(function (Warehouse $record): void {
                            $record->update([
                                'status' => ! $record->status,
                            ]);
                        }),

                    Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->disabled(fn (Warehouse $record): bool => ! static::userOwnsWarehouse($record))
                        ->action(fn (Warehouse $record) => $record->delete()),
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
                                ->filter(fn (Warehouse $record) => static::userOwnsWarehouse($record))
                                ->each
                                ->update(['status' => true]);
                        }),

                    BulkAction::make('deactivateSelected')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (Warehouse $record) => static::userOwnsWarehouse($record))
                                ->each
                                ->update(['status' => false]);
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canEdit($record): bool
    {
        return static::userOwnsWarehouse($record);
    }

    public static function canDelete($record): bool
    {
        return static::userOwnsWarehouse($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'view' => ViewWarehouse::route('/{record}'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
