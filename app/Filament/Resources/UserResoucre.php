<?php

namespace App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;

class UserResource extends Resource
{

    protected static ?string $model = User::class;


    protected static ?string $navigationIcon = 'heroicon-o-users';


    protected static ?string $navigationLabel = 'Quản Lý Người Dùng';


    public static function canCreate(): bool
    {
        $user = Filament::auth()->user();
        Log::info('User roles:', $user ? $user->roles->pluck('name')->toArray() : []);
        return Filament::auth()->check() && $user && $user->hasRole('admin');
    }


    public static function canEdit(Model $record): bool
    {
        $user = Filament::auth()->user();
        return Filament::auth()->check() && $user && $user->hasRole('admin');
    }


    public static function canDelete(Model $record): bool
    {
        $user = Filament::auth()->user();
        return Filament::auth()->check() && $user && $user->hasRole('admin');
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Tên')
                    ->required()
                    ->maxLength(255)
                    ->extraAttributes([
                        'class' => 'w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all',
                    ]),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->extraAttributes([
                        'class' => 'w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all',
                    ]),
                TextInput::make('password')
                    ->label('Mật Khẩu')
                    ->password()
                    ->required()
                    ->maxLength(255)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                    ->extraAttributes([
                        'class' => 'w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all',
                    ]),
                DateTimePicker::make('email_verified_at')
                    ->label('Ngày Xác Minh Email')
                    ->extraAttributes([
                        'class' => 'w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all',
                    ]),
                Select::make('roles')
                    ->label('Vai Trò')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->required()
                    ->extraAttributes([
                        'class' => 'w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all',
                    ]),
            ])
            ->columns(2) // Sử dụng grid layout với 2 cột
            ->extraAttributes([
                'class' => 'max-w-2xl mx-auto p-6 bg-white rounded-xl shadow-lg',
            ]);
    }

    /**
     * Define the table configuration for the resource.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label('Ngày Xác Minh')
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->label('Ngày Tạo')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}