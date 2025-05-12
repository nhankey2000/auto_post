<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\PlatformAccount;
use App\Models\Post;
use App\Models\PostRepost;
use App\Services\FacebookService;
use Illuminate\Support\Facades\Log;
use Filament\Actions\DeleteAction;
use Filament\Forms\Form;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public function form(Form $form): Form
    {
        $isPublished = $this->record->status === 'published';

        return $form
            ->schema([
                \Filament\Forms\Components\Section::make('Thông tin đăng bài')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                // Tài khoản đăng bài
                                $isPublished
                                    ? \Filament\Forms\Components\Placeholder::make('platform_account_display')
                                        ->label('Tài khoản đăng bài')
                                        ->content(function ($record) {
                                            $platformAccount = PlatformAccount::find($record->platform_account_id);
                                            return $platformAccount ? $platformAccount->name : 'Không xác định';
                                        })
                                    : \Filament\Forms\Components\Select::make('platform_account_id')
                                        ->label('Tài khoản đăng bài')
                                        ->options(PlatformAccount::all()->pluck('name', 'id'))
                                        ->required()
                                        ->default($this->record->platform_account_id),

                                // Thời gian đăng
                                \Filament\Forms\Components\Placeholder::make('schedule_display')
                                    ->label('Thời gian đăng')
                                    ->content(function ($record) {
                                        $schedule = $record->scheduled_at;
                                        if (!empty($schedule)) {
                                            try {
                                                return \Carbon\Carbon::parse($schedule)->format('d/m/Y H:i');
                                            } catch (\Exception $e) {
                                                Log::error('Lỗi khi parse giá trị từ cột scheduled_at trong form', [
                                                    'record_id' => $record->id,
                                                    'scheduled_at' => $schedule,
                                                    'error' => $e->getMessage(),
                                                ]);
                                                return 'Không xác định';
                                            }
                                        }
                                        return 'Không xác định';
                                    }),

                                // Thời gian cập nhật
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
                    ->schema(array_filter([
                        // Tiêu đề
                        $isPublished
                            ? \Filament\Forms\Components\Placeholder::make('title_display')
                                ->label('Tiêu đề')
                                ->content(fn ($record) => $record->title)
                            : \Filament\Forms\Components\TextInput::make('title')
                                ->label('Tiêu đề')
                                ->required()
                                ->maxLength(255),

                        // Nội dung
                        $isPublished
                            ? \Filament\Forms\Components\Placeholder::make('content_display')
                                ->label('Nội dung')
                                ->content(fn ($record) => $record->content)
                            : \Filament\Forms\Components\Textarea::make('content')
                                ->label('Nội dung')
                                ->rows(6)
                                ->columnSpanFull(),

                        // Hashtags
                        $isPublished
                            ? \Filament\Forms\Components\Placeholder::make('hashtags_display')
                                ->label('Hashtags')
                                ->content(fn ($record) => !empty($record->hashtags) ? implode(' ', $record->hashtags) : 'Không có hashtags')
                            : \Filament\Forms\Components\TagsInput::make('hashtags')
                                ->label('Hashtags')
                                ->separator(' ')
                                ->columnSpanFull(),

                        // Lịch đăng (chỉ hiển thị nếu chưa đăng)
                        !$isPublished
                            ? \Filament\Forms\Components\DateTimePicker::make('scheduled_at')
                                ->label('Lịch đăng')
                                ->required()
                                ->default(now())
                                ->minDate(now())
                            : null,
                    ], fn ($item) => !is_null($item)))
                    ->collapsible()
                    ->columns(1),

                // Hình ảnh
                \Filament\Forms\Components\Section::make('Hình ảnh')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('images')
                            ->label('Hình ảnh')
                            ->multiple()
                            ->image()
                            ->directory('post-media')
                            ->preserveFilenames()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull()
                            ->disabled($isPublished)
                            ->deletable(!$isPublished)
                            ->default(fn ($record) => $record->media),
                    ])
                    ->collapsible()
                    ->visible(function ($get) use ($isPublished) {
                        if ($isPublished) {
                            $images = $this->record->media ?? [];
                            Log::info('Kiểm tra hiển thị section Hình ảnh khi đã đăng', [
                                'images' => $images,
                                'is_visible' => !empty($images) && is_array($images),
                            ]);
                            return !empty($images) && is_array($images);
                        }

                        $images = $get('images') ?? [];
                        Log::info('Kiểm tra hiển thị section Hình ảnh khi chưa đăng', [
                            'images' => $images,
                            'is_visible' => !empty($images) && is_array($images),
                        ]);
                        return !empty($images) && is_array($images);
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Xoá')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->successNotificationTitle('Bài viết đã được xóa thành công'),
        ];
    }

    protected function getFormActions(): array
    {
        $isPublished = $this->record->status === 'published';

        return $isPublished
            ? [
                $this->getSaveFormAction()
                    ->label('Lưu')
                    ->icon('heroicon-o-check'),
                $this->getCancelFormAction()
                    ->label('Đóng')
                    ->icon('heroicon-o-x-mark'),
            ]
            : [
                $this->getSaveFormAction()
                    ->label('Lưu')
                    ->icon('heroicon-o-check'),
                $this->getCancelFormAction()
                    ->label('Huỷ')
                    ->icon('heroicon-o-x-mark'),
            ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        Log::info('Dữ liệu ban đầu trong mutateFormDataBeforeFill', [
            'record_id' => $this->record->id,
            'data' => $data,
            'record_media' => $this->record->media,
        ]);

        $data['images'] = $data['media'] ?? $this->record->media ?? [];

        Log::info('Dữ liệu sau khi gán trong mutateFormDataBeforeFill', [
            'record_id' => $this->record->id,
            'images' => $data['images'],
        ]);

        $data['reposts'] = $this->record->reposts->map(function ($repost) {
            return [
                'platform_account_ids' => [$repost->platform_account_id],
                'reposted_at' => $repost->reposted_at,
            ];
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['media'] = $data['images'] ?? [];

        $this->platformAccountIds = [$data['platform_account_id']];
        $this->reposts = $data['reposts'] ?? [];

        if (empty($this->platformAccountIds)) {
            throw new \Exception('Phải chọn ít nhất một tài khoản nền tảng.');
        }

        Log::info('Dữ liệu trong mutateFormDataBeforeSave', [
            'record_id' => $this->record->id,
            'media' => $data['media'],
            'platform_account_ids' => $this->platformAccountIds,
        ]);

        unset($data['platform_account_id']);
        unset($data['reposts']);
        unset($data['images']);
        unset($data['platform_account_ids']);
        unset($data['platform_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->update(['platform_account_id' => $this->platformAccountIds[0]]);

        $this->record->reposts()->delete();
        if (!empty($this->reposts)) {
            foreach ($this->reposts as $repost) {
                if (in_array($this->record->platform_account_id, $repost['platform_account_ids'])) {
                    PostRepost::create([
                        'post_id' => $this->record->id,
                        'platform_account_id' => $this->record->platform_account_id,
                        'reposted_at' => $repost['reposted_at'],
                    ]);
                }
            }

            Log::info('Lịch đăng lại đã được cập nhật:', $this->reposts);
        }

        $this->updateOnPlatform();
    }

    protected function updateOnPlatform(): void
    {
        $facebookService = app(FacebookService::class);

        $message = $this->record->content;
        if ($this->record->hashtags) {
            $message .= "\n" . implode(' ', $this->record->hashtags);
        }

        if ($this->record->facebook_post_id) {
            $platformAccount = $this->record->platformAccount;
            if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                try {
                    $facebookService->updatePost($this->record->facebook_post_id, $platformAccount->access_token, $message);
                    Log::info("Đã cập nhật bài viết trên Facebook: Post ID {$this->record->facebook_post_id}");
                } catch (\Exception $e) {
                    Log::error('Failed to update post on Facebook for platform account ' . $platformAccount->name . ': ' . $e->getMessage());
                }
            }
        }

        foreach ($this->record->reposts as $repost) {
            if ($repost->facebook_post_id) {
                $platformAccount = PlatformAccount::find($repost->platform_account_id);
                if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                    try {
                        $facebookService->updatePost($repost->facebook_post_id, $platformAccount->access_token, $message);
                        Log::info("Đã cập nhật bài viết trên Facebook: Post ID {$repost->facebook_post_id}");
                    } catch (\Exception $e) {
                        Log::error('Failed to update post on Facebook for platform account ' . $platformAccount->name . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }

    protected ?array $platformAccountIds = [];
    protected ?array $reposts = [];
}