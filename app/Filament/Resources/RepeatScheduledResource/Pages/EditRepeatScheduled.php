<?php

namespace App\Filament\Resources\RepeatScheduledResource\Pages;

use App\Filament\Resources\RepeatScheduledResource;
use App\Models\PlatformAccount;
use App\Services\FacebookService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EditRepeatScheduled extends EditRecord
{
    protected static string $resource = RepeatScheduledResource::class;

    protected $originalData = [];

    public function getTitle(): string
    {
        return "Chỉnh sửa bài viết";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Section::make('Thông tin đăng bài')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('platform_account_name')
                                    ->label('Tài khoản đăng bài')
                                    ->content(function ($record) {
                                        return $record->platformAccount ? $record->platformAccount->name : 'Không có tài khoản';
                                    }),
                                \Filament\Forms\Components\Placeholder::make('schedule_display')
                                    ->label('Thời gian đăng')
                                    ->content(function ($record) {
                                        $schedule = $record->schedule;
                                        if (!empty($schedule)) {
                                            try {
                                                return \Carbon\Carbon::parse($schedule)->format('d/m/Y H:i');
                                            } catch (\Exception $e) {
                                                Log::error('Lỗi khi parse giá trị từ cột schedule trong form', [
                                                    'record_id' => $record->id,
                                                    'schedule' => $schedule,
                                                    'error' => $e->getMessage(),
                                                ]);
                                                return 'Không xác định';
                                            }
                                        }
                                        return 'Không xác định';
                                    }),
                                \Filament\Forms\Components\Placeholder::make('updated_at_display')
                                    ->label('Thời gian cập nhật')
                                    ->content(function ($record) {
                                        $updatedAt = $record->updated_at;
                                        if (!empty($updatedAt)) {
                                            try {
                                                return \Carbon\Carbon::parse($updatedAt)->format('d/m/Y H:i');
                                            } catch (\Exception $e) {
                                                Log::error('Lỗi khi parse giá trị từ cột updated_at trong form', [
                                                    'record_id' => $record->id,
                                                    'updated_at' => $updatedAt,
                                                    'error' => $e->getMessage(),
                                                ]);
                                                return 'Không xác định';
                                            }
                                        }
                                        return 'Không xác định';
                                    }),
                            ]),
                    ])
                    ->collapsible(),
                \Filament\Forms\Components\Section::make('Nội dung bài viết')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\Textarea::make('content')
                            ->label('Nội dung')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                \Filament\Forms\Components\Section::make('Hình ảnh')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('images')
                            ->label('Hình ảnh')
                            ->multiple()
                            ->image()
                            ->directory('images')
                            ->preserveFilenames()
                            ->deletable()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(function ($get) {
                        $images = $get('images') ?? $this->record->images ?? [];
                        Log::info('Kiểm tra hiển thị section Hình ảnh', [
                            'record_id' => $this->record->id,
                            'images' => $images,
                            'is_visible' => !empty($images) && is_array($images),
                        ]);
                        return !empty($images) && is_array($images);
                    }),
                \Filament\Forms\Components\Section::make('Video')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('videos')
                            ->label('Video')
                            ->multiple()
                            ->acceptedFileTypes(['video/mp4', 'video/ogg', 'video/webm'])
                            ->directory('videos')
                            ->preserveFilenames()
                            ->deletable()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull()
                            ->maxFiles(2),
                    ])
                    ->collapsible()
                    ->visible(function ($get) {
                        $videos = $get('videos') ?? $this->record->videos ?? [];
                        Log::info('Kiểm tra hiển thị section Video', [
                            'record_id' => $this->record->id,
                            'videos' => $videos,
                            'is_visible' => !empty($videos) && is_array($videos),
                        ]);
                        return !empty($videos) && is_array($videos);
                    }),
            ]);
    }

    protected function beforeSave(): void
    {
        $this->originalData = $this->record->getOriginal();
        Log::info('Lưu dữ liệu gốc trước khi lưu', [
            'record_id' => $this->record->id,
            'original_data' => $this->originalData,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Chuẩn hóa đường dẫn hình ảnh
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = array_map(function ($path) {
                $path = str_replace('\\', '/', trim($path));
                $filename = basename($path);
                $normalizedPath = 'images/' . $filename;
                Log::info('Chuẩn hóa đường dẫn ảnh', [
                    'original_path' => $path,
                    'filename' => $filename,
                    'normalized_path' => $normalizedPath,
                ]);
                return $normalizedPath;
            }, array_filter($data['images'], 'is_string'));
            Log::info('Chuẩn hóa images trong mutateFormDataBeforeSave', [
                'record_id' => $this->record->id,
                'images' => $data['images'],
            ]);
        } else {
            $data['images'] = [];
        }

        // Chuẩn hóa đường dẫn video
        if (isset($data['videos']) && is_array($data['videos'])) {
            $data['videos'] = array_map(function ($path) {
                $path = str_replace('\\', '/', trim($path));
                $filename = basename($path);
                $normalizedPath = 'videos/' . $filename;
                Log::info('Chuẩn hóa đường dẫn video', [
                    'original_path' => $path,
                    'filename' => $filename,
                    'normalized_path' => $normalizedPath,
                ]);
                return $normalizedPath;
            }, array_filter($data['videos'], 'is_string'));
            Log::info('Chuẩn hóa videos trong mutateFormDataBeforeSave', [
                'record_id' => $this->record->id,
                'videos' => $data['videos'],
            ]);
        } else {
            $data['videos'] = [];
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Log::info('Dữ liệu trước khi cập nhật model', [
            'record_id' => $record->id,
            'data' => $data,
        ]);
        $record->update($data);
        return $record;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (!$record->facebook_post_id || !$record->platform_account_id) {
            Log::warning('Thiếu thông tin để cập nhật bài viết', [
                'record_id' => $record->id,
                'facebook_post_id' => $record->facebook_post_id,
                'platform_account_id' => $record->platform_account_id,
            ]);
            Notification::make()
                ->title('Cảnh báo')
                ->body('Thiếu Facebook Post ID hoặc Platform Account ID.')
                ->warning()
                ->send();
            return;
        }

        try {
            $platformAccount = PlatformAccount::find($record->platform_account_id);

            if (!$platformAccount || !$platformAccount->page_id || !$platformAccount->access_token) {
                Log::error('Thông tin fan page hoặc access token không hợp lệ', [
                    'platform_account_id' => $record->platform_account_id,
                ]);
                Notification::make()
                    ->title('Lỗi')
                    ->body('Thông tin fan page hoặc access token không hợp lệ.')
                    ->danger()
                    ->send();
                return;
            }

            $newTitle = $record->title ?? '';
            $newContent = $record->content ?? '';
            $originalTitle = $this->originalData['title'] ?? '';
            $originalContent = $this->originalData['content'] ?? '';

            $message = '';
            if (!empty($newTitle)) {
                $message .= $this->toBoldText($newTitle) . "\n";
            }
            if (!empty($newContent)) {
                $message .= $newContent;
            }

            $imagePaths = [];
            if (is_array($record->images) && !empty($record->images)) {
                foreach ($record->images as $path) {
                    $cleanPath = preg_replace('#^images/#', '', $path);
                    $fullPath = storage_path('app/public/images/' . $cleanPath);
                    if (file_exists($fullPath)) {
                        $imagePaths[] = $fullPath;
                    } else {
                        Log::warning('Ảnh không tồn tại', [
                            'path' => $path,
                            'full_path' => $fullPath,
                        ]);
                        Notification::make()
                            ->title('Cảnh báo')
                            ->body("Ảnh không tồn tại: {$cleanPath}")
                            ->warning()
                            ->send();
                    }
                }
            }

            $videoPaths = [];
            if (is_array($record->videos) && !empty($record->videos)) {
                foreach ($record->videos as $path) {
                    $cleanPath = preg_replace('#^videos/#', '', $path);
                    $fullPath = storage_path('app/public/videos/' . $cleanPath);
                    if (file_exists($fullPath)) {
                        $videoPaths[] = $fullPath;
                    } else {
                        Log::warning('Video không tồn tại', [
                            'path' => $path,
                            'full_path' => $fullPath,
                        ]);
                        Notification::make()
                            ->title('Cảnh báo')
                            ->body("Video không tồn tại: {$cleanPath}")
                            ->warning()
                            ->send();
                    }
                }
            }

            Log::info('Danh sách ảnh để đăng', [
                'record_id' => $record->id,
                'image_paths' => $imagePaths,
            ]);

            Log::info('Danh sách video để đăng', [
                'record_id' => $record->id,
                'video_paths' => $videoPaths,
            ]);

            $facebookService = app(FacebookService::class);

            $originalImages = $this->getOriginalImages();
            $newImages = is_array($record->images) ? array_map(function ($path) {
                return preg_replace('#^images/#', '', $path);
            }, $record->images) : [];
            $imagesChanged = $this->imagesHaveChanged($originalImages, $newImages);

            $originalVideos = $this->getOriginalVideos();
            $newVideos = is_array($record->videos) ? array_map(function ($path) {
                return preg_replace('#^videos/#', '', $path);
            }, $record->videos) : [];
            $videosChanged = $this->imagesHaveChanged($originalVideos, $newVideos);

            $contentChanged = ($originalTitle !== $newTitle) || ($originalContent !== $newContent);

            Log::info('Kiểm tra thay đổi', [
                'record_id' => $record->id,
                'images_changed' => $imagesChanged,
                'videos_changed' => $videosChanged,
                'content_changed' => $contentChanged,
                'original_images' => $originalImages,
                'new_images' => $newImages,
                'original_videos' => $originalVideos,
                'new_videos' => $newVideos,
            ]);

            // Nếu có thay đổi về video hoặc hình ảnh, xóa bài viết cũ
            if (($imagesChanged && !empty($imagePaths)) || ($videosChanged && !empty($videoPaths))) {
                try {
                    $facebookService->deletePost($record->facebook_post_id, $platformAccount->access_token);
                    Log::info('Xóa bài viết cũ thành công', [
                        'record_id' => $record->id,
                        'post_id' => $record->facebook_post_id,
                    ]);
                    Notification::make()
                        ->title('Thông báo')
                        ->body('Đã xóa bài viết cũ trên Facebook.')
                        ->info()
                        ->send();
                } catch (\Exception $e) {
                    Log::warning('Không thể xóa bài viết cũ', [
                        'record_id' => $record->id,
                        'post_id' => $record->facebook_post_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Đăng video (tối đa 2 video)
                $postIds = [];
                if (!empty($videoPaths)) {
                    try {
                        $videoPostIds = $facebookService->postVideoToPage(
                            $platformAccount->page_id,
                            $platformAccount->access_token,
                            $message,
                            $videoPaths
                        );
                        $postIds = array_merge($postIds, $videoPostIds);
                        Log::info('Đăng video mới thành công', [
                            'record_id' => $record->id,
                            'new_post_ids' => $videoPostIds,
                            'video_paths' => $videoPaths,
                        ]);
                        Notification::make()
                            ->title('Thành công')
                            ->body('Video đã được đăng với ID: ' . implode(', ', $videoPostIds))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Lỗi khi đăng video', [
                            'record_id' => $record->id,
                            'video_paths' => $videoPaths,
                            'error' => $e->getMessage(),
                        ]);
                        Notification::make()
                            ->title('Lỗi')
                            ->body('Đăng video thất bại: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }

                // Đăng hình ảnh (nếu có)
                if (!empty($imagePaths)) {
                    $newPostId = $facebookService->postToPage(
                        $platformAccount->page_id,
                        $platformAccount->access_token,
                        $message,
                        $imagePaths
                    );
                    $postIds[] = $newPostId;
                    Log::info('Đăng hình ảnh mới thành công', [
                        'record_id' => $record->id,
                        'new_post_id' => $newPostId,
                    ]);
                    Notification::make()
                        ->title('Thành công')
                        ->body('Hình ảnh đã được đăng với ID mới: ' . $newPostId)
                        ->success()
                        ->send();
                }

                // Cập nhật facebook_post_id với ID của bài viết cuối cùng
                $record->update(['facebook_post_id' => end($postIds)]);
            } elseif ($contentChanged && !empty($message)) {
                // Nếu chỉ có thay đổi nội dung, cập nhật bài viết hiện tại
                $facebookService->updatePost(
                    $record->facebook_post_id,
                    $platformAccount->access_token,
                    $message
                );

                Log::info('Cập nhật nội dung bài viết thành công', [
                    'record_id' => $record->id,
                    'post_id' => $record->facebook_post_id,
                ]);

                Notification::make()
                    ->title('Thành công')
                    ->body('Nội dung bài viết trên Facebook đã được cập nhật.')
                    ->success()
                    ->send();
            } else {
                Log::info('Không có thay đổi cần cập nhật', [
                    'record_id' => $record->id,
                    'post_id' => $record->facebook_post_id,
                ]);
                Notification::make()
                    ->title('Thông báo')
                    ->body('Không có thay đổi cần cập nhật trên Facebook.')
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Lỗi khi cập nhật bài viết trên Facebook', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            Notification::make()
                ->title('Lỗi')
                ->body('Cập nhật bài viết thất bại: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getOriginalImages(): array
    {
        $originalImages = isset($this->originalData['images']) && is_array($this->originalData['images'])
            ? $this->originalData['images']
            : [];
        return array_map(function ($path) {
            return preg_replace('#^images/#', '', strval($path));
        }, $originalImages);
    }

    protected function getOriginalVideos(): array
    {
        $originalVideos = isset($this->originalData['videos']) && is_array($this->originalData['videos'])
            ? $this->originalData['videos']
            : [];
        return array_map(function ($path) {
            return preg_replace('#^videos/#', '', strval($path));
        }, $originalVideos);
    }

    protected function imagesHaveChanged(array $original, array $new): bool
    {
        $original = array_map('strval', $original);
        $new = array_map('strval', $new);
        sort($original);
        sort($new);
        return $original !== $new;
    }

    protected function toBoldText(string $text): string
    {
        $boldMap = [
            'A' => '𝐀', 'B' => '𝐁', 'C' => '𝐂', 'D' => '𝐃', 'E' => '𝐄', 'F' => '𝐅', 'G' => '𝐆', 'H' => '𝐇',
            'I' => '𝐈', 'J' => '𝐉', 'K' => '𝐊', 'L' => '𝐋', 'M' => '𝐌', 'N' => '𝐍', 'O' => '𝐎', 'P' => '𝐏',
            'Q' => '𝐐', 'R' => '𝐑', 'S' => '𝐒', 'T' => '𝐓', 'U' => '𝐔', 'V' => '𝐕', 'W' => '𝐖', 'X' => '𝐋',
            'Y' => '𝐘', 'Z' => '𝐙',
            'a' => '𝐚', 'b' => '𝐛', 'c' => '𝐜', 'd' => '𝐝', 'e' => '𝐞', 'f' => '𝐟', 'g' => '𝐠', 'h' => '𝐡',
            'i' => '𝐢', 'j' => '𝐣', 'k' => '𝐤', 'l' => '𝐥', 'm' => '𝐦', 'n' => '𝐧', 'o' => '𝐨', 'p' => '𝐩',
            'q' => '𝐪', 'r' => '𝐫', 's' => '𝐬', 't' => '𝐭', 'u' => '𝐮', 'v' => '𝐯', 'w' => '𝐰', 'x' => '𝐱',
            'y' => '𝐲', 'z' => '𝐳',
            '0' => '𝟎', '1' => '𝟏', '2' => '𝟐', '3' => '𝟑', '4' => '𝟒', '5' => '𝟓', '6' => '𝟔', '7' => '𝟕',
            '8' => '𝟖', '9' => '𝟗',
            '!' => '❗', '?' => '❓', '.' => '.', ',' => ',', ' ' => ' ', ':' => ':', ';' => ';', '-' => '-',
        ];

        $boldText = '';
        for ($i = 0; $i < mb_strlen($text, 'UTF-8'); $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $boldText .= $boldMap[$char] ?? $char;
        }

        return $boldText;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Xóa bài viết')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Xóa bài viết thành công'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Cập nhật')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Hủy')
                ->icon('heroicon-o-x-mark'),
        ];
    }

    protected function fillForm(): void
    {
        $data = $this->record->toArray();

        // Chuẩn hóa đường dẫn hình ảnh
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = array_map(function ($image) {
                $image = str_replace('\\', '/', trim($image));
                $filename = preg_replace('#^images/#', '', $image);
                $normalizedImage = 'images/' . $filename;
                Log::info('Chuẩn hóa image trong fillForm', [
                    'record_id' => $this->record->id,
                    'original_image' => $image,
                    'normalized_image' => $normalizedImage,
                ]);
                return $normalizedImage;
            }, $data['images']);
        }

        // Chuẩn hóa đường dẫn video
        if (isset($data['videos']) && is_array($data['videos'])) {
            $data['videos'] = array_map(function ($video) {
                $video = str_replace('\\', '/', trim($video));
                $filename = preg_replace('#^videos/#', '', $video);
                $normalizedVideo = 'videos/' . $filename;
                Log::info('Chuẩn hóa video trong fillForm', [
                    'record_id' => $this->record->id,
                    'original_video' => $video,
                    'normalized_video' => $normalizedVideo,
                ]);
                return $normalizedVideo;
            }, $data['videos']);
        }

        Log::info('Dữ liệu để fill form', [
            'record_id' => $this->record->id,
            'data' => $data,
        ]);
        $this->form->fill($data);
    }
}