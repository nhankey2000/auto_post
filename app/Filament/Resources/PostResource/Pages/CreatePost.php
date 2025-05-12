<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\PlatformAccount;
use App\Models\Post;
use App\Models\PostRepost;
use Illuminate\Support\Facades\Log;
use Filament\Actions\CreateAction; // Import CreateAction
use Filament\Actions\Action; // Import Action

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tạo bài viết thành công';
    }

    protected function getFormActions(): array
    {
        return [
            CreateAction::make() // Sử dụng CreateAction từ Filament\Actions
                ->label('Tạo')
                ->submit('create'),
            CreateAction::make()
                ->label('Tạo và tạo thêm')
                ->submit('createAnother'),
            Action::make('cancel')
                ->label('Hủy')
                ->url($this->getResource()::getUrl('index')) // Sử dụng url() thay vì action() cho redirect
                ->color('gray'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Lưu platform_account_ids và reposts để xử lý sau
        $this->platformAccountIds = $data['platform_account_ids'] ?? [];
        $this->reposts = $data['reposts'] ?? [];

        // Kiểm tra nếu không có tài khoản nền tảng nào được chọn
        if (empty($this->platformAccountIds)) {
            throw new \Exception('Phải chọn ít nhất một tài khoản nền tảng.');
        }

        // Gán platform_account_id cho bản ghi gốc (lấy platform_account_id đầu tiên)
        $data['platform_account_id'] = $this->platformAccountIds[0];

        // Xóa platform_account_ids, platform_id và reposts khỏi data để không lưu vào bảng posts
        unset($data['platform_account_ids']);
        unset($data['platform_id']);
        unset($data['reposts']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Lưu lịch đăng lại cho bản ghi gốc
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
        }

        // Tạo các bản ghi Post khác cho các platform_account_id còn lại
        $remainingPlatformAccountIds = array_slice($this->platformAccountIds, 1); // Bỏ qua platform_account_id đầu tiên
        $baseData = $this->record->toArray();
        unset($baseData['id']); // Bỏ id để tạo bản ghi mới

        foreach ($remainingPlatformAccountIds as $platformAccountId) {
            $postData = array_merge($baseData, [
                'platform_account_id' => $platformAccountId,
            ]);
            $newPost = Post::create($postData);

            // Lưu lịch đăng lại cho bản ghi mới
            if (!empty($this->reposts)) {
                foreach ($this->reposts as $repost) {
                    if (in_array($platformAccountId, $repost['platform_account_ids'])) {
                        PostRepost::create([
                            'post_id' => $newPost->id,
                            'platform_account_id' => $platformAccountId,
                            'reposted_at' => $repost['reposted_at'],
                        ]);
                    }
                }
            }
        }
    }

    protected ?array $platformAccountIds = [];
    protected ?array $reposts = [];
}