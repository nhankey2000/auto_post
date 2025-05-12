<?php

namespace App\Filament\Resources\RepeatScheduledResource\Pages;

use App\Filament\Resources\RepeatScheduledResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepeatScheduleds extends ListRecords
{
    protected static string $resource = RepeatScheduledResource::class;

    protected function getHeaderActions(): array
    {
        return []; // ✅ Tắt hết nút trên header, bao gồm nút "Tạo"
    }
}
