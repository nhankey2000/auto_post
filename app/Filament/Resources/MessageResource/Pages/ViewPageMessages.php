<?php

namespace App\Filament\Resources\MessageResource\Pages;

use App\Filament\Resources\MessageResource;
use Filament\Resources\Pages\Page;
use App\Models\PlatformAccount;
use App\Services\FacebookService;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Carbon\Carbon;

class ViewPageMessages extends Page
{
    use WithFileUploads;

    protected static string $resource = MessageResource::class;
    protected static string $view = 'filament.resources.message-resource.pages.view-page-messages';

    public function getTitle(): string
    {
        return 'Quản Lý Tin Nhắn';
    }

    public function getBreadcrumb(): string
    {
        return 'Quản Lý Tin Nhắn';
    }

    public array $messages = [];
    public ?string $selectedConversationId = null;
    public ?string $replyMessage = '';
    public $uploadFile = [];
    public ?string $currentPageAvatar = null;
    public array $avatarCache = [];
    public bool $hasPendingUpload = false;

    public function mount(): void
    {
        $platformAccounts = PlatformAccount::all();

        if ($platformAccounts->isEmpty()) {
            Log::warning('No platform accounts found.');
            Notification::make()
                ->title('Cảnh báo')
                ->warning()
                ->body('Không tìm thấy trang nào.')
                ->send();
            $this->messages = [];
            return;
        }

        $fb = app(FacebookService::class);
        $allMessages = [];

        foreach ($platformAccounts as $account) {
            $raw = $fb->getPageMessages($account->page_id, $account->access_token);

            if (!is_array($raw) || isset($raw['error'])) {
                Log::error('Failed to fetch messages from Facebook API for page', [
                    'page_id' => $account->page_id,
                    'error' => $raw['error'] ?? 'Invalid data returned',
                ]);
                continue;
            } 
            
            Log::info('Raw messages from Facebook API', [
                'page_id' => $account->page_id,
                'raw_messages_count' => count($raw),
            ]);

            $pageAvatar = $fb->getPageAvatar($account->page_id, $account->access_token) ?? asset('images/default-avatar.png');
            $this->avatarCache[$account->page_id] = $pageAvatar;

            $grouped = collect($raw)->groupBy('conversation_id')->filter()->map(function ($items, $conversationId) use ($account) {
                $firstMessage = $items->first();
                if (!$firstMessage) {
                    return null;
                }

                $participants = $firstMessage['participants'] ?? [];
                $sender = 'Người dùng';
                $senderId = null;

                foreach ($participants as $participant) {
                    if ($participant['id'] !== $account->page_id) {
                        $sender = $participant['name'] ?? $participant['id'];
                        $senderId = $participant['id'];
                        break;
                    }
                }

                $avatarUrl = asset('images/avatar.png');

                $sortedMessages = collect($items)->sortBy('created_time')->values();
                $lastMessage = $sortedMessages->last();
                $isUnread = $this->selectedConversationId !== $firstMessage['conversation_id'];

                return [
                    'sender' => $sender,
                    'last_message' => $lastMessage['message'] ?? '',
                    'last_message_time' => $lastMessage['created_time'] ?? '',
                    'messages' => $sortedMessages->all(),
                    'conversation_id' => $firstMessage['conversation_id'],
                    'avatar_url' => $avatarUrl,
                    'sender_id' => $senderId,
                    'unread' => $isUnread,
                    'page_id' => $account->page_id,
                    'page_name' => $account->name,
                ];
            })->filter()->values()->all();

            $allMessages = array_merge($allMessages, $grouped);
        }

        usort($allMessages, function ($a, $b) {
            return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
        });

        $this->messages = $allMessages;

        Log::info('Grouped messages from all pages', [
            'total_messages_count' => count($this->messages),
        ]);

        $this->selectedConversationId = !empty($this->messages) ? $this->messages[0]['conversation_id'] : null;

        if ($this->selectedConversationId) {
            $selectedConv = $this->getSelectedConversationProperty();
            $this->currentPageAvatar = $this->avatarCache[$selectedConv['page_id']] ?? asset('images/default-avatar.png');
        }

        Log::info('Initial selectedConversationId', [
            'selectedConversationId' => $this->selectedConversationId,
            'selectedConversation' => $this->getSelectedConversationProperty(),
        ]);
    }

    public function updatedSelectedConversationId()
    {
        $selectedConv = $this->getSelectedConversationProperty();
        if ($selectedConv) {
            $this->currentPageAvatar = $this->avatarCache[$selectedConv['page_id']] ?? asset('images/default-avatar.png');
        }

        Log::info('Updated selectedConversationId', [
            'selectedConversationId' => $this->selectedConversationId,
            'selectedConversation' => $this->getSelectedConversationProperty(),
            'currentPageAvatar' => $this->currentPageAvatar,
        ]);

        $this->markAsRead();
        
    }

    public function updatedUploadFile()
    {
        $this->validate([
            'uploadFile.*' => 'file|mimes:jpeg,png,gif,mp4,webm,mp3,pdf,doc,docx,xls,xlsx,ppt,pptx|max:5120', // 5MB
        ]);
    }

    public function getSelectedConversationProperty()
    {
        return collect($this->messages)->firstWhere('conversation_id', $this->selectedConversationId);
    }

    public function sendReply()
    {
        if (!$this->replyMessage && empty($this->uploadFile)) {
            Notification::make()
                ->title('Lỗi')
                ->danger()
                ->body('Vui lòng nhập tin nhắn hoặc chọn tệp.')
                ->send();
            return;
        }

        if (!$this->selectedConversationId) {
            Notification::make()
                ->title('Lỗi')
                ->danger()
                ->body('Vui lòng chọn hội thoại.')
                ->send();
            return;
        }

        $fb = app(FacebookService::class);
        $conversation = $this->getSelectedConversationProperty();

        if (!$conversation || !isset($conversation['sender_id'])) {
            Notification::make()
                ->title('Lỗi')
                ->danger()
                ->body('Hội thoại không hợp lệ.')
                ->send();
            return;
        }

        $platformAccount = PlatformAccount::where('page_id', $conversation['page_id'])->first();

        if (!$platformAccount) {
            Notification::make()
                ->title('Lỗi')
                ->danger()
                ->body('Không tìm thấy trang tương ứng.')
                ->send();
            return;
        }

        // Kiểm tra 24 giờ tương tác
        $lastMessageTime = Carbon::parse($conversation['last_message_time']);
        if (Carbon::now()->diffInHours($lastMessageTime) > 24) {
            Notification::make()
                ->title('Không thể gửi')
                ->danger()
                ->body('Đã quá 24 giờ kể từ lần tương tác cuối.')
                ->send();
            return;
        }

        try {
            // Gửi tin nhắn văn bản nếu có
            if ($this->replyMessage) {
                $fb->replyToMessage(
                    $conversation['sender_id'],
                    $platformAccount->access_token,
                    $this->replyMessage
                );
            }

            // Gửi file đính kèm nếu có
            foreach ($this->uploadFile as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    if ($file->getSize() > $maxSize) {
                        throw new \Exception("File {$file->getClientOriginalName()} vượt quá 5MB.");
                    }

                    $allowedTypes = [
                        'image/jpeg', 'image/png', 'image/gif',
                        'video/mp4', 'video/webm',
                        'audio/mpeg',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    ];
                    if (!in_array($file->getMimeType(), $allowedTypes)) {
                        throw new \Exception("File {$file->getClientOriginalName()} không được hỗ trợ.");
                    }

                    $realPath = $file->getRealPath();

                    if (!file_exists($realPath)) {
                        throw new \Exception("File {$file->getClientOriginalName()} không tồn tại trên máy chủ.");
                    }

                    Log::info('Đang gửi file:', [
                        'path' => $realPath,
                        'mime' => $file->getMimeType(),
                    ]);

                    $this->sendAttachment(
                        $conversation['sender_id'],
                        $platformAccount->access_token,
                        $realPath,
                        $file->getMimeType()
                    );
                } else {
                    throw new \Exception("Tệp không hợp lệ.");
                }
            }

            Notification::make()
                ->title('Thành công')
                ->success()
                ->body('Tin nhắn đã được gửi.')
                ->send();

            // Reset sạch dữ liệu sau khi gửi
            $this->reset('replyMessage', 'uploadFile', 'hasPendingUpload');
            $this->refreshMessages();
            $this->markOtherConversationsAsUnread();

        } catch (\Exception $e) {
            Log::error('Lỗi khi gửi tin nhắn:', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation['sender_id'] ?? 'N/A',
            ]);

            Notification::make()
                ->title('Lỗi')
                ->danger()
                ->body('Không thể gửi: ' . $e->getMessage())
                ->send();
        }
    }

    public function sendAttachment(string $recipientId, string $accessToken, string $filePath, string $mimeType)
    {
        $type = str_contains($mimeType, 'image') ? 'image' :
                (str_contains($mimeType, 'video') ? 'video' :
                (str_contains($mimeType, 'audio') ? 'audio' :
                (str_contains($mimeType, 'pdf') || 
                 str_contains($mimeType, 'msword') || 
                 str_contains($mimeType, 'wordprocessingml') || 
                 str_contains($mimeType, 'ms-excel') || 
                 str_contains($mimeType, 'spreadsheetml') || 
                 str_contains($mimeType, 'ms-powerpoint') || 
                 str_contains($mimeType, 'presentationml') ? 'file' : 'file')));

        // B1: Upload file trước (chưa gửi)
        $uploadResponse = Http::attach(
            'filedata',
            file_get_contents($filePath),
            basename($filePath)
        )->post("https://graph.facebook.com/v20.0/me/message_attachments", [
            'message' => json_encode([
                'attachment' => [
                    'type' => $type,
                    'payload' => ['is_reusable' => true],
                ],
            ]),
            'access_token' => $accessToken,
        ]);

        if (!$uploadResponse->successful()) {
            throw new \Exception('Không thể upload file: ' . $uploadResponse->body());
        }

        $attachmentId = $uploadResponse->json('attachment_id');

        // B2: Gửi file bằng attachment_id
        $sendResponse = Http::post("https://graph.facebook.com/v20.0/me/messages", [
            'recipient' => ['id' => $recipientId],
            'message' => [
                'attachment' => [
                    'type' => $type,
                    'payload' => [
                        'attachment_id' => $attachmentId,
                    ],
                ],
            ],
            'access_token' => $accessToken,
        ]);

        if (!$sendResponse->successful()) {
            throw new \Exception('Gửi file thất bại: ' . $sendResponse->body());
        }

        return $sendResponse->json();
    }

    protected function refreshMessages()
    {
        $platformAccounts = PlatformAccount::all();
        $fb = app(FacebookService::class);
        $allMessages = [];

        foreach ($platformAccounts as $account) {
            $raw = $fb->getPageMessages($account->page_id, $account->access_token);

            if (!is_array($raw) || isset($raw['error'])) {
                Log::error('Failed to refresh messages for page', [
                    'page_id' => $account->page_id,
                    'error' => $raw['error'] ?? 'Invalid data returned',
                ]);
                continue;
            }

            $grouped = collect($raw)->groupBy('conversation_id')->filter()->map(function ($items, $conversationId) use ($fb, $account) {
                $firstMessage = $items->first();
                if (!$firstMessage) {
                    return null;
                }

                $participants = $firstMessage['participants'] ?? [];
                $sender = 'Người dùng';
                $senderId = null;

                foreach ($participants as $participant) {
                    if ($participant['id'] !== $account->page_id) {
                        $sender = $participant['name'] ?? $participant['id'];
                        $senderId = $participant['id'];
                        break;
                    }
                }

                $avatarUrl = asset('images/avatar.png');

                $sortedMessages = collect($items)->sortBy('created_time')->values();
                foreach ($sortedMessages as $msg) {
                    if (isset($msg['attachments'])) {
                        Log::info('Message with attachment found', [
                            'conversation_id' => $conversationId,
                            'message' => $msg,
                        ]);
                    }
                }

                $lastMessage = $sortedMessages->last();
                $isUnread = $this->selectedConversationId !== $firstMessage['conversation_id'];

                return [
                    'sender' => $sender,
                    'last_message' => $lastMessage['message'] ?? '',
                    'last_message_time' => $lastMessage['created_time'] ?? '',
                    'messages' => $sortedMessages->all(),
                    'conversation_id' => $firstMessage['conversation_id'],
                    'avatar_url' => $avatarUrl,
                    'sender_id' => $senderId,
                    'unread' => $isUnread,
                    'page_id' => $account->page_id,
                    'page_name' => $account->name,
                ];
            })->filter()->values()->all();

            $allMessages = array_merge($allMessages, $grouped);
        }

        usort($allMessages, function ($a, $b) {
            return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
        });

        $this->messages = $allMessages;

        Log::info('Refreshed messages from all pages', [
            'total_messages_count' => count($this->messages),
        ]);
    }

    public function pollMessages()
    {
        if ($this->hasPendingUpload) {
            Log::info('Polling skipped due to pending upload files', [
                'hasPendingUpload' => $this->hasPendingUpload,
            ]);
            return;
        }

        $this->refreshMessages();
    }

    protected function markAsRead()
    {
        $this->messages = collect($this->messages)->map(function ($conversation) {
            if ($conversation['conversation_id'] === $this->selectedConversationId) {
                $conversation['unread'] = false;
            }
            return $conversation;
        })->toArray();
    }

    protected function markOtherConversationsAsUnread()
    {
        $this->messages = collect($this->messages)->map(function ($conversation) {
            if ($conversation['conversation_id'] !== $this->selectedConversationId) {
                $conversation['unread'] = true;
            }
            return $conversation;
        })->toArray();
    }
}