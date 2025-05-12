<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostRepost;
use App\Services\FacebookService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Lên Lịch Đăng Bài';
    protected static ?string $label = 'Bài Viết';
    protected static ?string $pluralLabel = 'Bài Viết';

    /**
     * Chuyển đổi văn bản thành dạng "in đậm" bằng ký tự Unicode.
     *
     * @param string $text
     * @return string
     */
    private static function toBoldUnicode(string $text): string
    {
        $boldMap = [
            'A' => '𝐀', 'B' => '𝐁', 'C' => '𝐂', 'D' => '𝐃', 'E' => '𝐄', 'F' => '𝐅', 'G' => '𝐆', 'H' => '𝐇', 'I' => '𝐈', 'J' => '𝐉',
            'K' => '𝐊', 'L' => '𝐋', 'M' => '𝐌', 'N' => '𝐍', 'O' => '𝐎', 'P' => '𝐏', 'Q' => '𝐐', 'R' => '𝐑', 'S' => '𝐒', 'T' => '𝐓',
            'U' => '𝐔', 'V' => '𝐕', 'W' => '𝐖', 'X' => '𝐗', 'Y' => '𝐘', 'Z' => '𝐍',
            'a' => '𝐚', 'b' => '𝐛', 'c' => '𝐜', 'd' => '𝐝', 'e' => '𝐞', 'f' => '𝐟', 'g' => '𝐠', 'h' => '𝐡', 'i' => '𝐢', 'j' => '𝐣',
            'k' => '𝐤', 'l' => '𝐥', 'm' => '𝐦', 'n' => '𝐧', 'o' => '𝐨', 'p' => '𝐩', 'q' => '𝐪', 'r' => '𝐫', 's' => '𝐬', 't' => '𝐭',
            'u' => '𝐮', 'v' => '𝐯', 'w' => '𝐰', 'x' => '𝐱', 'y' => '𝐯', 'z' => '𝐳',
            '0' => '𝟎', '1' => '𝟏', '2' => '𝟐', '3' => '𝟑', '4' => '𝟒', '5' => '𝟓', '6' => '𝟔', '7' => '𝟇', '8' => '𝟖', '9' => '𝟗',
        ];

        $boldText = '';
        foreach (mb_str_split($text) as $char) {
            $boldText .= $boldMap[$char] ?? $char;
        }

        return $boldText;
    }

    /**
     * Định dạng nội dung để đăng lên Facebook, đảm bảo giữ các ký tự xuống dòng.
     *
     * @param string $content
     * @return string
     */
    private static function formatContentForPost(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = str_replace(['</p><p>', '</p>'], "\n", $content);
        $content = str_replace(['<br>', '<br/>', '<br />'], "\n", $content);
        $content = strip_tags($content);
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);
        return trim($content);
    }

    /**
     * Chuẩn bị danh sách media với đường dẫn tuyệt đối và xác định loại media (ảnh hoặc video).
     *
     * @param array $media
     * @param int $postId
     * @return array
     */
    private static function prepareMediaPaths(array $media, int $postId): array
    {
        $mediaPaths = [];
        $mediaType = 'image';
        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'heif', 'webp'];
        $allowedVideoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        $maxSize = 4 * 1024 * 1024;
        $maxVideoSize = 100 * 1024 * 1024;

        if (!empty($media)) {
            foreach ($media as $mediaPath) {
                $absolutePath = storage_path('app/public/' . $mediaPath);
                if (file_exists($absolutePath)) {
                    $fileSize = filesize($absolutePath);
                    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

                    if (in_array($extension, $allowedImageExtensions)) {
                        if ($fileSize > $maxSize) {
                            Log::warning('File ảnh vượt quá kích thước cho phép (4 MB)', [
                                'post_id' => $postId,
                                'media_path' => $mediaPath,
                                'file_size' => $fileSize,
                            ]);
                            throw new \Exception("File ảnh {$mediaPath} vượt quá kích thước cho phép (4 MB).");
                        }
                    } elseif (in_array($extension, $allowedVideoExtensions)) {
                        $mediaType = 'video';
                        if ($fileSize > $maxVideoSize) {
                            Log::warning('File video vượt quá kích thước cho phép (100 MB)', [
                                'post_id' => $postId,
                                'media_path' => $mediaPath,
                                'file_size' => $fileSize,
                            ]);
                            throw new \Exception("File video {$mediaPath} vượt quá kích thước cho phép (100 MB).");
                        }
                    } else {
                        Log::warning('Định dạng file không được hỗ trợ', [
                            'post_id' => $postId,
                            'media_path' => $mediaPath,
                            'extension' => $extension,
                        ]);
                        throw new \Exception("File {$mediaPath} có định dạng không được hỗ trợ. Chỉ hỗ trợ ảnh (JPG, PNG, GIF, TIFF, HEIF, WebP) hoặc video (MP4, MOV, AVI, WMV, FLV, MKV, WEBM).");
                    }

                    $mediaPaths[] = $absolutePath;
                } else {
                    Log::warning('File media không tồn tại', [
                        'post_id' => $postId,
                        'media_path' => $mediaPath,
                        'absolute_path' => $absolutePath,
                    ]);
                }
            }
        }

        return [
            'paths' => $mediaPaths,
            'type' => $mediaType,
        ];
    }

    /**
     * Define the form schema for creating/editing a Post.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section 1: Platform and Pages
                Forms\Components\Section::make('Nền Tảng và Trang')
                    ->description('Chọn nền tảng và các trang để đăng bài.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Platform selection
                                Select::make('platform_id')
                                    ->label('Chọn Nền Tảng')
                                    ->options(Platform::all()->pluck('name', 'id')->toArray())
                                    ->default(1)
                                    ->placeholder('Chọn nền tảng')
                                    ->reactive()
                                    ->afterStateUpdated(fn (Set $set) => $set('platform_account_ids', []))
                                    ->required(),

                                // Platform accounts (pages)
                                CheckboxList::make('platform_account_ids')
                                    ->label('Tên Trang')
                                    ->options(function (Get $get) {
                                        $platformId = $get('platform_id');
                                        if (!$platformId) {
                                            return [];
                                        }
                                        return PlatformAccount::where('platform_id', $platformId)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->hidden(fn (Get $get) => !$get('platform_id'))
                                    ->reactive()
                                    ->required()
                                    ->minItems(1)
                                    ->columns(2),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

                // Section 2: Post Content
                Forms\Components\Section::make('Nội Dung Bài Viết')
                    ->description('Nhập tiêu đề, nội dung và các thông tin liên quan.')
                    ->schema([
                        // Actions for generating content
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('generate_with_gpt')
                                ->label('Tạo Nội Dung Bằng GPT')
                                ->icon('heroicon-o-sparkles')
                                ->color('primary')
                                ->visible(fn (Get $get) => !$get('content') || !$get('title'))
                                ->form([
                                    Forms\Components\Textarea::make('topic')
                                        ->label('Chủ Đề')
                                        ->required()
                                        ->rows(3)
                                        ->placeholder('Nhập chủ đề bài viết...'),
                                    Forms\Components\Select::make('tone')
                                        ->label('Phong Cách')
                                        ->options([
                                            'formal' => 'Chính Thức',
                                            'casual' => 'Thân Mật',
                                            'funny' => 'Hài Hước',
                                            'professional' => 'Chuyên Nghiệp',
                                        ])
                                        ->default('casual'),
                                    Forms\Components\Select::make('language')
                                        ->label('Ngôn Ngữ')
                                        ->options([
                                            'vi' => 'Tiếng Việt',
                                            'en' => 'Tiếng Anh',
                                        ])
                                        ->default('vi'),
                                ])
                                ->action(function (array $data, $livewire, Set $set) {
                                    try {
                                        $currentFormData = $livewire->form->getState();
                                        $data['topic'] = str_replace(["\r\n", "\n", "\r"], ' ', $data['topic']);
                                        $data['topic'] = trim($data['topic']);

                                        $platformId = $livewire->data['platform_id'] ?? null;
                                        $platform = Platform::find($platformId)?->name ?? '';
                                        $platformLower = strtolower($platform);
                                        $existingHashtags = $livewire->data['hashtags'] ?? [];

                                        $platformConfig = match ($platformLower) {
                                            'facebook' => ['max_length' => 63206, 'max_hashtags' => 10],
                                            'instagram' => ['max_length' => 2200, 'max_hashtags' => 30],
                                            'youtube' => ['max_length' => 5000, 'title_required' => true],
                                            'tiktok' => ['max_length' => 2200, 'max_hashtags' => 10],
                                            'zalo' => ['max_length' => 10000],
                                            default => [],
                                        };
                                        $platformConfig['platform'] = $platformLower;

                                        $generated = \App\Services\ChatGptContentService::generatePostContent(
                                            null,
                                            $data['topic'],
                                            $data['tone'],
                                            $data['language'],
                                            array_merge($platformConfig, ['existing_hashtags' => $existingHashtags])
                                        );

                                        $generated['title'] = strip_tags($generated['title']);
                                        $generated['content'] = strip_tags($generated['content']);
                                        $generated['content'] = self::formatContentForPost($generated['content']);

                                        Log::info('Generated content after formatting', [
                                            'title' => $generated['title'],
                                            'content' => $generated['content'],
                                            'hashtags' => $generated['hashtags'] ?? 'Not set',
                                        ]);

                                        $currentFormData['title'] = $generated['title'];
                                        $currentFormData['content'] = $generated['content'];
                                        $currentFormData['hashtags'] = $generated['hashtags'] ?? [];
                                        $livewire->form->fill($currentFormData);
                                        $set('is_content_generated', true);

                                        Notification::make()
                                            ->success()
                                            ->title('Nội Dung Đã Được Tạo')
                                            ->body('Bài viết đã được tạo với nội dung từ GPT cho ' . ucfirst($platformLower) . '.')
                                            ->send();
                                    } catch (\Exception $e) {
                                        Log::error('Error generating content', ['error' => $e->getMessage()]);
                                        Notification::make()
                                            ->danger()
                                            ->title('Lỗi Khi Tạo Nội Dung')
                                            ->body($e->getMessage())
                                            ->send();
                                    }
                                }),

                            Forms\Components\Actions\Action::make('regenerate_with_gpt')
                                ->label('Tạo Lại Nội Dung')
                                ->icon('heroicon-o-arrow-path')
                                ->color('warning')
                                ->visible(fn (Get $get) => $get('is_content_generated') === true)
                                ->form([
                                    Forms\Components\Textarea::make('topic')
                                        ->label('Chủ Đề')
                                        ->required()
                                        ->rows(3)
                                        ->placeholder('Nhập chủ đề bài viết...'),
                                    Forms\Components\Select::make('tone')
                                        ->label('Phong Cách')
                                        ->options([
                                            'formal' => 'Chính Thức',
                                            'casual' => 'Thân Mật',
                                            'funny' => 'Hài Hước',
                                            'professional' => 'Chuyên Nghiệp',
                                        ])
                                        ->default('casual'),
                                    Forms\Components\Select::make('language')
                                        ->label('Ngôn Ngữ')
                                        ->options([
                                            'vi' => 'Tiếng Việt',
                                            'en' => 'Tiếng Anh',
                                        ])
                                        ->default('vi'),
                                ])
                                ->action(function (array $data, $livewire) {
                                    try {
                                        $currentFormData = $livewire->form->getState();
                                        $data['topic'] = str_replace(["\r\n", "\n", "\r"], ' ', $data['topic']);
                                        $data['topic'] = trim($data['topic']);

                                        $platformId = $livewire->data['platform_id'] ?? null;
                                        $platform = Platform::find($platformId)?->name ?? '';
                                        $platformLower = strtolower($platform);
                                        $existingHashtags = $livewire->data['hashtags'] ?? [];

                                        $platformConfig = match ($platformLower) {
                                            'facebook' => ['max_length' => 63206, 'max_hashtags' => 10],
                                            'instagram' => ['max_length' => 2200, 'max_hashtags' => 30],
                                            'youtube' => ['max_length' => 5000, 'title_required' => true],
                                            'tiktok' => ['max_length' => 2200, 'max_hashtags' => 10],
                                            'zalo' => ['max_length' => 10000],
                                            default => [],
                                        };
                                        $platformConfig['platform'] = $platformLower;

                                        $generated = \App\Services\ChatGptContentService::generatePostContent(
                                            null,
                                            $data['topic'],
                                            $data['tone'],
                                            $data['language'],
                                            array_merge($platformConfig, ['existing_hashtags' => $existingHashtags])
                                        );

                                        $generated['title'] = strip_tags($generated['title']);
                                        $generated['content'] = strip_tags($generated['content']);
                                        $generated['content'] = self::formatContentForPost($generated['content']);

                                        Log::info('Regenerated content after formatting', [
                                            'title' => $generated['title'],
                                            'content' => $generated['content'],
                                            'hashtags' => $generated['hashtags'] ?? 'Not set',
                                        ]);

                                        $currentFormData['title'] = $generated['title'];
                                        $currentFormData['content'] = $generated['content'];
                                        $currentFormData['hashtags'] = $generated['hashtags'] ?? [];
                                        $livewire->form->fill($currentFormData);

                                        Notification::make()
                                            ->success()
                                            ->title('Nội Dung Đã Được Tạo Lại')
                                            ->body('Bài viết đã được tạo lại với nội dung mới từ GPT cho ' . ucfirst($platformLower) . '.')
                                            ->send();
                                    } catch (\Exception $e) {
                                        Log::error('Error regenerating content', ['error' => $e->getMessage()]);
                                        Notification::make()
                                            ->danger()
                                            ->title('Lỗi Khi Tạo Lại Nội Dung')
                                            ->body($e->getMessage())
                                            ->send();
                                    }
                                }),
                        ])->columnSpanFull(),

                        // Hidden field for tracking content generation
                        Forms\Components\Hidden::make('is_content_generated')
                            ->default(false),

                        // Title and Content
                        Forms\Components\Grid::make(1)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Tiêu Đề')
                                    ->maxLength(255)
                                    ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),

                                Forms\Components\Textarea::make('content')
                                    ->label('Nội Dung')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),
                            ]),

                        // Add contact info action
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('add_contact_info')
                                ->label('Tự Động Thêm Phần Liên Hệ')
                                ->color('success')
                                ->action(function (Get $get, Set $set) {
                                    $currentContent = $get('content') ?? '';
                                    $contactInfo = "🌿MỌI THÔNG TIN CHI TIẾT LIÊN HỆ 🌿\n" .
                                                   "🎯Địa chỉ: Tổ 26, ấp Mỹ Ái, xã Mỹ Khánh, huyện Phong Điền, TP Cần Thơ.\n" .
                                                   "🎯Địa chỉ google map: https://goo.gl/maps/padvdnsZeBHM6UC97\n" .
                                                   "☎️Hotline: 0901 095 709 |  0931 852 113\n" .
                                                   "🔰Zalo hỗ trợ: 078 2 918 222\n" .
                                                   "📧Mail: dulichongde@gmail.com\n" .
                                                   "🌐Website: www.ongde.vn\n" .
                                                   "#ongde #dulichongde #khudulichongde #langdulichsinhthaiongde #homestay #phimtruong #mientay #VietNam #Thailand #Asian #thienvientruclam #chonoicairang #khachsancantho #dulichcantho #langdulichongde";

                                    $newContent = $currentContent ? $currentContent . "\n\n" . $contactInfo : $contactInfo;
                                    $set('content', $newContent);

                                    Notification::make()
                                        ->success()
                                        ->title('Đã Thêm Nội Dung Liên Hệ')
                                        ->body('Thông tin liên hệ đã được thêm vào cuối nội dung.')
                                        ->send();
                                }),
                        ])->columnSpanFull(),

                        // Media upload
                        FileUpload::make('media')
                            ->label('Ảnh/Video')
                            ->multiple()
                            ->directory('post-media')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/heif', 'image/webp', 'video/mp4', 'video/mov', 'video/avi', 'video/wmv', 'video/flv', 'video/mkv', 'video/webm'])
                            ->maxSize(102400)
                            ->maxFiles(10)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),

                        // Hashtags
                        TagsInput::make('hashtags')
                            ->label('Hashtags')
                            ->placeholder('Thêm hashtags')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

                // Section 3: Scheduling
                Forms\Components\Section::make('Lên Lịch Đăng Bài')
                    ->description('Thiết lập thời gian đăng và lịch đăng lại.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Status
                                Select::make('status')
                                    ->label('Trạng Thái')
                                    ->placeholder('Chọn trạng thái')
                                    ->options([
                                        'draft' => 'Nháp',
                                        'published' => 'Đã Đăng',
                                        'scheduled' => 'Hẹn Giờ',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true),

                                // Scheduled date
                                DateTimePicker::make('scheduled_at')
                                    ->label('Hẹn Giờ Đăng Lần Đầu')
                                    ->nullable()
                                    ->reactive()
                                    ->displayFormat('d/m/Y H:i')
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $scheduledAt = $get('scheduled_at');
                                        $reposts = $get('reposts') ?? [];
                                        $hasRepostedAt = false;
                                        foreach ($reposts as $repost) {
                                            if (!empty($repost['reposted_at'])) {
                                                $hasRepostedAt = true;
                                                break;
                                            }
                                        }
                                        $set('status', $scheduledAt || $hasRepostedAt ? 'scheduled' : 'draft');
                                    }),
                            ]),

                        // Reposts
                        Repeater::make('reposts')
                            ->label('Lịch Đăng Lại')
                            ->schema([
                                CheckboxList::make('platform_account_ids')
                                    ->label('Chọn Trang')
                                    ->options(function (Get $get) {
                                        $platformAccountIds = $get('../../platform_account_ids') ?? [];
                                        Log::info('Platform Account IDs in Repeater:', $platformAccountIds);
                                        return empty($platformAccountIds)
                                            ? []
                                            : PlatformAccount::whereIn('id', $platformAccountIds)
                                                ->pluck('name', 'id')
                                                ->toArray();
                                    })
                                    ->required()
                                    ->minItems(1)
                                    ->columns(2),
                                DateTimePicker::make('reposted_at')
                                    ->label('Thời Gian Đăng Lại')
                                    ->required()
                                    ->reactive()
                                    ->displayFormat('d/m/Y H:i')
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $scheduledAt = $get('../../scheduled_at');
                                        $reposts = $get('../../reposts') ?? [];
                                        $hasRepostedAt = false;
                                        foreach ($reposts as $repost) {
                                            if (!empty($repost['reposted_at'])) {
                                                $hasRepostedAt = true;
                                                break;
                                            }
                                        }
                                        $set('../../status', $scheduledAt || $hasRepostedAt ? 'scheduled' : 'draft');
                                    }),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->default([])
                            ->itemLabel(fn (array $state): ?string => isset($state['reposted_at']) ? $state['reposted_at'] : null)
                            ->deleteAction(fn (FormAction $action) => $action->color('danger'))
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $scheduledAt = $get('scheduled_at');
                                $reposts = $get('reposts') ?? [];
                                $hasRepostedAt = false;
                                foreach ($reposts as $repost) {
                                    if (!empty($repost['reposted_at'])) {
                                        $hasRepostedAt = true;
                                        break;
                                    }
                                }
                                $set('status', $scheduledAt || $hasRepostedAt ? 'scheduled' : 'draft');
                            }),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),
            ]);
    }

    /**
     * Define the table schema for displaying Posts.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu Đề')
                    ->searchable()
                    ->limit(10)
                    ->tooltip(fn ($record) => $record->title)
                    ->sortable()
                    ->extraAttributes(['class' => 'font-semibold text-gray-200']),
                Tables\Columns\TextColumn::make('platformAccount.name')
                    ->label('Tên Trang')
                    ->sortable()
                    ->default('Không Có Trang')
                    ->extraAttributes(['class' => 'text-gray-300']),
                Tables\Columns\TextColumn::make('platformAccount.platform.name')
                    ->label('Nền Tảng')
                    ->sortable()
                    ->default('Không Có Nền Tảng')
                    ->extraAttributes(['class' => 'text-gray-300']),
                Tables\Columns\TextColumn::make('content')
                    ->label('Nội Dung')
                    ->limit(10)
                    ->formatStateUsing(fn ($state) => strip_tags($state))
                    ->tooltip(fn ($record) => strip_tags($record->content))
                    ->searchable()
                    ->extraAttributes(['class' => 'text-gray-400']),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng Thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'scheduled' => 'warning',
                    }),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Giờ Đăng Lần Đầu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-400']),
                Tables\Columns\TextColumn::make('reposts')
                    ->label('Lịch Đăng Lại')
                    ->limit(10)
                    ->tooltip(fn ($record) => $record->reposts)
                    ->formatStateUsing(function ($record) {
                        return $record->reposts->map(function ($repost) {
                            $platformAccount = PlatformAccount::find($repost->platform_account_id);
                            $platformAccountName = $platformAccount ? $platformAccount->name : 'Không xác định';
                            return "{$platformAccountName} vào " . ($repost->reposted_at ? $repost->reposted_at->format('d/m/Y H:i') : 'Chưa xác định');
                        })->implode('; ');
                    })
                    ->default('Không Có Lịch Đăng Lại')
                    ->extraAttributes(['class' => 'text-gray-400']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng Thái')
                    ->placeholder('Chọn trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đã Đăng',
                        'scheduled' => 'Hẹn Giờ',
                    ]),
            ])
            ->actions([
                TableAction::make('view_or_edit')
                    ->label(fn (Post $record) => $record->status === 'published' ? 'Xem' : 'Sửa')
                    ->icon(fn (Post $record) => $record->status === 'published' ? 'heroicon-o-eye' : 'heroicon-o-pencil')
                    ->color('primary')
                    ->url(fn (Post $record) => static::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->before(function (Post $record, FacebookService $facebookService) {
                        if ($record->facebook_post_id) {
                            $platformAccount = $record->platformAccount;
                            if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                                try {
                                    $facebookService->deletePost($record->facebook_post_id, $platformAccount->access_token);
                                    Log::info("Đã xóa bài viết trên Facebook: Post ID {$record->facebook_post_id}");
                                } catch (\Exception $e) {
                                    Log::error('Failed to delete post from Facebook for platform account ' . $platformAccount->name . ': ' . $e->getMessage());
                                }
                            }
                        }
                        foreach ($record->reposts as $repost) {
                            if ($repost->facebook_post_id) {
                                $platformAccount = PlatformAccount::find($repost->platform_account_id);
                                if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                                    try {
                                        $facebookService->deletePost($repost->facebook_post_id, $platformAccount->access_token);
                                        $repost->update(['facebook_post_id' => null]);
                                        Log::info("Đã xóa bài viết trên Facebook: Post ID {$repost->facebook_post_id}");
                                    } catch (\Exception $e) {
                                        Log::error('Failed to delete post from Facebook for platform account ' . $platformAccount->name . ': ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }),
                TableAction::make('post_now')
                    ->label('Đăng Ngay')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->action(function (Post $record, FacebookService $facebookService) {
                        if ($record->status === 'published') {
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body('Bài viết này đã được đăng, không thể đăng lại.')
                                ->send();
                            return;
                        }

                        $title = $record->title ?: 'Bài viết không có tiêu đề';
                        $content = $record->content ?: '';
                        $boldTitle = self::toBoldUnicode($title);
                        $content = self::formatContentForPost($content);
                        $message = $boldTitle . "\n\n" . $content;

                        if ($record->hashtags) {
                            $message .= "\n" . implode(' ', $record->hashtags);
                        }

                        Log::info('Post content before sending to Facebook', [
                            'post_id' => $record->id,
                            'message' => $message,
                            'newlines' => substr_count($message, "\n"),
                        ]);

                        $mediaData = self::prepareMediaPaths($record->media ?? [], $record->id);

                        // Log dữ liệu media trước khi xử lý
                        Log::info('Media data before processing in post_now', [
                            'post_id' => $record->id,
                            'media_paths' => $mediaData['paths'],
                            'media_type' => $mediaData['type'],
                            'media_count' => count($mediaData['paths']),
                        ]);

                        $platformAccount = $record->platformAccount;
                        if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                            $pageId = $platformAccount->page_id;
                            if (!$pageId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi')
                                    ->body('Page ID không tìm thấy cho trang: ' . $platformAccount->name)
                                    ->send();
                                return;
                            }

                            try {
                                if ($mediaData['type'] === 'video') {
                                    // Chỉ cho phép đăng tối đa 2 video
                                    if (count($mediaData['paths']) > 2) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Lỗi')
                                            ->body('Chỉ có thể đăng tối đa 2 video tại một thời điểm. Vui lòng chọn ít hơn hoặc bằng 2 video.')
                                            ->send();
                                        return;
                                    }

                                    if (empty($mediaData['paths'])) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Lỗi')
                                            ->body('Không tìm thấy video để đăng.')
                                            ->send();
                                        return;
                                    }

                                    // Làm phẳng mảng video paths
                                    $videoPaths = $mediaData['paths'];
                                    $flattenArray = function ($array) use (&$flattenArray) {
                                        $result = [];
                                        foreach ($array as $item) {
                                            if (is_array($item)) {
                                                $result = array_merge($result, $flattenArray($item));
                                            } elseif (is_object($item) && method_exists($item, '__toString')) {
                                                $result[] = (string) $item;
                                            } elseif (is_scalar($item) || is_null($item)) {
                                                $result[] = (string) $item;
                                            }
                                        }
                                        return $result;
                                    };

                                    $videoPaths = $flattenArray($videoPaths);
                                    $videoPaths = array_filter($videoPaths);

                                    Log::info('Video paths after normalization in post_now', [
                                        'post_id' => $record->id,
                                        'video_paths' => $videoPaths,
                                        'type' => gettype($videoPaths),
                                        'content' => json_encode($videoPaths),
                                    ]);

                                    // Sử dụng postVideo thay vì postVideoToPage
                                    $facebookPostIds = $facebookService->postVideo($pageId, $platformAccount->access_token, $message, $videoPaths);
                                    $facebookPostId = $facebookPostIds[0] ?? null; // Lấy post ID đầu tiên
                                } else {
                                    $facebookPostId = $facebookService->postToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                                }

                                $record->update([
                                    'facebook_post_id' => $facebookPostId,
                                    'status' => 'published',
                                    'scheduled_at' => null,
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Đăng Bài Thành Công')
                                    ->body("Bài viết đã được đăng lên trang {$platformAccount->name}.")
                                    ->send();

                                Log::info("Đã đăng bài viết lên trang {$platformAccount->name}: Post ID {$facebookPostId}");
                            } catch (\Exception $e) {
                                Log::error("Error posting to page {$platformAccount->name} for Post ID {$record->id}: " . $e->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi Khi Đăng Bài')
                                    ->body("Không thể đăng bài lên trang {$platformAccount->name}: " . $e->getMessage())
                                    ->send();
                            }
                        } else {
                            Log::warning('Platform account not found or not a Facebook account for Post ID: ' . $record->id);
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body('Trang không tồn tại hoặc không phải là trang Facebook cho Post ID: ' . $record->id)
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Post $record) => $record->status !== 'published'),
                TableAction::make('update_post')
                    ->label('Cập Nhật Bài Viết')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->form([
                        TextInput::make('title')
                            ->label('Tiêu Đề')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (Post $record) => $record->title),
                        Textarea::make('content')
                            ->label('Nội Dung')
                            ->required()
                            ->default(fn (Post $record) => strip_tags($record->content)),
                        FileUpload::make('media')
                            ->label('Ảnh/Video Mới (Nếu Có)')
                            ->multiple()
                            ->directory('post-media')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/heif', 'image/webp', 'video/mp4', 'video/mov', 'video/avi', 'video/wmv', 'video/flv', 'video/mkv', 'video/webm'])
                            ->maxSize(102400)
                            ->maxFiles(10)
                            ->default(fn (Post $record) => $record->media),
                        TagsInput::make('hashtags')
                            ->label('Hashtags')
                            ->placeholder('Thêm hashtags')
                            ->default(fn (Post $record) => $record->hashtags),
                    ])
                    ->action(function (Post $record, array $data, FacebookService $facebookService) {
                        if ($record->status !== 'published' || !$record->facebook_post_id) {
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body('Bài viết này chưa được đăng lên Facebook, không thể cập nhật.')
                                ->send();
                            return;
                        }

                        $title = $data['title'] ?: 'Bài viết không có tiêu đề';
                        $content = $data['content'] ?: '';
                        $boldTitle = self::toBoldUnicode($title);
                        $content = self::formatContentForPost($content);
                        $message = $boldTitle . "\n\n" . $content;

                        if (!empty($data['hashtags'])) {
                            $message .= "\n" . implode(' ', $data['hashtags']);
                        }

                        Log::info('Post content before sending to Facebook (update)', [
                            'post_id' => $record->id,
                            'message' => $message,
                            'newlines' => substr_count($message, "\n"),
                        ]);

                        $mediaData = self::prepareMediaPaths($data['media'] ?? [], $record->id);

                        Log::info('Media data for update', [
                            'post_id' => $record->id,
                            'media_paths' => $mediaData['paths'],
                            'media_type' => $mediaData['type'],
                            'media_count' => count($mediaData['paths']),
                        ]);

                        $platformAccount = $record->platformAccount;
                        if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                            $pageId = $platformAccount->page_id;
                            if (!$pageId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi')
                                    ->body('Page ID không tìm thấy cho trang: ' . $platformAccount->name)
                                    ->send();
                                return;
                            }

                            try {
                                if (!empty($mediaData['paths'])) {
                                    $newPostId = $facebookService->updatePostWithMedia(
                                        $record->facebook_post_id,
                                        $pageId,
                                        $platformAccount->access_token,
                                        $message,
                                        $mediaData['paths'],
                                        $mediaData['type']
                                    );

                                    $record->update([
                                        'facebook_post_id' => $newPostId,
                                        'title' => $data['title'],
                                        'content' => $data['content'],
                                        'hashtags' => $data['hashtags'],
                                        'media' => $data['media'],
                                    ]);
                                } else {
                                    $facebookService->updatePost($record->facebook_post_id, $platformAccount->access_token, $message);
                                    $record->update([
                                        'title' => $data['title'],
                                        'content' => $data['content'],
                                        'hashtags' => $data['hashtags'],
                                    ]);
                                }

                                Log::info("Đã cập nhật bài viết trên trang {$platformAccount->name}: Post ID {$record->facebook_post_id}");

                                Notification::make()
                                    ->success()
                                    ->title('Cập Nhật Thành Công')
                                    ->body("Bài viết đã được cập nhật trên trang {$platformAccount->name}.")
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error("Error updating post on page {$platformAccount->name} for Post ID {$record->id}: " . $e->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi Khi Cập Nhật Bài')
                                    ->body("Không thể cập nhật bài trên trang {$platformAccount->name}: " . $e->getMessage())
                                    ->send();
                            }
                        } else {
                            Log::warning('Platform account not found or not a Facebook account for Post ID: ' . $record->id);
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body('Trang không tồn tại hoặc không phải là trang Facebook cho Post ID: ' . $record->id)
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Post $record) => $record->status === 'published' && $record->facebook_post_id !== null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('post_all_now')
                        ->label('Đăng Tất Cả')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(function (Collection $records, FacebookService $facebookService) {
                            $successCount = 0;
                            $errorMessages = [];

                            foreach ($records as $record) {
                                if ($record->status === 'published') {
                                    continue;
                                }

                                try {
                                    $title = $record->title ?: 'Bài viết không có tiêu đề';
                                    $content = $record->content ?: '';
                                    $boldTitle = self::toBoldUnicode($title);
                                    $content = self::formatContentForPost($content);
                                    $message = $boldTitle . "\n\n" . $content;

                                    if ($record->hashtags) {
                                        $message .= "\n" . implode(' ', $record->hashtags);
                                    }

                                    Log::info('Post content before sending to Facebook (bulk)', [
                                        'post_id' => $record->id,
                                        'message' => $message,
                                        'newlines' => substr_count($message, "\n"),
                                    ]);

                                    $mediaData = self::prepareMediaPaths($record->media ?? [], $record->id);

                                    $platformAccount = $record->platformAccount;
                                    if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                                        $pageId = $platformAccount->page_id;
                                        if (!$pageId) {
                                            $errorMessages[] = "Bài viết ID {$record->id}: Page ID không tìm thấy cho trang: {$platformAccount->name}.";
                                            continue;
                                        }

                                        try {
                                            if ($mediaData['type'] === 'video') {
                                                // Chỉ cho phép đăng tối đa 2 video
                                                if (count($mediaData['paths']) > 2) {
                                                    $errorMessages[] = "Bài viết ID {$record->id}: Chỉ có thể đăng tối đa 2 video tại một thời điểm.";
                                                    continue;
                                                }

                                                if (empty($mediaData['paths'])) {
                                                    $errorMessages[] = "Bài viết ID {$record->id}: Không tìm thấy video để đăng.";
                                                    continue;
                                                }

                                                // Làm phẳng mảng video paths
                                                $videoPaths = $mediaData['paths'];
                                                $flattenArray = function ($array) use (&$flattenArray) {
                                                    $result = [];
                                                    foreach ($array as $item) {
                                                        if (is_array($item)) {
                                                            $result = array_merge($result, $flattenArray($item));
                                                        } elseif (is_object($item) && method_exists($item, '__toString')) {
                                                            $result[] = (string) $item;
                                                        } elseif (is_scalar($item) || is_null($item)) {
                                                            $result[] = (string) $item;
                                                        }
                                                    }
                                                    return $result;
                                                };

                                                $videoPaths = $flattenArray($videoPaths);
                                                $videoPaths = array_filter($videoPaths);

                                                Log::info('Video paths after normalization in post_all_now', [
                                                    'post_id' => $record->id,
                                                    'video_paths' => $videoPaths,
                                                    'type' => gettype($videoPaths),
                                                    'content' => json_encode($videoPaths),
                                                ]);

                                                // Sử dụng postVideo thay vì postVideoToPage
                                                $facebookPostIds = $facebookService->postVideo($pageId, $platformAccount->access_token, $message, $videoPaths);
                                                $facebookPostId = $facebookPostIds[0] ?? null; // Lấy post ID đầu tiên
                                            } else {
                                                $facebookPostId = $facebookService->postToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                                            }

                                            $record->update([
                                                'facebook_post_id' => $facebookPostId,
                                                'status' => 'published',
                                                'scheduled_at' => null,
                                            ]);
                                            $successCount++;
                                            Log::info("Đã đăng bài viết lên trang {$platformAccount->name}: Post ID {$facebookPostId}");
                                        } catch (\Exception $e) {
                                            $errorMessages[] = "Bài viết ID {$record->id}: Không thể đăng bài lên trang {$platformAccount->name}: {$e->getMessage()}";
                                            Log::error("Error posting to page {$platformAccount->name} for Post ID {$record->id}: " . $e->getMessage());
                                        }
                                    } else {
                                        $errorMessages[] = "Bài viết ID {$record->id}: Trang không tồn tại hoặc không phải là trang Facebook.";
                                    }
                                } catch (\Exception $e) {
                                    $errorMessages[] = "Bài viết ID {$record->id}: " . $e->getMessage();
                                    Log::error("Error posting Post ID {$record->id}: " . $e->getMessage());
                                    continue;
                                }
                            }

                            if ($successCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Đăng Bài Thành Công')
                                    ->body("Đã đăng thành công {$successCount} bài viết.")
                                    ->success()
                                    ->send();
                            }

                            if (!empty($errorMessages)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Có Lỗi Xảy Ra')
                                    ->body(implode("\n", $errorMessages))
                                    ->danger()
                                    ->send();
                            }

                            if ($successCount === 0 && empty($errorMessages)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Không Có Bài Viết Nào Để Đăng')
                                    ->body('Tất cả bài viết được chọn đã được đăng.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->color('success')
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa Tất Cả')
                        ->modalHeading('Xóa Các Bài Viết Đã Chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xóa các bài viết này? Hành động này sẽ không thể hoàn tác.')
                        ->modalButton('Xác Nhận')
                        ->color('danger')
                        ->deselectRecordsAfterCompletion()
                        ->before(function (Collection $records, FacebookService $facebookService) {
                            foreach ($records as $record) {
                                if ($record->facebook_post_id) {
                                    $platformAccount = $record->platformAccount;
                                    if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                                        try {
                                            $facebookService->deletePost($record->facebook_post_id, $platformAccount->access_token);
                                            Log::info("✅ Đã xoá bài viết chính Facebook: {$record->facebook_post_id}");
                                        } catch (\Exception $e) {
                                            Log::error("❌ Xoá post Facebook lỗi: " . $e->getMessage());
                                        }
                                    }
                                }

                                foreach ($record->reposts as $repost) {
                                    if ($repost->facebook_post_id) {
                                        $platformAccount = PlatformAccount::find($repost->platform_account_id);
                                        if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                                            try {
                                                $facebookService->deletePost($repost->facebook_post_id, $platformAccount->access_token);
                                                $repost->update(['facebook_post_id' => null]);
                                                Log::info("✅ Đã xoá repost Facebook: {$repost->facebook_post_id}");
                                            } catch (\Exception $e) {
                                                Log::error("❌ Xoá repost lỗi: " . $e->getMessage());
                                            }
                                        }
                                    }
                                }
                            }
                        }),
                ])->label('Tùy Chọn'),
            ]);
    }

    /**
     * Define the Eloquent query for the Post resource.
     *
     * @return Builder
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['platformAccount', 'platformAccount.platform', 'reposts']);
    }

    /**
     * Define the relations for the Post resource.
     *
     * @return array
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Define the pages for the Post resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}