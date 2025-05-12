<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageLibraryResource\Pages;
use App\Filament\Resources\ImageLibraryResource\RelationManagers;
use App\Models\ImageLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage; // Đảm bảo import Storage

class ImageLibraryResource extends Resource
{
    protected static ?string $model = ImageLibrary::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationGroup = 'Tự Động Đăng Bài';

    protected static ?string $navigationLabel = 'Thư Viện Media';

    protected static ?string $pluralLabel = 'Thư Viện Media';

    /**
     * Define the form schema for creating/editing an Image Library entry.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section: Media Upload and Category
                Forms\Components\Section::make('Tải Lên Media')
                    ->description('Chọn danh mục và tải lên hình ảnh hoặc video cho thư viện.')
                    ->schema([
                        Forms\Components\Grid::make(1)
                            ->schema([
                                // Category selection
                                Forms\Components\Select::make('category_id')
                                    ->label('Danh Mục Media')
                                    ->relationship('category', 'category')
                                    ->required()
                                    ->placeholder('Chọn danh mục')
                                    ->helperText('Chọn danh mục cho media (ví dụ: Du lịch, Ẩm thực).'),

                                // Media upload (hình ảnh và video)
                                Forms\Components\FileUpload::make('media')
                                    ->label('Media')
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
                                    ->saveUploadedFileUsing(function ($state, $get) {
                                        \Illuminate\Support\Facades\Log::info('Media FileUpload state:', [
                                            'state' => $state,
                                            'count' => is_array($state) ? count($state) : 0,
                                            'category_id' => $get('category_id')
                                        ]);

                                        if (!is_array($state) || empty($state)) {
                                            \Illuminate\Support\Facades\Log::warning('No media uploaded or invalid state', ['state' => $state]);
                                            return null;
                                        }

                                        // Tạo khóa để ngăn xử lý trùng lặp
                                        $fileNames = array_map(function ($file) {
                                            return $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : 'invalid';
                                        }, $state);
                                        $lockKey = 'media_upload_lock_' . md5($get('category_id') . implode('|', $fileNames));

                                        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                                            \Illuminate\Support\Facades\Log::warning('Duplicate media submit detected:', [
                                                'lock_key' => $lockKey,
                                                'files' => $fileNames
                                            ]);
                                            return null;
                                        }

                                        \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addSeconds(10));

                                        $categoryId = $get('category_id');
                                        foreach ($state as $file) {
                                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                                $type = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
                                                $directory = $type === 'video' ? 'videos' : 'images';
                                                $path = $file->store($directory, 'public');
                                                \Illuminate\Support\Facades\Log::info('Saving media:', [
                                                    'path' => $path,
                                                    'category_id' => $categoryId,
                                                    'file' => $file->getClientOriginalName(),
                                                    'lock_key' => $lockKey,
                                                    'type' => $type
                                                ]);
                                                ImageLibrary::create([
                                                    'category_id' => $categoryId,
                                                    'item' => $path,
                                                    'type' => $type,
                                                ]);
                                            }
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

    /**
     * Define the table schema for displaying Image Library entries.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.category')
                    ->label('Danh Mục')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'font-semibold text-gray-200']),
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'image' => 'success',
                        'video' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::title($state))
                    ->extraAttributes(['class' => 'text-gray-400']),
                Tables\Columns\TextColumn::make('item')
                    ->label('Media')
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $mediaPath = $state;
                        $mediaType = strtolower($record->type);
                        $mediaUrl = null;
                        $fileExists = false;
                        $filePath = null;

                        if (is_string($mediaPath) && !empty($mediaPath)) {
                            // Sử dụng Storage facade trực tiếp
                            $mediaUrl = Storage::disk('public')->url($mediaPath);
                            $filePath = public_path('storage/' . $mediaPath);
                            $fileExists = file_exists($filePath);
                        }

                        \Illuminate\Support\Facades\Log::info('Media display debug', [
                            'mediaPath' => $mediaPath,
                            'mediaType' => $mediaType,
                            'mediaUrl' => $mediaUrl,
                            'fileExists' => $fileExists,
                            'filePath' => $filePath,
                        ]);

                        if ($mediaType === 'image' && $mediaUrl && $fileExists) {
                            return '<img src="' . $mediaUrl . '" alt="Xem trước ảnh" class="object-cover rounded-lg shadow-sm" style="width: 60px; height: 60px;">';
                        } elseif ($mediaType === 'video' && $mediaUrl && $fileExists) {
                            return '<video width="60" height="60" controls class="object-cover rounded-lg shadow-sm"><source src="' . $mediaUrl . '" type="video/mp4">Trình duyệt của bạn không hỗ trợ thẻ video.</video>';
                        } elseif ($mediaUrl && !$fileExists) {
                            return '<span class="text-red-500 text-xs">File không tồn tại: ' . $mediaPath . '</span>';
                        }

                        return '<img src="https://via.placeholder.com/60" alt="Ảnh mặc định" class="object-cover rounded-lg shadow-sm" style="width: 60px; height: 60px;">';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo Lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-400']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Danh Mục')
                    ->relationship('category', 'category'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại Media')
                    ->options([
                        'image' => 'Hình Ảnh',
                        'video' => 'Video',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa Tất Cả')
                        ->modalHeading('Xóa Các Media Đã Chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xóa các media này? Hành động này sẽ không thể hoàn tác.')
                        ->modalButton('Xác Nhận')
                        ->color('danger'),
                ])->label('Tùy Chọn'),
            ]);
    }

    /**
     * Define the relations for the Image Library resource.
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
     * Define the pages for the Image Library resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImageLibraries::route('/'),
            'create' => Pages\CreateImageLibrary::route('/create'),
            'edit' => Pages\EditImageLibrary::route('/{record}/edit'),
        ];
    }
}