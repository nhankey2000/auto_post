<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAccount extends CreateRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tạo tài khoản nền tảng thành công';
    }

    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tạo')
                ->submit('create'),
            Actions\CreateAction::make()
                ->label('Tạo và tạo thêm')
                ->submit('createAnother'),
            Action::make('cancel')
                ->label('Hủy')
                ->action(fn () => $this->redirect($this->getResource()::getUrl('index')))
                ->color('gray'),
        ];
    }
}