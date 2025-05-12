<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    // ✅ Đổi tiêu đề trang danh sách
    public function getTitle(): string
    {
        return 'Danh sách bài viết';
    }

    // ✅ Đổi tên và style nút "Tạo mới"
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tạo bài viết mới')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
