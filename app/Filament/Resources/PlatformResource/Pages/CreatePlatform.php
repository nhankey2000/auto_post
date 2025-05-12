<?php

namespace App\Filament\Resources\PlatformResource\Pages;

use App\Filament\Resources\PlatformResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatform extends CreateRecord
{
    protected static string $resource = PlatformResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); // Chuyển hướng về trang danh sách sau khi tạo
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tạo nền tảng thành công'; // Thông báo khi tạo thành công
    }

    // Tùy chỉnh các nút hành động
    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tạo') // Nút "Tạo"
                ->submit('create'),
            Actions\CreateAction::make()
                ->label('Tạo và tạo thêm') // Nút "Tạo và tạo thêm"
                ->submit('createAnother'),
            Action::make('cancel')
                ->label('Hủy') // Nút "Hủy"
                ->action(fn () => $this->redirect($this->getResource()::getUrl('index')))
                ->color('gray'), // Tùy chọn màu cho nút Hủy
        ];
    }
}