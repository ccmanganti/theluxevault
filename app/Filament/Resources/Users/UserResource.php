<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use UnitEnum;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::UserGroup;
    protected static string | UnitEnum | null $navigationGroup = 'Users & Permissions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->description('Basic user account details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            TextInput::make('password')
                                ->password()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->dehydrated(fn ($state) => filled($state))
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ])->columnSpan(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouses_count')
                    ->counts('warehouses')
                    ->label('Warehouses')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencilSquare),
                
                Impersonate::make()
                ->visible(fn (User $record) => auth()->id() !== $record->id
                    && auth()->user()?->hasRole('Superadmin'))
                ->redirectTo('/admin')
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}