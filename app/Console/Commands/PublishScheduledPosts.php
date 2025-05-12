<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostRepost;
use App\Models\PlatformAccount;
use App\Services\FacebookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';
    protected $description = 'Publish scheduled posts and reposts when their scheduled_at or reposted_at time is reached';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔍 Đang kiểm tra các bài viết và lịch đăng lại đã đến thời gian đăng...');

        // Lấy thời gian hiện tại
        $now = now();

        // 1. Kiểm tra và đăng các bài viết lần đầu (scheduled posts)
        $posts = Post::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $facebookService = app(FacebookService::class);

        if ($posts->isNotEmpty()) {
            $this->publishPosts($posts, $facebookService);
        } else {
            $this->info('📭 Không có bài viết nào cần đăng lúc này.');
        }

        // 2. Kiểm tra và đăng lại các bài viết (reposts)
        // Chỉ xử lý repost nếu bài viết gốc đã được đăng (status = 'published')
        $reposts = PostRepost::whereNotNull('reposted_at')
            ->where('reposted_at', '<=', $now)
            ->whereNull('facebook_post_id') // Chỉ xử lý các repost chưa được đăng
            ->with(['post' => function ($query) {
                $query->where('status', 'published'); // Chỉ lấy repost của bài viết đã được đăng lần đầu
            }])
            ->get()
            ->filter(function ($repost) {
                return !is_null($repost->post); // Loại bỏ repost nếu bài viết gốc không tồn tại hoặc chưa được đăng
            });

        if ($reposts->isNotEmpty()) {
            $this->publishReposts($reposts, $facebookService);
        } else {
            $this->info('🔁 Không có bài viết nào cần đăng lại lúc này.');
        }

        $this->info('✅ Hoàn tất kiểm tra và xử lý bài viết hẹn giờ.');
    }

    private function publishPosts($posts, FacebookService $facebookService)
    {
        foreach ($posts as $post) {
            try {
                $message = $this->preparePostMessage($post);
                $mediaData = $this->prepareMediaPaths($post);

                $platformAccount = $post->platformAccount;
                if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                    $pageId = $platformAccount->page_id;
                    if (!$pageId) {
                        throw new \Exception('Page ID not found for platform account: ' . $platformAccount->name);
                    }

                    // Post based on media type
                    if ($mediaData['type'] === 'video') {
                        $facebookPostId = $facebookService->postVideoToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                    } else {
                        $facebookPostId = $facebookService->postToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                    }

                    // Ghi log: Đăng bài lần đầu
                    Log::info("Đăng bài lần đầu cho bài viết ID {$post->id} vào thời gian " . now()->toDateTimeString());

                    $post->update([
                        'facebook_post_id' => $facebookPostId,
                        'status' => 'published',
                        'scheduled_at' => null,
                    ]);

                    $this->info("Published scheduled post ID {$post->id} to Facebook: Post ID {$facebookPostId}");
                    Log::info("Published scheduled post to page {$platformAccount->name}: Post ID {$facebookPostId}");

                    // Kiểm tra lịch đăng lại và ghi log
                    $nextRepost = PostRepost::where('post_id', $post->id)
                        ->whereNotNull('reposted_at')
                        ->where('reposted_at', '>', now())
                        ->whereNull('facebook_post_id')
                        ->orderBy('reposted_at', 'asc')
                        ->first();

                    if ($nextRepost) {
                        Log::info("Thời gian đăng bài tiếp theo cho bài viết ID {$post->id} là {$nextRepost->reposted_at} (repost ID {$nextRepost->id}).");
                    } else {
                        Log::info("Không có lịch đăng lại cho bài viết ID {$post->id}.");
                    }

                    // Gửi thông báo (tùy chọn)
                    \Filament\Notifications\Notification::make()
                        ->title('Bài viết đã được đăng')
                        ->body("Bài viết ID {$post->id} đã được đăng lên trang {$platformAccount->name}.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception('Platform account not found or not a Facebook account for Post ID: ' . $post->id);
                }
            } catch (\Exception $e) {
                Log::error("Error publishing scheduled post ID {$post->id}: " . $e->getMessage());
                $this->error("Failed to publish post ID {$post->id}: " . $e->getMessage());
            }
        }
    }

    private function publishReposts($reposts, FacebookService $facebookService)
    {
        foreach ($reposts as $repost) {
            try {
                $post = $repost->post;
                $message = $this->preparePostMessage($post);
                $mediaData = $this->prepareMediaPaths($post);

                $platformAccount = PlatformAccount::find($repost->platform_account_id);
                if ($platformAccount && $platformAccount->platform->name === 'Facebook' && $platformAccount->access_token) {
                    $pageId = $platformAccount->page_id;
                    if (!$pageId) {
                        throw new \Exception('Page ID not found for platform account: ' . $platformAccount->name);
                    }

                    // Post based on media type
                    if ($mediaData['type'] === 'video') {
                        $facebookPostId = $facebookService->postVideoToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                    } else {
                        $facebookPostId = $facebookService->postToPage($pageId, $platformAccount->access_token, $message, $mediaData['paths']);
                    }

                    // Ghi log: Đăng lại bài viết
                    Log::info("Đăng lại bài viết ID {$post->id} (repost ID {$repost->id}) vào thời gian " . now()->toDateTimeString());

                    $repost->update([
                        'facebook_post_id' => $facebookPostId,
                        'reposted_at' => null, // Xóa reposted_at sau khi đăng
                    ]);

                    $this->info("Published repost ID {$repost->id} for post ID {$post->id} to Facebook: Post ID {$facebookPostId}");
                    Log::info("Published repost to page {$platformAccount->name}: Post ID {$facebookPostId}");

                    // Kiểm tra lịch đăng lại tiếp theo và ghi log
                    $nextRepost = PostRepost::where('post_id', $post->id)
                        ->whereNotNull('reposted_at')
                        ->where('reposted_at', '>', now())
                        ->whereNull('facebook_post_id')
                        ->orderBy('reposted_at', 'asc')
                        ->first();

                    if ($nextRepost) {
                        Log::info("Thời gian đăng bài tiếp theo cho bài viết ID {$post->id} là {$nextRepost->reposted_at} (repost ID {$nextRepost->id}).");
                    } else {
                        Log::info("Không có lịch đăng lại tiếp theo cho bài viết ID {$post->id}.");
                    }

                    // Gửi thông báo (tùy chọn)
                    \Filament\Notifications\Notification::make()
                        ->title('Bài viết đã được đăng lại')
                        ->body("Bài viết ID {$post->id} đã được đăng lại trên trang {$platformAccount->name}.")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception('Platform account not found or not a Facebook account for Repost ID: ' . $repost->id);
                }
            } catch (\Exception $e) {
                Log::error("Error publishing repost ID {$repost->id}: " . $e->getMessage());
                $this->error("Failed to publish repost ID {$repost->id}: " . $e->getMessage());
            }
        }
    }

    private function preparePostMessage($post): string
    {
        $boldTitle = $this->toBoldUnicode($post->title);
        $content = $this->formatContentForPost($post->content);
        $message = $boldTitle . "\n\n" . $content;

        if ($post->hashtags) {
            // Tách hashtag từ nội dung
            preg_match_all('/#[\wÀ-ỹ]+/', $content, $existingHashtags);
            $existingHashtags = array_map('strtolower', $existingHashtags[0]);

            // Loại bỏ hashtag trùng lặp
            $newHashtags = array_filter($post->hashtags, function ($hashtag) use ($existingHashtags) {
                return !in_array(strtolower('#' . $hashtag), $existingHashtags);
            });

            if (!empty($newHashtags)) {
                $message .= "\n" . implode(' ', array_map(function ($tag) {
                    return '#' . $tag;
                }, $newHashtags));
            }
        }

        Log::info('Prepared post message', [
            'post_id' => $post->id,
            'message' => $message,
            'newlines' => substr_count($message, "\n"),
        ]);

        return $message;
    }

    private function prepareMediaPaths($post): array
    {
        $mediaPaths = [];
        $mediaType = 'image'; // Mặc định là ảnh
        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'heif', 'webp'];
        $allowedVideoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        $maxImageSize = 4 * 1024 * 1024; // 4 MB for images
        $maxVideoSize = 100 * 1024 * 1024; // 100 MB for videos

        if ($post->media) {
            foreach ($post->media as $mediaPath) {
                $absolutePath = storage_path('app/public/' . $mediaPath);
                if (file_exists($absolutePath)) {
                    $fileSize = filesize($absolutePath);
                    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

                    // Xác định loại media
                    if (in_array($extension, $allowedImageExtensions)) {
                        if ($fileSize > $maxImageSize) {
                            Log::warning('File ảnh vượt quá kích thước cho phép (4 MB)', [
                                'post_id' => $post->id,
                                'media_path' => $mediaPath,
                                'file_size' => $fileSize,
                            ]);
                            throw new \Exception("File ảnh {$mediaPath} vượt quá kích thước cho phép (4 MB).");
                        }
                    } elseif (in_array($extension, $allowedVideoExtensions)) {
                        $mediaType = 'video';
                        if ($fileSize > $maxVideoSize) {
                            Log::warning('File video vượt quá kích thước cho phép (100 MB)', [
                                'post_id' => $post->id,
                                'media_path' => $mediaPath,
                                'file_size' => $fileSize,
                            ]);
                            throw new \Exception("File video {$mediaPath} vượt quá kích thước cho phép (100 MB).");
                        }
                    } else {
                        Log::warning('Định dạng file không được hỗ trợ', [
                            'post_id' => $post->id,
                            'media_path' => $mediaPath,
                            'extension' => $extension,
                        ]);
                        throw new \Exception("File {$mediaPath} có định dạng không được hỗ trợ. Chỉ hỗ trợ ảnh (JPG, PNG, GIF, TIFF, HEIF, WebP) hoặc video (MP4, MOV, AVI, WMV, FLV, MKV, WEBM).");
                    }

                    $mediaPaths[] = $absolutePath;
                } else {
                    Log::warning('File media không tồn tại', [
                        'post_id' => $post->id,
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

    private function toBoldUnicode(string $text): string
    {
        $boldMap = [
            'A' => '𝐀', 'B' => '𝐁', 'C' => '𝐂', 'D' => '𝐃', 'E' => '𝐄', 'F' => '𝐅', 'G' => '𝐆', 'H' => '𝐇', 'I' => '𝐈', 'J' => '𝐉',
            'K' => '𝐊', 'L' => '𝐋', 'M' => '𝐌', 'N' => '𝐍', 'O' => '𝐎', 'P' => '𝐏', 'Q' => '𝐐', 'R' => '𝐑', 'S' => '𝐒', 'T' => '𝐓',
            'U' => '𝐔', 'V' => '𝐕', 'W' => '𝐖', 'X' => '𝐗', 'Y' => '𝐘', 'Z' => '𝐙',
            'a' => '𝐚', 'b' => '𝐛', 'c' => '𝐜', 'd' => '𝐝', 'e' => '𝐞', 'f' => '𝐟', 'g' => '𝐠', 'h' => '𝐡', 'i' => '𝐢', 'j' => '𝐣',
            'k' => '𝐤', 'l' => '𝐥', 'm' => '𝐦', 'n' => '𝐧', 'o' => '𝐨', 'p' => '𝐩', 'q' => '𝐪', 'r' => '𝐫', 's' => '𝐬', 't' => '𝐭',
            'u' => '𝐮', 'v' => '𝐯', 'w' => '𝐰', 'x' => '𝐱', 'y' => '𝐲', 'z' => '𝐳',
            '0' => '𝟎', '1' => '𝟏', '2' => '𝟐', '3' => '𝟑', '4' => '𝟒', '5' => '𝟓', '6' => '𝟔', '7' => '𝟕', '8' => '𝟖', '9' => '𝟗',
        ];

        $boldText = '';
        foreach (mb_str_split($text) as $char) {
            $boldText .= $boldMap[$char] ?? $char;
        }

        return $boldText;
    }

    private function formatContentForPost(string $content): string
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
}