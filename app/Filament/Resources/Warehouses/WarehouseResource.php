<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Filament\Resources\Warehouses\Schemas\WarehouseInfolist;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ToggleColumn;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::HomeModern;
    protected static string|UnitEnum|null $navigationGroup = 'Warehouse Management';

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
                                ->disabled()
                                ->dehydrated()
                                ->saveRelationshipsWhenDisabled(),

                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) =>
                                    $set('slug', Str::slug($state))
                                ),

                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            TextInput::make('code')
                                ->label('Warehouse Code')
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),

                        ]),
                    ])->columnSpan(2),

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

                    ])->columnSpan(2),

                Section::make('Additional Information')
                    ->schema([

                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),

                    ])->columnSpan(2),
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
            ->columns([
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->visible(fn () => auth()->user()?->hasRole('Superadmin'))
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),

                ToggleColumn::make('status')
                    ->label('Active')
                    ->disabled(fn (Warehouse $record): bool => (int) $record->user_id !== (int) auth()->id()),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->disabled(fn (Warehouse $record): bool => (int) $record->user_id !== (int) auth()->id()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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