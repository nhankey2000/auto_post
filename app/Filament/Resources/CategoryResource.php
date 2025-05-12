<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tự Động Đăng Bài';

    protected static ?string $navigationLabel = 'Danh Mục Hình Ảnh';

    protected static ?string $pluralLabel = 'Danh Mục Hình Ảnh';

    /**
     * Define the form schema for creating/editing a Category.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section: Category Information
                Forms\Components\Section::make('Thông Tin Danh Mục')
                    ->description('Nhập thông tin chi tiết về danh mục hình ảnh.')
                    ->schema([
                        Forms\Components\Grid::make(1)
                            ->schema([
                                // Category name
                                Forms\Components\TextInput::make('category')
                                    ->label('Tên Danh Mục')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ví dụ: Du lịch, Ẩm thực...')
                                    ->helperText('Nhập tên danh mục (ví dụ: Du lịch, Ẩm thực).')
                                    ->extraAttributes(['class' => 'bg-gray-800 text-gray-300']),
                            ]),
                    ])
                    ->collapsible()
                    ->extraAttributes(['class' => 'bg-gray-900 border border-gray-700']),
            ]);
    }

    /**
     * Define the table schema for displaying Categories.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('Tên Danh Mục')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'font-semibold text-gray-200']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo Lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-400']),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập Nhật Lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-400']),
            ])
            ->filters([
                //
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
                        ->modalHeading('Xóa Các Danh Mục Đã Chọn')
                        ->modalSubheading('Bạn có chắc chắn muốn xóa các danh mục này? Hành động này sẽ không thể hoàn tác.')
                        ->modalButton('Xác Nhận')
                        ->color('danger'),
                ])->label('Tùy Chọn'),
            ]);
    }

    /**
     * Define the relations for the Category resource.
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
     * Define the pages for the Category resource.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}