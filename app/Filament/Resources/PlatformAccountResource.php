<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformAccountResource\Pages;
use App\Models\PlatformAccount;
use App\Services\Connection\Connection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class PlatformAccountResource extends Resource
{
    protected static ?string $model = PlatformAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Lên Lịch Đăng Bài';
    protected static ?string $label = 'Tài Khoản Nền Tảng';
    protected static ?string $pluralLabel = 'Tài Khoản Nền Tảng';

    /**
     * Define the form schema for creating/editing a Platform Account.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section 1: Basic Information
                Forms\Components\Section::make('Thông Tin Cơ Bản')
                    ->description('Cung cấp thông tin cơ bản về tài khoản nền tảng.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Platform selection
                                Forms\Components\Select::make('platform_id')
                                    ->label('Nền Tảng')
                                    ->relationship('platform', 'name')
                                    ->required()
                                    ->placeholder('Chọn nền tảng'),

                                // Account name
                                Forms\Components\TextInput::make('name')
                                    ->label('Tên Tài Khoản')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

                // Section 2: API Credentials
                Forms\Components\Section::make('Thông Tin API')
                    ->description('Nhập các thông tin API cần thiết để kết nối với nền tảng.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // App ID
                                Forms\Components\TextInput::make('app_id')
                                    ->label('App ID')
                                    ->maxLength(255),

                                // App Secret
                                Forms\Components\TextInput::make('app_secret')
                                    ->label('App Secret')
                                    ->maxLength(255),

                                // Access Token
                                Forms\Components\TextInput::make('access_token')
                                    ->label('Access Token')
                                    ->maxLength(255),

                                // API Key
                                Forms\Components\TextInput::make('api_key')
                                    ->label('API Key')
                                    ->maxLength(255),

                                // API Secret
                                Forms\Components\TextInput::make('api_secret')
                                    ->label('API Secret')
                                    ->maxLength(255),

                                // Page ID
                                Forms\Components\TextInput::make('page_id')
                                    ->label('Page ID')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),

                // Section 3: Additional Information
                Forms\Components\Section::make('Thông Tin Bổ Sung')
                    ->description('Cung cấp dữ liệu bổ sung và thời gian hết hạn.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Extra Data
                                Forms\Components\KeyValue::make('extra_data')
                                    ->label('Dữ Liệu Bổ Sung')
                                    ->columnSpanFull(),

                                // Expiration Date
                                Forms\Components\DateTimePicker::make('expires_at')
                                    ->label('Ngày Hết Hạn')
                                    ->nullable()
                                    ->displayFormat('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),
            ]);
    }

    /**
     * Define the table schema for displaying Platform Accounts.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform.name')
                    ->label('Nền Tảng')
                    ->sortable()
                    ->extraAttributes(['class' => 'font-semibold text-gray-200']),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên Tài Khoản')
                    ->limit(20)
                    ->searchable()
                    ->extraAttributes(['class' => 'text-gray-300']),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Ngày Hết Hạn')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        Log::info('Expires_at state in PlatformAccountResource', [
                            'state' => $state,
                            'type' => gettype($state),
                            'is_carbon' => $state instanceof \Carbon\Carbon,
                        ]);

                        try {
                            if ($state) {
                                $date = $state instanceof \Carbon\Carbon
                                    ? $state
                                    : \Carbon\Carbon::parse($state);
                                return $date->format('d/m/Y H:i');
                            }
                            return 'Vô thời hạn';
                        } catch (\Exception $e) {
                            Log::error('Error formatting expires_at', [
                                'state' => $state,
                                'error' => $e->getMessage(),
                            ]);
                            return 'Không hợp lệ';
                        }
                    })
                    ->extraAttributes(['class' => 'text-gray-400']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày Tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => 'text-gray-400']),
            ])
            ->filters([
                // Có thể thêm bộ lọc nếu cần
            ])
            ->actions([
                Action::make('check_connection')
                    ->label('Kiểm Tra Kết Nối')
                    ->icon('heroicon-o-wifi')
                    ->color('success')
                    ->action(function (PlatformAccount $record) {
                        $connectionService = new Connection();
                        $result = $connectionService->check($record);

                        if ($result && is_array($result) && $result['success']) {
                            if (isset($result['expires_at']) && $result['expires_at'] instanceof \DateTime) {
                                $record->expires_at = $result['expires_at'];
                                $record->save();

                                \Filament\Notifications\Notification::make()
                                    ->title('Kết Nối Thành Công')
                                    ->body('Ngày hết hạn token: ' . $record->expires_at->format('d/m/Y H:i:s'))
                                    ->success()
                                    ->send();
                            } else {
                                $record->expires_at = null;
                                $record->save();

                                \Filament\Notifications\Notification::make()
                                    ->title('Kết Nối Thành Công')
                                    ->body('Token vô thời hạn')
                                    ->success()
                                    ->send();
                            }
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Kết Nối Thất Bại')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('view_analytics')
                    ->label('Xem Thống Kê')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->url(fn (PlatformAccount $record): string => static::getUrl('analytics', ['record' => $record]))
                    ->visible(fn (PlatformAccount $record): bool => $record->platform_id == 1),
                Action::make('view_chart')
                    ->label('Xem Biểu Đồ')
                    ->icon('heroicon-o-arrow-trending-up') // Updated icon name
                    ->color('info')
                    ->url(fn (PlatformAccount $record): string => static::getUrl('chart', ['record' => $record]))
                    ->visible(fn (PlatformAccount $record): bool => $record->platform_id == 1),
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
                    Tables\Actions\BulkAction::make('check_all_connections')
                        ->label('Kiểm Tra Kết Nối Tất Cả')
                        ->icon('heroicon-o-wifi')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $successCount = 0;
                            $errorMessages = [];

                            $connectionService = new Connection();

                            foreach ($records as $record) {
                                try {
                                    $result = $connectionService->check($record);

                                    if ($result && is_array($result) && $result['success']) {
                                        if (isset($result['expires_at']) && $result['expires_at'] instanceof \DateTime) {
                                            $record->expires_at = $result['expires_at'];
                                            $record->save();
                                            $successCount++;
                                            Log::info("Kết nối thành công cho tài khoản {$record->name}", [
                                                'expires_at' => $record->expires_at->format('d/m/Y H:i:s'),
                                            ]);
                                        } else {
                                            $record->expires_at = null;
                                            $record->save();
                                            $successCount++;
                                            Log::info("Kết nối thành công cho tài khoản {$record->name}", [
                                                'expires_at' => 'Vô thời hạn',
                                            ]);
                                        }
                                    } else {
                                        $errorMessages[] = "Tài khoản {$record->name}: Kết nối thất bại.";
                                        Log::warning("Kết nối thất bại cho tài khoản {$record->name}");
                                    }
                                } catch (\Exception $e) {
                                    $errorMessages[] = "Tài khoản {$record->name}: Lỗi - {$e->getMessage()}";
                                    Log::error("Lỗi khi kiểm tra kết nối cho tài khoản {$record->name}", [
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            if ($successCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Kết Nối Thành Công')
                                    ->body("Đã kiểm tra thành công {$successCount} tài khoản.")
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
                                    ->title('Không Có Tài Khoản Nào Được Kiểm Tra')
                                    ->body('Vui lòng chọn ít nhất một tài khoản để kiểm tra.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa Tất Cả')
                        ->modalHeading('Xóa Các Tài Khoản Đã Chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xóa các tài khoản này? Hành động này sẽ không thể hoàn tác.')
                        ->modalButton('Xác Nhận')
                        ->color('danger')
                        ->deselectRecordsAfterCompletion(),
                ])->label('Tùy Chọn'),
            ]);
    }

    /**
     * Define the pages for the Platform Account resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformAccounts::route('/'),
            'create' => Pages\CreatePlatformAccount::route('/create'),
            'edit' => Pages\EditPlatformAccount::route('/{record}/edit'),
            'analytics' => Pages\AnalyticsPlatformAccount::route('/{record}/analytics'),
            'chart' => Pages\ChartPlatformAccount::route('/{record}/chart'),
            
        ];
    }
}