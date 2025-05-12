<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Filament\Resources\MessageResource\RelationManagers;
use App\Models\PlatformAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = PlatformAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox'; // Biểu tượng cho "Tin Nhắn"

    protected static ?string $navigationLabel = 'Tin Nhắn';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Nếu cần form, bạn có thể để lại, nếu không thì bỏ
            ]);
    }

    public static function table(Table $table): Table
    {
        // Bỏ phần bảng danh sách các trang
        return $table
            ->columns([])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewPageMessages::route('/'), // Điều hướng thẳng đến ViewPageMessages
        ];
    }

    // Tùy chỉnh menu navigation để bỏ qua bảng và đi thẳng đến ViewPageMessages
    public static function getNavigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->url(fn () => static::getUrl('index')) // Điều hướng đến ViewPageMessages
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), static::getNavigationBadgeColor()),
        ];
    }
}