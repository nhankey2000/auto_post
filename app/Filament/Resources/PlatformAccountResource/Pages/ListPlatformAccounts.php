<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAccounts extends ListRecords
{
    protected static string $resource = PlatformAccountResource::class;

    // ✅ Tuỳ chỉnh nút "Thêm mới"
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Thêm tài khoản mới')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
