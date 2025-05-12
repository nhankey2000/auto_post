<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Xoá')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Danh mục đã được xóa thành công'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Lưu ')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Quay lại')
                ->icon('heroicon-o-x-mark'),
        ];
    } 
}
