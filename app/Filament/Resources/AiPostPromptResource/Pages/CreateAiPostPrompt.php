<?php

namespace App\Filament\Resources\AiPostPromptResource\Pages;

use App\Filament\Resources\AiPostPromptResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
class CreateAiPostPrompt extends CreateRecord
{
    protected static string $resource = AiPostPromptResource::class;
  
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
