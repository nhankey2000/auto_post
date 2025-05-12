<?php

namespace App\Filament\Resources\CategoryResource\Pages;
use Filament\Actions\Action;    
use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
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
