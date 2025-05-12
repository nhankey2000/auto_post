<?php

namespace App\Services;

use App\Models\PlatformAccount;
use App\Models\PageAnalytic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
class FacebookService
{
    protected $client;

    public function __construct()
    {
        // Tạo handler stack để thêm middleware retry
        $handlerStack = HandlerStack::create();

        // Thêm middleware retry
        $handlerStack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) {
                // Thử lại tối đa 3 lần nếu gặp lỗi kết nối hoặc timeout
                if ($retries >= 3) {
                    return false;
                }

                // Thử lại nếu gặp lỗi kết nối (timeout, DNS error, v.v.)
                if ($exception instanceof ConnectException) {
                    Log::warning('Retrying Facebook API request due to connection error', [
                        'retry' => $retries + 1,
                        'error' => $exception->getMessage(),
                    ]);
                    return true;
                }

                // Thử lại nếu nhận được mã lỗi 503 (Service Unavailable) hoặc 429 (Too Many Requests)
                if ($response && in_array($response->getStatusCode(), [503, 429])) {
                    Log::warning('Retrying Facebook API request due to server error', [
                        'retry' => $retries + 1,
                        'status' => $response->getStatusCode(),
                    ]);
                    return true;
                }

                // Thêm xử lý cho lỗi rate limit (code 4) từ Facebook API
                if ($response && $response->getStatusCode() === 400) {
                    $body = json_decode($response->getBody()->getContents(), true);
                    if (isset($body['error']['code']) && $body['error']['code'] === 4) {
                        Log::warning('Retrying Facebook API request due to rate limit (code 4)', [
                            'retry' => $retries + 1,
                            'error' => $body['error']['message'],
                        ]);
                        // Reset con trỏ body để tránh lỗi khi đọc lại
                        $response->getBody()->rewind();
                        return true;
                    }
                }

                return false;
            },
            function ($retries) {
                // Delay giữa các lần thử lại (1s, 2s, 4s)
                return (int) pow(2, $retries) * 1000;
            }
        ));

        $this->client = new Client([
            'base_uri' => 'https://graph.facebook.com/v20.0/',
            'timeout' => 60.0, // Tăng timeout lên 60 giây để hỗ trợ video uploads
            'handler' => $handlerStack,
        ]);
    }

    /**
     * Lấy URL avatar của người dùng từ Facebook API.
     *
     * @param string $userId
     * @param string $accessToken
     * @return string|null
     */
 public function getPageMessages(string $pageId, string $pageAccessToken): array
{
    try {
        if (empty($pageId) || empty($pageAccessToken)) {
            throw new \Exception('Page ID and access token are required.');
        }

        Log::info('Fetching messages from Facebook API', ['page_id' => $pageId]);

        $response = $this->client->get("{$pageId}/conversations", [
            'query' => [
                'fields' => 'id,participants,messages.limit(20){message,from,created_time,attachments}',
                'access_token' => $pageAccessToken,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $conversations = $data['data'] ?? [];

        $messages = [];

        // Đảm bảo thư mục videos tồn tại
        $videoDir = storage_path('app/public/videos');
        if (!file_exists($videoDir)) {
            mkdir($videoDir, 0777, true);
        }

        foreach ($conversations as $conversation) {
            $participants = $conversation['participants']['data'] ?? [];
            $sender = null;
            $senderId = null;

            foreach ($participants as $participant) {
                if ($participant['id'] !== $pageId) {
                    $sender = $participant['name'] ?? $participant['id'];
                    $senderId = $participant['id'];
                    break;
                }
            }

            $conversationMessages = $conversation['messages']['data'] ?? [];
            foreach ($conversationMessages as $msg) {
                $attachments = $msg['attachments'] ?? [];

                if (!empty($attachments['data'])) {
                    foreach ($attachments['data'] as &$attachment) {
                        if (
                            (isset($attachment['type']) && $attachment['type'] === 'video') ||
                            (isset($attachment['mime_type']) && strpos($attachment['mime_type'], 'video') === 0)
                        ) {
                            $url = $attachment['payload']['url'] ?? $attachment['url'] ?? $attachment['file_url'] ?? '';
                            if (!empty($url)) {
                                $parsedUrl = parse_url($url);
                                $query = $parsedUrl['query'] ?? '';
                                parse_str($query, $queryParams);
                                $queryParams['access_token'] = $pageAccessToken;
                                $newQuery = http_build_query($queryParams);
                                $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'] . '?' . $newQuery;

                                $localPath = storage_path("app/public/videos/{$msg['id']}.mp4");
                                $localUrl = asset("storage/videos/{$msg['id']}.mp4");

                                try {
                                    Log::info('Đang tải video về', [
                                        'video_url' => $newUrl,
                                        'local_path' => $localPath,
                                    ]);

                                    $downloadResponse = $this->client->get($newUrl, ['sink' => $localPath]);

                                    if ($downloadResponse->getStatusCode() === 200) {
                                        Log::info('Tải video thành công', [
                                            'message_id' => $msg['id'],
                                            'saved_to' => $localUrl,
                                        ]);

                                        $attachment['payload']['url'] = $localUrl;
                                    } else {
                                        Log::warning('Không tải được video', [
                                            'message_id' => $msg['id'],
                                            'status_code' => $downloadResponse->getStatusCode(),
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Lỗi khi tải video về server', [
                                        'message_id' => $msg['id'],
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }
                }

                $messages[] = [
                    'conversation_id' => $conversation['id'],
                    'message_id' => $msg['id'],
                    'sender' => $sender,
                    'sender_id' => $senderId,
                    'message' => $msg['message'] ?? '',
                    'from' => $msg['from']['name'] ?? $msg['from']['id'],
                    'created_time' => $msg['created_time'],
                    'participants' => $participants,
                    'attachments' => $attachments,
                ];
            }
        }

        Log::info('Đã lấy thành công tin nhắn từ Facebook', [
            'page_id' => $pageId,
            'message_count' => count($messages),
        ]);

        return $messages;
    } catch (RequestException $e) {
        $errorMessage = $e->hasResponse()
            ? $e->getResponse()->getBody()->getContents()
            : $e->getMessage();

        Log::error('Không lấy được tin nhắn từ Facebook', [
            'page_id' => $pageId,
            'error' => $errorMessage,
        ]);

        return [];
    }
}

    
    /**
     * Lấy URL avatar của trang từ Facebook API.
     */
    public function getPageAvatar(string $pageId, string $accessToken): ?string
    {
        try {
            Log::info('Fetching page avatar from Facebook API', ['page_id' => $pageId]);

            $response = $this->client->get("{$pageId}/picture", [
                'query' => [
                    'access_token' => $accessToken,
                    'redirect' => false,
                    'height' => 36,
                    'width' => 36,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['data']['url'])) {
                Log::info('Successfully fetched page avatar', [
                    'page_id' => $pageId,
                    'avatar_url' => $data['data']['url'],
                ]);
                return $data['data']['url'];
            }

            Log::warning('No avatar URL found for page', ['page_id' => $pageId]);
            return null;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Failed to fetch page avatar from Facebook API', [
                'page_id' => $pageId,
                'error' => $errorMessage,
            ]);
            return "https://graph.facebook.com/{$pageId}/picture?type=small";
        }
    }

    /**
     * Phản hồi một tin nhắn trên trang Facebook.
     *
     * @param string $conversationId
     * @param string $pageAccessToken
     * @param string $message
     * @return bool
     * @throws \Exception
     */
   public function replyToMessage(string $conversationId, string $pageAccessToken, string $message): bool
{
    try {
        if (empty($conversationId) || empty($pageAccessToken) || empty($message)) {
            throw new \Exception('Conversation ID, access token, and message are required.');
        }

        $message = $this->normalizeMessage($message);

        Log::info('Sending reply to Facebook message', [
            'conversation_id' => $conversationId,
            'message' => $message,
        ]);

        $params = [
            'recipient' => [
                'id' => $conversationId
            ],
            'message' => [
                'text' => $message
            ]
        ];

        $response = $this->client->post("me/messages", [
            'query' => ['access_token' => $pageAccessToken],
            'json' => $params,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Kiểm tra nếu có lỗi từ API
        if (isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            Log::error('Failed to reply to Facebook message', [
                'conversation_id' => $conversationId,
                'error' => $errorMessage,
            ]);
            throw new \Exception('Failed to reply to message: ' . $errorMessage);
        }

        // Log phản hồi để kiểm tra
        Log::info('Facebook API response for replyToMessage', [
            'conversation_id' => $conversationId,
            'response' => $data,
        ]);

        // Nếu không có lỗi, coi như thành công, ngay cả khi không có message_id
        Log::info('Successfully replied to Facebook message', [
            'conversation_id' => $conversationId,
            'message_id' => $data['message_id'] ?? 'Not returned',
        ]);

        return true;
    } catch (RequestException $e) {
        $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        Log::error('Failed to reply to Facebook message', [
            'conversation_id' => $conversationId,
            'error' => $errorMessage,
        ]);
        throw new \Exception('Failed to reply to message: ' . $errorMessage);
    }
}

    /**
     * Lấy và lưu trữ dữ liệu thống kê từ Facebook Insights
     */
    public function storePageAnalytics(PlatformAccount $platformAccount, string $since, string $until)
    {
        // Kiểm tra các trường bắt buộc
        if (empty($platformAccount->app_id) || empty($platformAccount->app_secret) || empty($platformAccount->access_token)) {
            throw new \Exception("Missing app_id, app_secret, or access_token for {$platformAccount->name}");
        }

        // Danh sách metrics từ API Insights (page-level)
        $metrics = [
            'page_impressions',        // Lưu vào cột impressions
            'page_post_engagements',   // Lưu vào cột engagements
            'page_impressions_unique', // Lưu vào cột reach
        ];

        try {
            // Lấy dữ liệu từ API Insights (page-level)
            $allInsights = [];
            foreach ($metrics as $metric) {
                try {
                    Log::info('Sending request to Facebook Insights API for metric', [
                        'page_id' => $platformAccount->page_id,
                        'metric' => $metric,
                        'since' => $since,
                        'until' => $until,
                        'period' => 'day',
                    ]);

                    $response = $this->client->get("{$platformAccount->page_id}/insights", [
                        'query' => [
                            'metric' => $metric,
                            'period' => 'day',
                            'since' => $since,
                            'until' => $until,
                            'access_token' => $platformAccount->access_token,
                        ],
                    ]);

                    // Reset con trỏ về đầu stream để có thể đọc lại nội dung
                    $response->getBody()->rewind();

                    $data = json_decode($response->getBody()->getContents(), true);
                    $insights = $data['data'] ?? [];

                    Log::info('Received data from Facebook Insights API for metric', [
                        'page_id' => $platformAccount->page_id,
                        'metric' => $metric,
                        'insights_count' => count($insights),
                        'insights_data' => $insights,
                    ]);

                    $allInsights[$metric] = $insights;
                } catch (RequestException $e) {
                    $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                    Log::warning('Failed to fetch insights for metric', [
                        'page_id' => $platformAccount->page_id,
                        'metric' => $metric,
                        'error' => $errorMessage,
                    ]);

                    if ($e->hasResponse()) {
                        $errorData = json_decode($errorMessage, true);
                        if (isset($errorData['error']['code']) && $errorData['error']['code'] == 200) {
                            Log::error('Access token lacks required permissions to fetch insights', [
                                'page_id' => $platformAccount->page_id,
                                'metric' => $metric,
                                'error' => $errorMessage,
                            ]);
                            throw new \Exception('Access token lacks required permissions to fetch insights for metric: ' . $metric);
                        }
                    }

                    $allInsights[$metric] = [];
                    continue;
                }
            }

            // Lấy danh sách bài viết và số liệu Insights từ bài viết
            $postInsights = [];
            try {
                Log::info('Fetching posts from Facebook API', [
                    'page_id' => $platformAccount->page_id,
                    'since' => $since,
                    'until' => $until,
                ]);

                $response = $this->client->get("{$platformAccount->page_id}/posts", [
                    'query' => [
                        'fields' => 'created_time',
                        'since' => $since,
                        'until' => $until,
                        'access_token' => $platformAccount->access_token,
                    ],
                ]);

                $posts = json_decode($response->getBody()->getContents(), true)['data'] ?? [];

                // Lấy Insights từ từng bài viết
                foreach ($posts as $post) {
                    $postId = $post['id'];
                    $createdDate = Carbon::parse($post['created_time'])->format('Y-m-d');

                    try {
                        $response = $this->client->get("{$postId}/insights", [
                            'query' => [
                                'metric' => 'post_impressions,post_engaged_users,post_clicks_by_type',
                                'access_token' => $platformAccount->access_token,
                            ],
                        ]);

                        $postData = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
                        $postMetrics = [
                            'date' => $createdDate,
                            'link_clicks' => 0,
                            'engagements' => 0,
                            'impressions' => 0,
                        ];

                        foreach ($postData as $metricData) {
                            if ($metricData['name'] === 'post_impressions') {
                                $postMetrics['impressions'] = $metricData['values'][0]['value'] ?? 0;
                            }
                            if ($metricData['name'] === 'post_engaged_users') {
                                $postMetrics['engagements'] = $metricData['values'][0]['value'] ?? 0;
                            }
                            if ($metricData['name'] === 'post_clicks_by_type') {
                                $clicksByType = $metricData['values'][0]['value'] ?? [];
                                $postMetrics['link_clicks'] = $clicksByType['link clicks'] ?? 0;
                            }
                        }

                        if (!isset($postInsights[$createdDate])) {
                            $postInsights[$createdDate] = [
                                'date' => $createdDate,
                                'link_clicks' => 0,
                                'engagements' => 0,
                                'impressions' => 0,
                            ];
                        }

                        // Cộng dồn dữ liệu từ các bài viết trong cùng ngày
                        $postInsights[$createdDate]['link_clicks'] += $postMetrics['link_clicks'];
                        $postInsights[$createdDate]['engagements'] += $postMetrics['engagements'];
                        $postInsights[$createdDate]['impressions'] += $postMetrics['impressions'];
                    } catch (RequestException $e) {
                        Log::warning('Failed to fetch insights for post', [
                            'post_id' => $postId,
                            'error' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                Log::info('Received post insights from Facebook API', [
                    'page_id' => $platformAccount->page_id,
                    'post_insights' => $postInsights,
                ]);
            } catch (RequestException $e) {
                Log::warning('Failed to fetch posts from Facebook API', [
                    'page_id' => $platformAccount->page_id,
                    'error' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage(),
                ]);
            }

            // Lưu dữ liệu vào bảng page_analytics
            $startDate = Carbon::parse($since);
            $endDate = Carbon::parse($until);
            $recordsCount = 0;

            for ($date = $startDate; $date <= $endDate; $date->addDay()) {
                $dateStr = $date->format('Y-m-d');

                $data = [
                    'platform_account_id' => $platformAccount->id,
                    'date' => $dateStr,
                    'impressions' => 0,
                    'engagements' => 0,
                    'reach' => 0,
                    'link_clicks' => 0,
                ];

                // Lấy impressions (ưu tiên từ page-level, nếu rỗng thì từ post-level)
                if (isset($allInsights['page_impressions']) && !empty($allInsights['page_impressions'])) {
                    foreach ($allInsights['page_impressions'] as $insight) {
                        $insightDate = Carbon::parse($insight['end_time'])->subDay()->format('Y-m-d');
                        if ($insightDate === $dateStr) {
                            $data['impressions'] = $insight['values'][0]['value'] ?? 0;
                            break;
                        }
                    }
                }
                if ($data['impressions'] == 0 && isset($postInsights[$dateStr])) {
                    $data['impressions'] = $postInsights[$dateStr]['impressions'];
                }

                // Lấy engagements (ưu tiên từ page-level, nếu rỗng thì từ post-level)
                if (isset($allInsights['page_post_engagements']) && !empty($allInsights['page_post_engagements'])) {
                    foreach ($allInsights['page_post_engagements'] as $insight) {
                        $insightDate = Carbon::parse($insight['end_time'])->subDay()->format('Y-m-d');
                        if ($insightDate === $dateStr) {
                            $data['engagements'] = $insight['values'][0]['value'] ?? 0;
                            break;
                        }
                    }
                }
                if ($data['engagements'] == 0 && isset($postInsights[$dateStr])) {
                    $data['engagements'] = $postInsights[$dateStr]['engagements'];
                }

                // Lấy reach (page_impressions_unique)
                if (isset($allInsights['page_impressions_unique']) && !empty($allInsights['page_impressions_unique'])) {
                    foreach ($allInsights['page_impressions_unique'] as $insight) {
                        $insightDate = Carbon::parse($insight['end_time'])->subDay()->format('Y-m-d');
                        if ($insightDate === $dateStr) {
                            $data['reach'] = $insight['values'][0]['value'] ?? 0;
                            break;
                        }
                    }
                }

                // Lấy link_clicks từ post insights
                if (isset($postInsights[$dateStr])) {
                    $data['link_clicks'] = $postInsights[$dateStr]['link_clicks'];
                }

                // Log dữ liệu trước khi lưu
                Log::info('Storing data to page_analytics', [
                    'platform_account_id' => $platformAccount->id,
                    'date' => $dateStr,
                    'data' => $data,
                ]);

                // Lưu hoặc cập nhật bản ghi
                PageAnalytic::updateOrCreate(
                    [
                        'platform_account_id' => $platformAccount->id,
                        'date' => $dateStr,
                    ],
                    $data
                );

                $recordsCount++;
            }

            // Lưu số người theo dõi (followers_count) cho tất cả các ngày trong khoảng thời gian
            $this->storeFollowersCount($platformAccount, $since, $until);

            // Log tổng số bản ghi được lưu
            Log::info('Finished storing page analytics', [
                'platform_account_id' => $platformAccount->id,
                'total_records_stored' => $recordsCount,
                'since' => $since,
                'until' => $until,
            ]);
        } catch (\Exception $e) {
            Log::error('Unexpected error while fetching page insights', [
                'page_id' => $platformAccount->page_id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Unexpected error: {$e->getMessage()}");
        }
    }

    /**
     * Lấy và lưu số người theo dõi (followers_count) cho tất cả các ngày trong khoảng thời gian
     */
    protected function storeFollowersCount(PlatformAccount $platformAccount, string $since, string $until)
    {
        try {
            Log::info('Fetching followers count from Facebook API', [
                'page_id' => $platformAccount->page_id,
            ]);

            $response = $this->client->get("{$platformAccount->page_id}", [
                'query' => [
                    'fields' => 'followers_count',
                    'access_token' => $platformAccount->access_token,
                ],
            ]);

            $pageData = json_decode($response->getBody()->getContents(), true);
            $followersCount = $pageData['followers_count'] ?? 0;

            Log::info('Received followers count from Facebook API', [
                'page_id' => $platformAccount->page_id,
                'followers_count' => $followersCount,
            ]);

            // Lưu followers_count cho tất cả các ngày trong khoảng thời gian
            $startDate = Carbon::parse($since);
            $endDate = Carbon::parse($until);

            for ($date = $startDate; $date <= $endDate; $date->addDay()) {
                $dateStr = $date->format('Y-m-d');
                PageAnalytic::updateOrCreate(
                    [
                        'platform_account_id' => $platformAccount->id,
                        'date' => $dateStr,
                    ],
                    [
                        'followers_count' => $followersCount,
                    ]
                );
            }

            Log::info('Stored followers count for all days', [
                'platform_account_id' => $platformAccount->id,
                'since' => $since,
                'until' => $until,
                'followers_count' => $followersCount,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to fetch followers count for {$platformAccount->name}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Chuẩn hóa message để đảm bảo ký tự xuống dòng \n được giữ nguyên.
     *
     * @param string $message
     * @return string
     */
    private function normalizeMessage(string $message): string
    {
        // Thay thế các ký tự xuống dòng không mong muốn (nếu có)
        $message = str_replace(["\r\n", "\r"], "\n", $message);

        // Đảm bảo không có \u000A hoặc các ký tự mã hóa khác
        $message = str_replace("\u000A", "\n", $message);

        return $message;
    }

    public function postToPage(string $pageId, string $pageAccessToken, string $message, ?array $media = null): ?string
    {
        try {
            // Chuẩn hóa message để đảm bảo \n được giữ nguyên
            $message = $this->normalizeMessage($message);

            // Ghi log để kiểm tra message trước khi gửi
            Log::info('Message before sending to Facebook API', [
                'page_id' => $pageId,
                'message' => $message,
                'newlines' => substr_count($message, "\n"),
            ]);

            $params = [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ];

            // Nếu có media (ảnh), xử lý đăng nhiều ảnh
            if ($media && count($media) > 0) {
                $photoIds = [];

                // Tải từng ảnh lên với published=false
                foreach ($media as $mediaPath) {
                    // Kiểm tra xem file có tồn tại không
                    if (!file_exists($mediaPath)) {
                        Log::error('File ảnh không tồn tại khi đăng lên Facebook', [
                            'page_id' => $pageId,
                            'media_path' => $mediaPath,
                        ]);
                        throw new \Exception('File ảnh không tồn tại: ' . $mediaPath);
                    }

                    $photoParams = [
                        'multipart' => [
                            [
                                'name' => 'source',
                                'contents' => fopen($mediaPath, 'r'),
                                'filename' => basename($mediaPath),
                            ],
                            [
                                'name' => 'access_token',
                                'contents' => $pageAccessToken,
                            ],
                            [
                                'name' => 'published',
                                'contents' => 'false', // Không đăng ngay lập tức
                            ],
                        ],
                    ];

                    $response = $this->client->post("{$pageId}/photos", $photoParams);
                    $result = json_decode($response->getBody()->getContents(), true);

                    if (isset($result['error'])) {
                        Log::error('Lỗi khi tải ảnh lên Facebook', [
                            'page_id' => $pageId,
                            'error' => $result['error'],
                            'media_path' => $mediaPath,
                        ]);
                        throw new \Exception('Lỗi khi tải ảnh lên Facebook: ' . $result['error']['message']);
                    }

                    $photoIds[] = $result['id'];
                }

                // Đính kèm các ảnh vào bài viết
                foreach ($photoIds as $index => $photoId) {
                    $params["attached_media[{$index}]"] = "{\"media_fbid\":\"{$photoId}\"}";
                }

                // Đăng bài viết với các ảnh đã đính kèm
                Log::info('Gửi yêu cầu đăng bài lên Facebook với ảnh', [
                    'page_id' => $pageId,
                    'params' => $params,
                ]);
                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            } else {
                // Nếu không có ảnh, đăng bài viết như bình thường
                Log::info('Gửi yêu cầu đăng bài lên Facebook không có ảnh', [
                    'page_id' => $pageId,
                    'params' => $params,
                ]);
                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['id'] ?? null;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error', [
                'page_id' => $pageId,
                'error' => $errorMessage,
                'params' => $params,
            ]);
            throw new \Exception('Failed to post to Facebook: ' . $errorMessage);
        }
    }

    public function postVideoToPage(string $pageId, string $pageAccessToken, string $message, ?array $media = null): ?string
    {
        try {
            // Chuẩn hóa message để đảm bảo \n được giữ nguyên
            $message = $this->normalizeMessage($message);

            // Ghi log để kiểm tra message trước khi gửi
            Log::info('Message before sending video to Facebook API', [
                'page_id' => $pageId,
                'message' => $message,
                'newlines' => substr_count($message, "\n"),
            ]);

            $params = [
                'description' => $message,
                'access_token' => $pageAccessToken,
            ];

            // Nếu có media (video), xử lý đăng video
            if ($media && count($media) > 0) {
                // Facebook chỉ cho phép đăng 1 video mỗi bài viết
                $videoPath = $media[0]; // Lấy video đầu tiên

                if (!file_exists($videoPath)) {
                    Log::error('File video không tồn tại khi đăng lên Facebook', [
                        'page_id' => $pageId,
                        'media_path' => $videoPath,
                    ]);
                    throw new \Exception('File video không tồn tại: ' . $videoPath);
                }

                $params['multipart'] = [
                    [
                        'name' => 'source',
                        'contents' => fopen($videoPath, 'r'),
                        'filename' => basename($videoPath),
                    ],
                    [
                        'name' => 'description',
                        'contents' => $message,
                    ],
                    [
                        'name' => 'access_token',
                        'contents' => $pageAccessToken,
                    ],
                ];

                Log::info('Gửi yêu cầu đăng video lên Facebook', [
                    'page_id' => $pageId,
                    'params' => array_merge($params, ['multipart' => '[File data omitted from log]']),
                ]);

                $response = $this->client->post("{$pageId}/videos", $params);
            } else {
                // Nếu không có video, đăng bài viết như bình thường (không nên xảy ra trong trường hợp này)
                Log::info('Gửi yêu cầu đăng bài lên Facebook không có video', [
                    'page_id' => $pageId,
                    'params' => $params,
                ]);
                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['id'] ?? throw new \Exception('Failed to post video to Facebook: No post ID returned.');
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error (video post)', [
                'page_id' => $pageId,
                'error' => $errorMessage,
            ]);
            throw new \Exception('Failed to post video to Facebook: ' . $errorMessage);
        }
    }

    public function postVideo(string $pageId, string $pageAccessToken, string $message, $videoPaths): array
    {
        $postIds = [];

        try {
            if (empty($pageId) || empty($pageAccessToken)) {
                throw new \Exception('Page ID and access token are required.');
            }

            // Chuẩn hóa $videoPaths thành mảng nếu là chuỗi
            $videoPaths = is_string($videoPaths) ? [$videoPaths] : (array) $videoPaths;

            // Flatten nested arrays to ensure $videoPaths contains only strings
            $videoPaths = array_map(function ($path) {
                return is_array($path) ? (string) ($path[0] ?? '') : (string) $path;
            }, $videoPaths);

            // Loại bỏ các giá trị rỗng sau khi flatten
            $videoPaths = array_filter($videoPaths);

            if (empty($videoPaths)) {
                throw new \Exception('At least one video path is required.');
            }
            if (count($videoPaths) > 2) {
                throw new \Exception('Only up to 2 videos are allowed per post.');
            }

            $message = $this->normalizeMessage($message);

            foreach ($videoPaths as $videoPath) {
                $postId = $this->postVideoToPage($pageId, $pageAccessToken, $message, [$videoPath]);
                $postIds[] = $postId;
            }

            return $postIds;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error (video post)', [
                'page_id' => $pageId,
                'error' => $errorMessage,
                'video_paths' => $videoPaths,
            ]);
            throw new \Exception('Failed to post video to Facebook: ' . $errorMessage);
        }
    }

    public function updatePost(string $postId, string $pageAccessToken, string $message): bool
    {
        try {
            // Chuẩn hóa message để đảm bảo \n được giữ nguyên
            $message = $this->normalizeMessage($message);

            // Ghi log để kiểm tra message trước khi gửi
            Log::info('Message before updating to Facebook API', [
                'post_id' => $postId,
                'message' => $message,
                'newlines' => substr_count($message, "\n"),
            ]);

            $params = [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ];

            Log::info('Gửi yêu cầu cập nhật bài viết trên Facebook', [
                'post_id' => $postId,
                'params' => $params,
            ]);
            $response = $this->client->post($postId, [
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['success']) && $data['success'] === true) {
                return true;
            }

            throw new \Exception('Failed to update post on Facebook: Response does not indicate success.');
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error (update)', [
                'post_id' => $postId,
                'error' => $errorMessage,
            ]);
            throw new \Exception('Failed to update post on Facebook: ' . $errorMessage);
        }
    }

    public function updatePostWithMedia(string $postId, string $pageId, string $pageAccessToken, string $message, ?array $media = null, string $mediaType = 'image'): ?string
    {
        try {
            // Bước 1: Xoá bài viết cũ
            $this->deletePost($postId, $pageAccessToken);

            // Ghi log chi tiết về media trước khi đăng
            Log::info('Media details before posting in updatePostWithMedia', [
                'page_id' => $pageId,
                'media_paths' => $media,
                'media_type' => $mediaType,
                'media_count' => count($media ?? []),
            ]);

            // Bước 2: Đăng lại bài viết mới với nội dung và media đã cập nhật
            $newPostId = $mediaType === 'video'
                ? $this->postVideoToPage($pageId, $pageAccessToken, $message, $media)
                : $this->postToPage($pageId, $pageAccessToken, $message, $media);

            if (!$newPostId) {
                throw new \Exception('Failed to repost updated content to Facebook.');
            }

            Log::info('Đã cập nhật bài viết trên Facebook bằng cách xoá và đăng lại', [
                'old_post_id' => $postId,
                'new_post_id' => $newPostId,
                'page_id' => $pageId,
            ]);

            return $newPostId;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error (update with media)', [
                'post_id' => $postId,
                'page_id' => $pageId,
                'error' => $errorMessage,
            ]);
            throw new \Exception('Failed to update post with media on Facebook: ' . $errorMessage);
        }
    }

    public function deletePost(string $postId, string $pageAccessToken): bool
    {
        try {
            $params = [
                'access_token' => $pageAccessToken,
            ];

            Log::info('Gửi yêu cầu xóa bài viết trên Facebook', [
                'post_id' => $postId,
                'params' => $params,
            ]);
            $response = $this->client->delete($postId, [
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['success']) && $data['success'] === true) {
                return true;
            }

            throw new \Exception('Failed to delete post from Facebook: Response does not indicate success.');
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error('Facebook Graph API error (delete)', [
                'post_id' => $postId,
                'error' => $errorMessage,
            ]);
            throw new \Exception('Failed to delete post from Facebook: ' . $errorMessage);
        }
    }

public function getReadWatermark(string $conversationId, string $accessToken): ?int
{
    $response = Http::get("https://graph.facebook.com/v20.0/{$conversationId}?fields=read_watermark&access_token={$accessToken}");

    if ($response->successful()) {
        return $response->json('read_watermark');
    }

    Log::warning('Không thể lấy read_watermark từ Facebook', [
        'conversation_id' => $conversationId,
        'response' => $response->body(),
    ]);

    return null;
}


}