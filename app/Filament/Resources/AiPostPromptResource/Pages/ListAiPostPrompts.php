<?php

namespace App\Filament\Resources\AiPostPromptResource\Pages;

use App\Filament\Resources\AiPostPromptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiPostPrompts extends ListRecords
{
    protected static string $resource = AiPostPromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Thêm lịch lặp mới')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
