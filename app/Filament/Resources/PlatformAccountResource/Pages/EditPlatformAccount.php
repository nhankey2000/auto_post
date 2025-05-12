<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAccount extends EditRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Xoá')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Tài khoản đã được xóa thành công'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Lưu')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Huỷ')
                ->icon('heroicon-o-x-mark'),
        ];
    }
}
