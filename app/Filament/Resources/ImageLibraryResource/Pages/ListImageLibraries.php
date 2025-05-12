<?php

namespace App\Filament\Resources\ImageLibraryResource\Pages;

use App\Filament\Resources\ImageLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImageLibraries extends ListRecords
{
    protected static string $resource = ImageLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Thêm hình ảnh mới')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
