<?php

namespace App\Filament\Resources\PlatformResource\Pages;

use App\Filament\Resources\PlatformResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatforms extends ListRecords
{
    protected static string $resource = PlatformResource::class;

    // ✅ Tuỳ chỉnh nút tạo mới
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tạo nền tảng mới')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
