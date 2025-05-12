<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\PostRepost;
use App\Services\FacebookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    protected $repost;

    public function __construct(Post $post, ?PostRepost $repost = null)
    {
        $this->post = $post;
        $this->repost = $repost;
    }

    public function handle(FacebookService $facebookService): void
    {
        $record = $this->repost ?? $this->post;
        $platformAccount = $record->platformAccount;

        if (!$platformAccount || $platformAccount->platform->name !== 'Facebook' || !$platformAccount->access_token) {
            Log::warning('Invalid platform account for Post ID: ' . $record->id);
            return;
        }

        try {
            $message = $this->post->content;
            if ($this->post->hashtags) {
                $message .= "\n" . implode(' ', $this->post->hashtags);
            }

            $media = $this->post->media ? [$this->post->media[0]] : null; // Chỉ lấy media đầu tiên để đơn giản

            $facebookPostId = $facebookService->postToPage(
                $platformAccount->page_id,
                $platformAccount->access_token,
                $message,
                $media,
                $platformAccount
            );

            if ($this->repost) {
                $this->repost->update(['facebook_post_id' => $facebookPostId]);
            } else {
                $this->post->update([
                    'facebook_post_id' => $facebookPostId,
                    'status' => 'published',
                ]);
            }

            Log::info("Đã đăng bài tự động lên trang {$platformAccount->name}: Post ID {$facebookPostId}");
        } catch (\Exception $e) {
            Log::error('Failed to auto-post for Post ID ' . $record->id . ': ' . $e->getMessage());
        }
    }
}