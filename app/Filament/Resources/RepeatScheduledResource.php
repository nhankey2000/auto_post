<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepeatScheduledResource\Pages;
use App\Models\RepeatScheduled;
use App\Models\PlatformAccount;
use App\Services\FacebookService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RepeatScheduledResource extends Resource
{
    protected static ?string $model = RepeatScheduled::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Tự Động Đăng Bài';
    protected static ?string $navigationLabel = 'Bài Viết Đã Đăng';

    protected static ?string $pluralLabel = 'Bài Viết Đã Đăng';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('schedule')
                    ->label('Thời gian đăng')
                    ->disabled()
                    ->required(),
                TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(255),
                Textarea::make('content')
                    ->label('Nội dung')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                FileUpload::make('images')
                    ->label('Ảnh')
                    ->multiple()
                    ->directory('images')
                    ->preserveFilenames()
                    ->image()
                    ->maxFiles(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform_account_id')
                    ->label('Tên Trang')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return 'Không có trang';
                        }
                        $platformAccount = PlatformAccount::find($state);
                        return $platformAccount ? $platformAccount->name : 'Không tìm thấy trang';
                    }),
                TextColumn::make('schedule')
                    ->label('Thời gian đăng')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : 'Không có lịch';
                    }),
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->limit(20)
                    ->default('Không có tiêu đề')
                    ->wrap(),
                TextColumn::make('content')
                    ->label('Nội dung')
                    ->default('Không có nội dung')
                    ->wrap()
                    ->limit(10)
                    ->html()
                    ->formatStateUsing(fn ($state) => nl2br($state)),
                ImageColumn::make('images')
                    ->label('Ảnh')
                    ->stacked()
                    ->circular()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->disk('public')
                    ->getStateUsing(function ($record) {
                        $images = $record->images;
                        if (is_array($images) && !empty($images)) {
                            $images = array_map(function ($image) {
                                // Chuẩn hóa dấu gạch chéo
                                $image = str_replace('\\', '/', $image);
                                // Kiểm tra nếu đã có tiền tố images/
                                if (preg_match('#^images/#', $image)) {
                                    return $image;
                                }
                                // Nếu là tên file thuần, thêm images/
                                return 'images/' . $image;
                            }, $images);
                            Log::info('ImageColumn state', [
                                'record_id' => $record->id,
                                'images' => $images,
                            ]);
                            return $images;
                        }
                        return [];
                    }),
                TextColumn::make('aiPostPrompt.user.name')
                    ->label('Tác giả')
                    ->sortable()
                    ->searchable()
                    ->default('Không xác định')
                    ->formatStateUsing(function ($record) {
                        return $record->aiPostPrompt && $record->aiPostPrompt->user
                            ? $record->aiPostPrompt->user->name
                            : 'Không xác định';
                    }),
            ])
            ->filters([
                // Có thể thêm bộ lọc nếu cần
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Sửa bài viết')
                    ->icon('heroicon-o-pencil'),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa bài viết')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->before(function ($record) {
                        // Xóa bài viết trên Facebook trước khi xóa bản ghi
                        if ($record->facebook_post_id && $record->platform_account_id) {
                            try {
                                $platformAccount = PlatformAccount::find($record->platform_account_id);

                                if (!$platformAccount || !$platformAccount->access_token) {
                                    Log::error('Không tìm thấy thông tin fan page hoặc access token không hợp lệ', [
                                        'platform_account_id' => $record->platform_account_id,
                                    ]);
                                    Notification::make()
                                        ->title('Lỗi')
                                        ->body('Không thể xóa bài viết trên Facebook: Không tìm thấy thông tin fan page hoặc access token không hợp lệ.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $facebookService = app(FacebookService::class);
                                $facebookService->deletePost($record->facebook_post_id, $platformAccount->access_token);

                                Log::info('Xóa bài viết trên Facebook thành công', [
                                    'post_id' => $record->facebook_post_id,
                                    'record_id' => $record->id,
                                ]);

                                Notification::make()
                                    ->title('Thành công')
                                    ->body('Bài viết trên Facebook đã được xóa.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Lỗi khi xóa bài viết trên Facebook', [
                                    'post_id' => $record->facebook_post_id,
                                    'record_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('Lỗi')
                                    ->body('Không thể xóa bài viết trên Facebook: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                                // Tiếp tục xóa bản ghi ngay cả khi xóa bài viết trên FB thất bại
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xoá tất cả')
                        ->modalHeading('Xoá các bài viết đã chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xoá các bài viết này? Hành động này sẽ không thể hoàn tác.')
                        ->modalSubmitActionLabel('Xác nhận')
                        ->color('danger')
                        ->before(function ($records) {
                            // Xóa nhiều bài viết trên Facebook trước khi xóa các bản ghi
                            foreach ($records as $record) {
                                if ($record->facebook_post_id && $record->platform_account_id) {
                                    try {
                                        $platformAccount = PlatformAccount::find($record->platform_account_id);

                                        if (!$platformAccount || !$platformAccount->access_token) {
                                            Log::error('Không tìm thấy thông tin fan page hoặc access token không hợp lệ', [
                                                'platform_account_id' => $record->platform_account_id,
                                            ]);
                                            Notification::make()
                                                ->title('Lỗi')
                                                ->body("Không thể xóa bài viết trên Facebook cho bản ghi ID {$record->id}: Không tìm thấy thông tin fan page hoặc access token không hợp lệ.")
                                                ->danger()
                                                ->send();
                                            continue;
                                        }

                                        $facebookService = app(FacebookService::class);
                                        $facebookService->deletePost($record->facebook_post_id, $platformAccount->access_token);

                                        Log::info('Xóa bài viết trên Facebook thành công', [
                                            'post_id' => $record->facebook_post_id,
                                            'record_id' => $record->id,
                                        ]);

                                        Notification::make()
                                            ->title('Thành công')
                                            ->body("Bài viết trên Facebook cho bản ghi ID {$record->id} đã được xóa.")
                                            ->success()
                                            ->send();
                                    } catch (\Exception $e) {
                                        Log::error('Lỗi khi xóa bài viết trên Facebook', [
                                            'post_id' => $record->facebook_post_id,
                                            'record_id' => $record->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                        Notification::make()
                                            ->title('Lỗi')
                                            ->body("Không thể xóa bài viết trên Facebook cho bản ghi ID {$record->id}: " . $e->getMessage())
                                            ->danger()
                                            ->send();
                                        // Tiếp tục xóa các bản ghi khác ngay cả khi xóa bài viết trên FB thất bại
                                    }
                                }
                            }
                        }),
                ])->label('Tùy chọn'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepeatScheduleds::route('/'),
            'create' => Pages\CreateRepeatScheduled::route('/create'),
            'edit' => Pages\EditRepeatScheduled::route('/{record}/edit'),
        ];
    }
}   