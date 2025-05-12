<?php

namespace App\Filament\Resources\AiPostPromptResource\Pages;

use App\Filament\Resources\AiPostPromptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiPostPrompt extends EditRecord
{
    protected static string $resource = AiPostPromptResource::class;
    
    public function getTitle(): string
    {
        return 'Chỉnh sửa lịch đăng';
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Xoá lịch ')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Lịch đã được xoá thành công'),
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
