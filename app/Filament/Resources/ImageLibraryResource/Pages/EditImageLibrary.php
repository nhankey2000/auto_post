<?php

namespace App\Filament\Resources\ImageLibraryResource\Pages;

use App\Filament\Resources\ImageLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Storage;

class EditImageLibrary extends EditRecord
{
    protected static string $resource = ImageLibraryResource::class;

    public function getTitle(): string
    {
        return 'Chỉnh sửa hình ảnh';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section: Media Preview and Upload
                Forms\Components\Section::make('Xem và Cập Nhật Media')
                    ->description('Xem media hiện tại và tải lên media mới nếu cần.')
                    ->schema([
                        Forms\Components\Grid::make(1)
                            ->schema([
                                // Hiển thị media hiện tại
                                Forms\Components\Placeholder::make('media_preview')
                                    ->label('Media Hiện Tại')
                                    ->content(function ($record) {
                                        $mediaPath = $record->item;
                                        $mediaType = strtolower($record->type);
                                        $mediaUrl = null;
                                        $fileExists = false;

                                        if (is_string($mediaPath) && !empty($mediaPath)) {
                                            $mediaUrl = Storage::disk('public')->url($mediaPath);
                                            $filePath = public_path('storage/' . $mediaPath);
                                            $fileExists = file_exists($filePath);
                                        }

                                        \Illuminate\Support\Facades\Log::info('Media preview in edit form', [
                                            'mediaPath' => $mediaPath,
                                            'mediaType' => $mediaType,
                                            'mediaUrl' => $mediaUrl,
                                            'fileExists' => $fileExists,
                                        ]);

                                        if ($mediaType === 'image' && $mediaUrl && $fileExists) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<img src="' . $mediaUrl . '" alt="Xem trước ảnh" class="object-cover rounded-lg shadow-sm" style="width: 120px; height: 120px;">'
                                            );
                                        } elseif ($mediaType === 'video' && $mediaUrl && $fileExists) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<video width="120" height="120" controls class="object-cover rounded-lg shadow-sm"><source src="' . $mediaUrl . '" type="video/mp4">Trình duyệt của bạn không hỗ trợ thẻ video.</video>'
                                            );
                                        } elseif ($mediaUrl && !$fileExists) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="text-red-500 text-xs">File không tồn tại: ' . $mediaPath . '</span>'
                                            );
                                        }

                                        return new \Illuminate\Support\HtmlString(
                                            '<img src="https://via.placeholder.com/120" alt="Ảnh mặc định" class="object-cover rounded-lg shadow-sm" style="width: 120px; height: 120px;">'
                                        );
                                    }),

                                // Danh mục
                                Forms\Components\Select::make('category_id')
                                    ->label('Danh Mục Media')
                                    ->relationship('category', 'category')
                                    ->required()
                                    ->placeholder('Chọn danh mục')
                                    ->helperText('Chọn danh mục cho media (ví dụ: Du lịch, Ẩm thực).'),

                                // Tải lên media mới
                                Forms\Components\FileUpload::make('media')
                                    ->label('Tải Lên Media Mới')
                                    ->multiple()
                                    ->directory(function ($state) {
                                        $file = $state[0] ?? null;
                                        if ($file && str_starts_with($file->getMimeType(), 'video/')) {
                                            return 'videos';
                                        }
                                        return 'images';
                                    })
                                    ->visibility('public')
                                    ->maxFiles(5)
                                    ->maxSize(51200) // 50MB
                                    ->helperText('Chọn tối đa 5 file (hình ảnh hoặc video), mỗi file tối đa 50MB.')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mpeg', 'video/webm'])
                                    ->preserveFilenames()
                                    ->saveUploadedFileUsing(function ($state, $get, $record) {
                                        \Illuminate\Support\Facades\Log::info('Media FileUpload state (edit):', [
                                            'state' => $state,
                                            'count' => is_array($state) ? count($state) : 0,
                                            'category_id' => $get('category_id'),
                                            'record_id' => $record->id,
                                        ]);

                                        if (!is_array($state) || empty($state)) {
                                            \Illuminate\Support\Facades\Log::warning('No new media uploaded in edit', ['state' => $state]);
                                            return null;
                                        }

                                        // Xóa media cũ nếu có
                                        if ($record->item && Storage::disk('public')->exists($record->item)) {
                                            Storage::disk('public')->delete($record->item);
                                            \Illuminate\Support\Facades\Log::info('Deleted old media:', ['path' => $record->item]);
                                        }

                                        // Tạo khóa để ngăn xử lý trùng lặp
                                        $fileNames = array_map(function ($file) {
                                            return $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : 'invalid';
                                        }, $state);
                                        $lockKey = 'media_upload_lock_' . md5($get('category_id') . implode('|', $fileNames));

                                        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                                            \Illuminate\Support\Facades\Log::warning('Duplicate media submit detected:', [
                                                'lock_key' => $lockKey,
                                                'files' => $fileNames,
                                            ]);
                                            return null;
                                        }

                                        \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addSeconds(10));

                                        $categoryId = $get('category_id');
                                        $newPaths = [];
                                        foreach ($state as $file) {
                                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                                $type = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
                                                $directory = $type === 'video' ? 'videos' : 'images';
                                                $path = $file->store($directory, 'public');
                                                \Illuminate\Support\Facades\Log::info('Saving new media in edit:', [
                                                    'path' => $path,
                                                    'category_id' => $categoryId,
                                                    'file' => $file->getClientOriginalName(),
                                                    'lock_key' => $lockKey,
                                                    'type' => $type,
                                                ]);
                                                $newPaths[] = [
                                                    'path' => $path,
                                                    'type' => $type,
                                                ];
                                            }
                                        }

                                        // Chỉ lưu media đầu tiên (theo logic hiện tại của bạn)
                                        if (!empty($newPaths)) {
                                            $record->update([
                                                'category_id' => $categoryId,
                                                'item' => $newPaths[0]['path'],
                                                'type' => $newPaths[0]['type'],
                                            ]);
                                        }

                                        return null;
                                    })
                                    ->dehydrated(false)
                                    ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Xoá')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Ảnh đã được xóa thành công'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Lưu')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Quay lại')
                ->icon('heroicon-o-x-mark'),
        ];
    }
}