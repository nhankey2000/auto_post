<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiPostPromptResource\Pages;
use App\Models\AiPostPrompt;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\RepeatScheduled;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AiPostPromptResource extends Resource
{
    protected static ?string $model = AiPostPrompt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Tự Động Đăng Bài';

    protected static ?string $navigationLabel = 'Tạo Bài Đăng Bằng AI';

    protected static ?string $pluralLabel = 'Tạo Bài Đăng Bằng AI';

    /**
     * Define the form schema for creating/editing an AI Post Prompt.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            // Hidden fields for controlling visibility
            ...self::getVisibilityFields(),

            // Section 1: Content Input
            Forms\Components\Section::make('Nội Dung Bài Đăng')
                ->description('Cung cấp thông tin chính cho bài đăng tự động.')
                ->schema([
                    // Toggle actions for switching between prompt and image upload
                    ...self::getToggleActions(),

                    // Image upload section
                    Forms\Components\FileUpload::make('image')
                    ->label('Hình Ảnh')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->maxFiles(1)
                    ->directory('ai-post-images')
                    ->disk('public')
                    ->preserveFilenames()
                    ->dehydrated(true)
                    ->required(fn (callable $get) => $get('show_image_upload'))
                    ->helperText('Tải lên một hình ảnh duy nhất (JPG, PNG, GIF, WebP) để sử dụng cho bài đăng.')
                    ->visible(fn (callable $get) => $get('show_image_upload'))
                    ->afterStateUpdated(function ($state) {
                        Log::info('Trạng thái FileUpload image', [
                            'state' => $state,
                        ]);
                    })
                    ->getUploadedFileNameForStorageUsing(function ($file) {
                        $filename = $file->getClientOriginalName();
                        $path = 'ai-post-images/' . $filename;
                        Log::info('Tên file từ FileUpload', [
                            'filename' => $filename,
                            'path' => $path,
                        ]);
                        return $path;
                    })
                    ->validationMessages([
                        'required' => 'Vui lòng tải lên một hình ảnh.',
                        'image' => 'File tải lên phải là hình ảnh (JPG, PNG, GIF, WebP).',
                    ]),

                    // Prompt textarea
                    ...self::getPromptField(),

                    // Action to toggle back to prompt view
                    Forms\Components\Actions::make([
                        Action::make('show_prompt')
                            ->label('Nhập Prompt')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->visible(fn (callable $get) => $get('show_image_upload'))
                            ->action(fn (callable $set) => $set('show_prompt', true) && $set('show_image_upload', false)),
                    ]),
                ])
                ->collapsible()
                ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

            // Section 2: Image Settings
            Forms\Components\Section::make('Cài Đặt Hình Ảnh')
                ->description('Tùy chỉnh hình ảnh đi kèm bài đăng.')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            // Image category selection
                            Forms\Components\Select::make('image_category')
                                ->label('Phân Loại Hình Ảnh')
                                ->options(\App\Models\Category::pluck('category', 'id'))
                                ->nullable()
                                ->helperText('Chọn phân loại hình ảnh cho bài đăng.'),

                            // Image count input
                            Forms\Components\TextInput::make('image_count')
                                ->label('Số Lượng Ảnh Random')
                                ->numeric()
                                ->minValue(1)
                                ->nullable()
                                ->helperText('Nhập số lượng ảnh sẽ được random khi đăng bài.'),
                        ]),
                ])
                ->collapsible()
                ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

            // Section 3: Scheduling Settings
            Forms\Components\Section::make('Lên Lịch Đăng Bài')
                ->description('Thiết lập thời gian đăng và lịch chạy lại.')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            // Scheduled date and time
                            Forms\Components\DateTimePicker::make('scheduled_at')
                                ->label('Thời Điểm Lên Lịch')
                                ->nullable()
                                ->displayFormat('d/m/Y H:i')
                                ->helperText('Chọn thời điểm bài đăng sẽ được đăng lên.'),

                            // Posted date and time (disabled)
                            Forms\Components\DateTimePicker::make('posted_at')
                                ->label('Thời Điểm Đăng')
                                ->nullable()
                                ->disabled()
                                ->displayFormat('d/m/Y H:i'),
                        ]),

                    // Repeat schedules repeater
                    Forms\Components\Repeater::make('repeatSchedules')
                        ->label('Lịch Chạy Lại')
                        ->relationship('repeatSchedules')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\DateTimePicker::make('schedule')
                                        ->label('Thời Điểm Chạy Lại')
                                        ->required()
                                        ->displayFormat('d/m/Y H:i'),
                                    Forms\Components\TextInput::make('facebook_post_id')
                                        ->label('Facebook Post ID')
                                        ->disabled()
                                        ->nullable(),
                                ]),
                        ])
                        ->createItemButtonLabel('Thêm Lịch Chạy Lại')
                        ->nullable()
                        ->helperText('Thêm các thời điểm để bài đăng chạy lại.')
                        ->mutateRelationshipDataBeforeFillUsing(fn ($data, $record) => [
                            'schedule' => $data['schedule'],
                            'facebook_post_id' => $data['facebook_post_id'],
                        ])
                        ->mutateRelationshipDataBeforeSaveUsing(fn ($data, $record) => [
                            'schedule' => $data['schedule'],
                            'post_option' => $record->post_option,
                            'selected_pages' => $record->selected_pages,
                            'facebook_post_id' => $data['facebook_post_id'] ?? null,
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => isset($state['schedule']) ? $state['schedule'] : null)
                        ->deleteAction(fn (Action $action) => $action->color('danger')),
                ])
                ->collapsible()
                ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

            // Section 4: Platform Settings
            Forms\Components\Section::make('Cài Đặt Nền Tảng')
                ->description('Chọn nền tảng và các trang để đăng bài.')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            // Platform selection
                            Forms\Components\Select::make('platform_id')
                                ->label('Nền Tảng Đăng Bài')
                                ->relationship('platform', 'name')
                                ->preload()
                                ->searchable()
                                ->nullable()
                                ->reactive()
                                ->afterStateUpdated(fn (callable $set) => $set('post_option', null) && $set('selected_pages', []))
                                ->helperText('Chọn nền tảng để đăng bài.'),

                            // Post option selection
                            Forms\Components\Select::make('post_option')
                                ->label('Tùy Chọn Đăng Bài')
                                ->options([
                                    'all' => 'Đăng tất cả trang',
                                    'selected' => 'Chọn trang',
                                ])
                                ->reactive()
                                ->nullable()
                                ->afterStateUpdated(fn (callable $set) => $set('selected_pages', []))
                                ->helperText('Chọn cách đăng bài lên các trang.'),
                        ]),

                    // Selected pages checkbox list
                    Forms\Components\CheckboxList::make('selected_pages')
                        ->label('Chọn Các Trang')
                        ->options(function (callable $get) {
                            $platformId = $get('platform_id');
                            return $platformId
                                ? PlatformAccount::where('platform_id', $platformId)->pluck('name', 'id')->toArray()
                                : [];
                        })
                        ->visible(fn (callable $get) => $get('post_option') === 'selected')
                        ->required(fn (callable $get) => $get('post_option') === 'selected')
                        ->validationMessages([
                            'required' => 'Vui lòng chọn ít nhất một trang để đăng bài.',
                        ])
                        ->helperText('Chọn các trang để đăng bài.')
                        ->columns(2),
                ])
                ->collapsible()
                ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

            // Section 5: Status and Generated Content
            Forms\Components\Section::make('Trạng Thái và Nội Dung')
                ->description('Xem trạng thái và nội dung được tạo tự động.')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            // Status selection
                            Forms\Components\Select::make('status')
                                ->label('Trạng Thái')
                                ->options([
                                    'pending' => 'Chờ xử lý',
                                    'generating' => 'Đang tạo nội dung',
                                    'generated' => 'Đã tạo nội dung',
                                    'posted' => 'Đã đăng',
                                ])
                                ->default('pending')
                                ->required()
                                ->disabled(),

                            // Generated content textarea
                            Forms\Components\Textarea::make('generated_content')
                                ->label('Nội Dung Sinh Ra')
                                ->rows(5)
                                ->nullable()
                                ->disabled()
                                ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),
                        ]),
                ])
                ->collapsible()
                ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),
        ]);
    }

    /**
     * Get hidden fields for controlling visibility of prompt and image upload sections.
     *
     * @return array
     */
    protected static function getVisibilityFields(): array
    {
        return [
            Forms\Components\Hidden::make('show_prompt')
                ->default(true)
                ->reactive()
                ->dehydrated(false),
            Forms\Components\Hidden::make('show_image_upload')
                ->default(false)
                ->reactive()
                ->dehydrated(false),
        ];
    }

    /**
     * Get toggle actions for switching between prompt and image upload views.
     *
     * @return array
     */
    protected static function getToggleActions(): array
    {
        return [
            Forms\Components\Actions::make([
                Action::make('show_image_upload')
                    ->label('Nhập Hình Ảnh')
                    ->icon('heroicon-o-photo')
                    ->color('warning')
                    ->visible(fn (callable $get) => !$get('show_image_upload'))
                    ->action(function (callable $set) {
                        $set('show_prompt', false);
                        $set('show_image_upload', true);
                    }),
            ]),
        ];
    }

    /**
     * Get the prompt textarea field.
     *
     * @return array
     */
    protected static function getPromptField(): array
    {
        return [
            Forms\Components\Textarea::make('prompt')
                ->label('Yêu Cầu Đầu Vào')
                ->required()
                ->rows(5)
                ->visible(fn (callable $get) => $get('show_prompt'))
                ->extraAttributes(['class' => 'bg-gray-800 text-gray-300'])
                ->helperText('Nhập yêu cầu để AI tạo nội dung bài đăng.'),
        ];
    }

    /**
     * Define the table schema for displaying AI Post Prompts.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prompt')
                    ->label('Yêu cầu đầu vào')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->prompt)
                    ->default('Được tạo bằng hình ảnh'),
                Tables\Columns\TextColumn::make('image_category')
                    ->label('Phân loại hình ảnh')
                    ->formatStateUsing(fn ($state) => $state ? \App\Models\Category::find($state)['category'] ?? 'N/A' : 'Chưa chọn'),
                Tables\Columns\TextColumn::make('image_count')
                    ->label('Số ảnh random')
                    ->formatStateUsing(fn ($state) => $state ? "$state ảnh" : 'Chưa chọn'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Thời điểm lên lịch')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repeatSchedules.schedule')
                    ->label('Lịch chạy lại')
                    ->limit(10)
                    ->formatStateUsing(function ($record) {
                        $schedules = $record->repeatSchedules->pluck('schedule')->filter();
                        return $schedules->isEmpty()
                            ? 'Không có'
                            : $schedules->map(fn ($schedule) => $schedule->format('d/m/Y H:i'))->join(', ');
                    }),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ xử lý',
                        'generating' => 'Đang tạo nội dung',
                        'generated' => 'Đã tạo nội dung',
                        'posted' => 'Đã đăng',
                    ])
                    ->sortable()
                    ->disabled(),
                Tables\Columns\TextColumn::make('generated_content')
                    ->label('Nội dung sinh ra')
                    ->limit(10)
                    ->tooltip(fn ($record) => $record->generated_content),
                Tables\Columns\TextColumn::make('platform.name')
                    ->label('Nền tảng')
                    ->formatStateUsing(fn ($record) => $record->platform ? $record->platform->name : 'Không có'),
                Tables\Columns\TextColumn::make('post_option')
                    ->label('Tùy chọn đăng bài')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'all' => 'Đăng tất cả trang',
                        'selected' => 'Chọn trang',
                        default => 'Không có',
                    }),
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Thời điểm đăng')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ xử lý',
                        'generating' => 'Đang tạo nội dung',
                        'generated' => 'Đã tạo nội dung',
                        'posted' => 'Đã đăng',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->icon('heroicon-o-pencil'),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xoá tất cả')
                        ->modalHeading('Xoá các nền tảng đã chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xoá các nền tảng này? Hành động này sẽ không thể hoàn tác.')
                        ->modalButton('Xác nhận')
                        ->color('danger'),
                ])->label('Tùy chọn'),
            ]);
    }

    /**
     * Define the pages for the AI Post Prompt resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            
            'index' => Pages\ListAiPostPrompts::route('/'),
            'create' => Pages\CreateAiPostPrompt::route('/create'),
            'edit' => Pages\EditAiPostPrompt::route('/{record}/edit'),
        ];
    }
}