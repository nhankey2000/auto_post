<?php

namespace App\Filament\Resources\ImageLibraryResource\Pages;

use App\Filament\Resources\ImageLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
class CreateImageLibrary extends CreateRecord
{
    protected static string $resource = ImageLibraryResource::class;
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
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Không tạo bản ghi mặc định, dựa hoàn toàn vào saveUploadedFileUsing
        \Illuminate\Support\Facades\Log::info('Handle record creation skipped:', ['data' => $data]);
        return new \App\Models\ImageLibrary();
    }   
    protected function getRedirectUrl(): string
{
    return $this->getResource()::getUrl('index');
}
}
