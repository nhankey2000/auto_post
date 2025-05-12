<?php

namespace App\Filament\Resources\PlatformAccountResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

class TopPostsTable extends BaseWidget
{
    protected static ?string $heading = 'Bài Đăng Hiệu Suất Cao Nhất (30 Ngày Qua)';
    public $platformAccount;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Post::where('platform_account_id', $this->platformAccount->id)
                    ->where('status', 'published')
                    ->where('created_at', '>=', now()->subDays(30))
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu Đề')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->title),
                Tables\Columns\TextColumn::make('content')
                    ->label('Nội Dung')
                    ->limit(30)
                    ->formatStateUsing(fn ($state) => strip_tags($state)),
                Tables\Columns\TextColumn::make('engagements')
                    ->label('Tương Tác')
                    ->default(0) // Giả định bạn có cột engagements trong bảng posts
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày Đăng')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('engagements', 'desc')
            ->paginated([5, 10, 25]);
    }
}