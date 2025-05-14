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
        $handlerStack = HandlerStack::create();

        $handlerStack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) {
                if ($retries >= 3) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                if ($response && in_array($response->getStatusCode(), [503, 429])) {
                    return true;
                }

                if ($response && $response->getStatusCode() === 400) {
                    $body = json_decode($response->getBody()->getContents(), true);
                    if (isset($body['error']['code']) && $body['error']['code'] === 4) {
                        $response->getBody()->rewind();
                        return true;
                    }
                }

                return false;
            },
            function ($retries) {
                return (int) pow(2, $retries) * 1000;
            }
        ));

        $this->client = new Client([
            'base_uri' => 'https://graph.facebook.com/v20.0/',
            'timeout' => 60.0,
            'handler' => $handlerStack,
        ]);
    }

    public function getPageMessages(string $pageId, string $pageAccessToken): array
    {
        try {
            if (empty($pageId) || empty($pageAccessToken)) {
                throw new \Exception('Page ID and access token are required.');
            }

            $response = $this->client->get("{$pageId}/conversations", [
                'query' => [
                    'fields' => 'id,participants,messages.limit(20){message,from,created_time,attachments}',
                    'access_token' => $pageAccessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $conversations = $data['data'] ?? [];

            $messages = [];

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
                                        $downloadResponse = $this->client->get($newUrl, ['sink' => $localPath]);

                                        if ($downloadResponse->getStatusCode() === 200) {
                                            $attachment['payload']['url'] = $localUrl;
                                        }
                                    } catch (\Exception $e) {
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

            return $messages;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage();

            return [];
        }
    }

    public function getPageAvatar(string $pageId, string $accessToken): ?string
    {
        try {
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
                return $data['data']['url'];
            }

            return null;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return "https://graph.facebook.com/{$pageId}/picture?type=small";
        }
    }

    public function replyToMessage(string $conversationId, string $pageAccessToken, string $message): bool
    {
        try {
            if (empty($conversationId) || empty($pageAccessToken) || empty($message)) {
                throw new \Exception('Conversation ID, access token, and message are required.');
            }

            $message = $this->normalizeMessage($message);

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

            if (isset($data['error'])) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception('Failed to reply to message: ' . $errorMessage);
            }

            return true;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            throw new \Exception('Failed to reply to message: ' . $errorMessage);
        }
    }

    public function storePageAnalytics(PlatformAccount $platformAccount, string $since, string $until)
    {
        if (empty($platformAccount->app_id) || empty($platformAccount->app_secret) || empty($platformAccount->access_token)) {
            throw new \Exception("Missing app_id, app_secret, or access_token for {$platformAccount->name}");
        }

        $metrics = [
            'page_impressions',
            'page_post_engagements',
            'page_impressions_unique',
        ];

        try {
            $allInsights = [];
            foreach ($metrics as $metric) {
                try {
                    $response = $this->client->get("{$platformAccount->page_id}/insights", [
                        'query' => [
                            'metric' => $metric,
                            'period' => 'day',
                            'since' => $since,
                            'until' => $until,
                            'access_token' => $platformAccount->access_token,
                        ],
                    ]);

                    $response->getBody()->rewind();

                    $data = json_decode($response->getBody()->getContents(), true);
                    $insights = $data['data'] ?? [];

                    $allInsights[$metric] = $insights;
                } catch (RequestException $e) {
                    $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();

                    if ($e->hasResponse()) {
                        $errorData = json_decode($errorMessage, true);
                        if (isset($errorData['error']['code']) && $errorData['error']['code'] == 200) {
                            throw new \Exception('Access token lacks required permissions to fetch insights for metric: ' . $metric);
                        }
                    }

                    $allInsights[$metric] = [];
                    continue;
                }
            }

            $postInsights = [];
            try {
                $response = $this->client->get("{$platformAccount->page_id}/posts", [
                    'query' => [
                        'fields' => 'created_time',
                        'since' => $since,
                        'until' => $until,
                        'access_token' => $platformAccount->access_token,
                    ],
                ]);

                $posts = json_decode($response->getBody()->getContents(), true)['data'] ?? [];

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

                        $postInsights[$createdDate]['link_clicks'] += $postMetrics['link_clicks'];
                        $postInsights[$createdDate]['engagements'] += $postMetrics['engagements'];
                        $postInsights[$createdDate]['impressions'] += $postMetrics['impressions'];
                    } catch (RequestException $e) {
                        continue;
                    }
                }
            } catch (RequestException $e) {
            }

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

                if (isset($allInsights['page_impressions_unique']) && !empty($allInsights['page_impressions_unique'])) {
                    foreach ($allInsights['page_impressions_unique'] as $insight) {
                        $insightDate = Carbon::parse($insight['end_time'])->subDay()->format('Y-m-d');
                        if ($insightDate === $dateStr) {
                            $data['reach'] = $insight['values'][0]['value'] ?? 0;
                            break;
                        }
                    }
                }

                if (isset($postInsights[$dateStr])) {
                    $data['link_clicks'] = $postInsights[$dateStr]['link_clicks'];
                }

                PageAnalytic::updateOrCreate(
                    [
                        'platform_account_id' => $platformAccount->id,
                        'date' => $dateStr,
                    ],
                    $data
                );

                $recordsCount++;
            }

            $this->storeFollowersCount($platformAccount, $since, $until);
        } catch (\Exception $e) {
            throw new \Exception("Unexpected error: {$e->getMessage()}");
        }
    }

    protected function storeFollowersCount(PlatformAccount $platformAccount, string $since, string $until)
    {
        try {
            $response = $this->client->get("{$platformAccount->page_id}", [
                'query' => [
                    'fields' => 'followers_count',
                    'access_token' => $platformAccount->access_token,
                ],
            ]);

            $pageData = json_decode($response->getBody()->getContents(), true);
            $followersCount = $pageData['followers_count'] ?? 0;

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
        } catch (\Exception $e) {
        }
    }

    private function normalizeMessage(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = str_replace("\u000A", "\n", $message);

        return $message;
    }

    public function postToPage(string $pageId, string $pageAccessToken, string $message, ?array $media = null): ?string
    {
        try {
            $message = $this->normalizeMessage($message);

            $params = [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ];

            if ($media && count($media) > 0) {
                $photoIds = [];

                foreach ($media as $mediaPath) {
                    if (!file_exists($mediaPath)) {
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
                                'contents' => 'false',
                            ],
                        ],
                    ];

                    $response = $this->client->post("{$pageId}/photos", $photoParams);
                    $result = json_decode($response->getBody()->getContents(), true);

                    if (isset($result['error'])) {
                        throw new \Exception('Lỗi khi tải ảnh lên Facebook: ' . $result['error']['message']);
                    }

                    $photoIds[] = $result['id'];
                }

                foreach ($photoIds as $index => $photoId) {
                    $params["attached_media[{$index}]"] = "{\"media_fbid\":\"{$photoId}\"}";
                }

                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            } else {
                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['id'] ?? null;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            throw new \Exception('Failed to post to Facebook: ' . $errorMessage);
        }
    }

    public function postVideoToPage(string $pageId, string $pageAccessToken, string $message, ?array $media = null): ?string
    {
        try {
            $message = $this->normalizeMessage($message);

            $params = [
                'description' => $message,
                'access_token' => $pageAccessToken,
            ];

            if ($media && count($media) > 0) {
                $videoPath = $media[0];

                if (!file_exists($videoPath)) {
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

                $response = $this->client->post("{$pageId}/videos", $params);
            } else {
                $response = $this->client->post("{$pageId}/feed", [
                    'form_params' => $params,
                ]);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['id'] ?? throw new \Exception('Failed to post video to Facebook: No post ID returned.');
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
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

            $videoPaths = is_string($videoPaths) ? [$videoPaths] : (array) $videoPaths;

            $videoPaths = array_map(function ($path) {
                return is_array($path) ? (string) ($path[0] ?? '') : (string) $path;
            }, $videoPaths);

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
            throw new \Exception('Failed to post video to Facebook: ' . $errorMessage);
        }
    }

    public function updatePost(string $postId, string $pageAccessToken, string $message): bool
    {
        try {
            $message = $this->normalizeMessage($message);

            $params = [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ];

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
            throw new \Exception('Failed to update post on Facebook: ' . $errorMessage);
        }
    }

    public function updatePostWithMedia(string $postId, string $pageId, string $pageAccessToken, string $message, ?array $media = null, string $mediaType = 'image'): ?string
    {
        try {
            $this->deletePost($postId, $pageAccessToken);

            $newPostId = $mediaType === 'video'
                ? $this->postVideoToPage($pageId, $pageAccessToken, $message, $media)
                : $this->postToPage($pageId, $pageAccessToken, $message, $media);

            if (!$newPostId) {
                throw new \Exception('Failed to repost updated content to Facebook.');
            }

            return $newPostId;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            throw new \Exception('Failed to update post with media on Facebook: ' . $errorMessage);
        }
    }

    public function deletePost(string $postId, string $pageAccessToken): bool
    {
        try {
            $params = [
                'access_token' => $pageAccessToken,
            ];

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
            throw new \Exception('Failed to delete post from Facebook: ' . $errorMessage);
        }
    }

    public function getReadWatermark(string $conversationId, string $accessToken): ?int
    {
        $response = Http::get("https://graph.facebook.com/v20.0/{$conversationId}?fields=read_watermark&access_token={$accessToken}");

        if ($response->successful()) {
            return $response->json('read_watermark');
        }

        return null;
    }
}