<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use App\Models\AiPostPrompt;
use App\Models\PlatformAccount;
use App\Models\ImageLibrary;
use App\Models\RepeatScheduled;
use App\Services\FacebookService;
use Carbon\Carbon;
use GuzzleHttp\Client;

class ProcessScheduledPrompts extends Command
{
    protected $signature = 'prompts:process';
    protected $description = 'Process scheduled prompts and post content';

    public function handle()
    {
        $this->info('🔎 Đang kiểm tra các bài viết đã được lên lịch...');

        // Lấy thời gian hiện tại
        $now = Carbon::now();

        // Log thời gian hiện tại để debug
        $this->info("⏰ Thời gian hiện tại: {$now->toDateTimeString()}");

        // Lấy tất cả các prompt có scheduled_at hoặc có lịch trong repeat_scheduled
        $prompts = AiPostPrompt::whereNotNull('scheduled_at')
            ->orWhereHas('repeatSchedules')
            ->get();

        // Log dữ liệu đầy đủ để kiểm tra
        $this->info('📋 Dữ liệu bảng ai_post_prompts:');
        if ($prompts->isEmpty()) {
            $this->info("⚠️ Không có bản ghi nào trong bảng ai_post_prompts có scheduled_at hoặc repeat_scheduled.");
        } else {
            foreach ($prompts as $prompt) {
                $repeatSchedules = $prompt->repeatSchedules->pluck('schedule')->toArray();
                $this->info("Prompt ID: {$prompt->id}, Status: {$prompt->status}, Scheduled At: " . ($prompt->scheduled_at ? $prompt->scheduled_at->toDateTimeString() : 'null') . ", Repeat Schedules: " . json_encode($repeatSchedules));
            }
        }

        // Đếm các bài viết chưa đến giờ đăng
        $pendingPrompts = $prompts->filter(function ($prompt) use ($now) {
            $scheduledAtCondition = false;
            if ($prompt->scheduled_at) {
                $existingSchedule = RepeatScheduled::where('ai_post_prompts_id', $prompt->id)
                    ->where('schedule', $prompt->scheduled_at)
                    ->first();
                $scheduledAtCondition = !$existingSchedule || !$existingSchedule->facebook_post_id && $now->lessThan($prompt->scheduled_at);
            }
            $repeatSchedulesCondition = false;
            if ($prompt->repeatSchedules->isNotEmpty()) {
                foreach ($prompt->repeatSchedules as $schedule) {
                    if ($schedule->schedule && !$schedule->facebook_post_id && $now->lessThan($schedule->schedule)) {
                        $repeatSchedulesCondition = true;
                        break;
                    }
                }
            }
            return $scheduledAtCondition || $repeatSchedulesCondition;
        });

        $pendingCount = $pendingPrompts->count();
        $pendingIds = $pendingPrompts->pluck('id')->toArray();
        $this->info("📅 Có $pendingCount bài viết chưa đến giờ đăng. ID: " . ($pendingCount > 0 ? implode(', ', $pendingIds) : 'Không có'));

        foreach ($prompts as $prompt) {
            $shouldProcess = false;
            $isScheduledAt = false;
            $isRepeatSchedule = false;
            $repeatSchedule = null;

            // Tìm thời gian đăng bài tiếp theo
            $nextScheduleTime = null;
            if ($prompt->scheduled_at) {
                $existingSchedule = RepeatScheduled::where('ai_post_prompts_id', $prompt->id)
                    ->where('schedule', $prompt->scheduled_at)
                    ->first();
                if (!$existingSchedule || !$existingSchedule->facebook_post_id) {
                    if (!$nextScheduleTime || $prompt->scheduled_at->lessThan($nextScheduleTime)) {
                        $nextScheduleTime = $prompt->scheduled_at;
                    }
                }
            }
            if ($prompt->repeatSchedules->isNotEmpty()) {
                foreach ($prompt->repeatSchedules as $schedule) {
                    if ($schedule->schedule && !$schedule->facebook_post_id && (!$nextScheduleTime || $schedule->schedule->lessThan($nextScheduleTime))) {
                        $nextScheduleTime = $schedule->schedule;
                    }
                }
            }

            // Ưu tiên kiểm tra scheduled_at (bao gồm đã qua thời gian)
            if ($prompt->scheduled_at) {
                if ($now->greaterThanOrEqualTo($prompt->scheduled_at)) {
                    $existingSchedule = RepeatScheduled::where('ai_post_prompts_id', $prompt->id)
                        ->where('schedule', $prompt->scheduled_at)
                        ->first();

                    if ($existingSchedule && $existingSchedule->facebook_post_id) {
                        $this->info("⏩ Bài viết ID: {$prompt->id} đã được đăng cho scheduled_at (Facebook Post ID: {$existingSchedule->facebook_post_id}). Bỏ qua.");
                    } else {
                        $shouldProcess = true;
                        $isScheduledAt = true;
                        $this->info("📅 Đã đến hoặc qua thời gian đăng bài lần đầu của bài viết ID: {$prompt->id}.");
                    }
                } else {
                    $this->info("⏳ Chưa đến thời gian đăng bài lần đầu của bài viết ID: {$prompt->id}. Thời gian: {$prompt->scheduled_at->toDateTimeString()}");
                }
            }

            // Nếu không có scheduled_at hoặc đã xử lý, kiểm tra repeat_scheduled (bao gồm đã qua thời gian)
            if (!$isScheduledAt && $prompt->repeatSchedules->isNotEmpty()) {
                $hasFutureSchedule = false;
                foreach ($prompt->repeatSchedules as $schedule) {
                    if ($schedule->schedule && $now->greaterThanOrEqualTo($schedule->schedule)) {
                        if ($schedule->facebook_post_id) {
                            $this->info("⏩ Bài viết ID: {$prompt->id} đã được đăng cho lịch chạy lại (Facebook Post ID: {$schedule->facebook_post_id}). Bỏ qua.");
                            continue;
                        }
                        $shouldProcess = true;
                        $isRepeatSchedule = true;
                        $repeatSchedule = $schedule;
                        $this->info("📅 Đã đến hoặc qua thời gian đăng lại của bài viết ID: {$prompt->id}.");
                        break;
                    } elseif ($schedule->schedule && !$schedule->facebook_post_id && $now->lessThan($schedule->schedule)) {
                        $hasFutureSchedule = true;
                    }
                }
                if (!$shouldProcess && $hasFutureSchedule) {
                    $this->info("⏳ Chưa đến thời gian đăng lại của bài viết ID: {$prompt->id}. Thời gian tiếp theo: {$nextScheduleTime->toDateTimeString()}");
                    continue;
                }
            }

            // Nếu đến thời gian hoặc đã qua thời gian, và chưa có facebook_post_id, xử lý prompt
            if ($shouldProcess) {
                $this->info("✏️ Đang xử lý bài viết ID: {$prompt->id}");

                // Cập nhật trạng thái thành "generating" nếu không phải 'posted'
                if ($prompt->status !== 'posted') {
                    $prompt->update(['status' => 'generating']);
                }

                try {
                    // Kiểm tra sự tồn tại của prompt và image
                    $result = null;
                    if (!empty($prompt->prompt)) {
                        // Nếu prompt tồn tại, sử dụng logic hiện tại
                        $this->info("📝 Xử lý bài viết với prompt: {$prompt->prompt}");
                        $result = $this->generateContentWithChatGPT($prompt->prompt);
                    } elseif (!empty($prompt->image)) {
                        // Nếu image tồn tại, gọi ChatGPT Mini để phân tích hình ảnh
                        $this->info("🖼️ Xử lý bài viết với hình ảnh: {$prompt->image}");
                        $result = $this->generateContentFromImageWithChatGPTMini($prompt->image);
                    } else {
                        // Nếu cả prompt và image đều null, bỏ qua
                        throw new \Exception('Cả prompt và image đều trống. Không thể tạo nội dung bài đăng.');
                    }

                    // Lưu nội dung vào generated_content, title, và hashtags
                    $prompt->update([
                        'generated_content' => $result['content'],
                        'title' => $result['title'],
                        'hashtags' => $result['hashtags'],
                        'status' => 'generated',
                    ]);

                    // Chuẩn bị dữ liệu để đăng bài
                    $title = $result['title'];
                    $content = $result['content'];
                    $hashtags = $result['hashtags'];

                    // Xử lý media từ image_library (hình ảnh và video)
                    $imagePaths = [];
                    $imageNames = [];
                    $videoPaths = [];
                    $videoNames = [];
                    if ($prompt->image_count && $prompt->image_category) {
                        Log::info('🔍 Đang tìm media với category_id:', [
                            'category_id' => $prompt->image_category,
                            'image_count' => $prompt->image_count
                        ]);

                        $records = ImageLibrary::where('category_id', $prompt->image_category)
                            ->inRandomOrder()
                            ->take($prompt->image_count)
                            ->get();

                        if ($records->isNotEmpty()) {
                            $mediaItems = $records->filter(function ($record) {
                                return !empty($record->item);
                            })->toArray();

                            Log::info('📥 Media từ DB (item):', array_column($mediaItems, 'item'));

                            // Phân loại hình ảnh và video
                            foreach ($mediaItems as $media) {
                                $type = $media['type'] ?? 'image'; // Mặc định là image nếu không có type
                                $filename = $media['item'];
                                $directory = $type === 'video' ? 'videos' : 'images';
                                $relativePath = str_starts_with($filename, "$directory/")
                                    ? $filename
                                    : "$directory/$filename";
                                $absolutePath = storage_path("app/public/$relativePath");

                                $mediaInfo = [
                                    'name' => basename($absolutePath),
                                    'full_path' => $absolutePath,
                                    'exists' => file_exists($absolutePath),
                                ];

                                if ($type === 'video') {
                                    $videoPaths[] = $mediaInfo['full_path'];
                                    $videoNames[] = $mediaInfo['name'];
                                } else {
                                    $imagePaths[] = $mediaInfo['full_path'];
                                    $imageNames[] = $mediaInfo['name'];
                                }
                            }

                            Log::info('🧪 Kiểm tra từng media:', [
                                'images' => $imagePaths,
                                'videos' => $videoPaths,
                            ]);

                            // Lọc các file tồn tại
                            $imagePaths = array_filter($imagePaths, 'file_exists');
                            $imageNames = array_filter($imageNames, function ($name, $index) use ($imagePaths) {
                                return file_exists($imagePaths[$index]);
                            }, ARRAY_FILTER_USE_BOTH);
                            $imagePaths = array_values($imagePaths);
                            $imageNames = array_values($imageNames);

                            $videoPaths = array_filter($videoPaths, 'file_exists');
                            $videoNames = array_filter($videoNames, function ($name, $index) use ($videoPaths) {
                                return file_exists($videoPaths[$index]);
                            }, ARRAY_FILTER_USE_BOTH);
                            $videoPaths = array_values($videoPaths);
                            $videoNames = array_values($videoNames);

                            Log::info("✅ Hình ảnh tồn tại được chọn (đường dẫn đầy đủ):", $imagePaths);
                            Log::info("✅ Tên file hình ảnh được chọn:", $imageNames);
                            Log::info("✅ Video tồn tại được chọn (đường dẫn đầy đủ):", $videoPaths);
                            Log::info("✅ Tên file video được chọn:", $videoNames);
                            $this->info("🖼️ Đã chọn " . count($imagePaths) . " hình ảnh và " . count($videoPaths) . " video từ thư viện.");
                        } else {
                            $this->warn("⚠️ Không tìm thấy bản ghi image_library phù hợp với category_id = {$prompt->image_category}");
                        }
                    } else {
                        $this->warn("⚠️ Không có image_count hoặc image_category để lấy media từ image_library.");
                    }

                    // Ghép nội dung hoàn chỉnh để đăng
                    $finalContent = '';
                    if (!empty($title)) {
                        $boldTitle = $this->toBoldText($title);
                        $finalContent .= $boldTitle . "\n";
                    }
                    if (!empty($content)) {
                        $finalContent .= $content . "\n";
                    }
                    $contactInfo = "🌿MỌI THÔNG TIN CHI TIẾT LIÊN HỆ 🌿\n" .
                                   "🎯Địa chỉ: Tổ 26, ấp Mỹ Ái, xã Mỹ Khánh, huyện Phong Điền, TP Cần Thơ.\n" .
                                   "🎯Địa chỉ google map: https://goo.gl/maps/padvdnsZeBHM6UC97\n" .
                                   "☎️Hotline: 0901 095 709 |  0931 852 113\n" .
                                   "🔰Zalo hỗ trợ: 078 2 918 222\n" .
                                   "📧Mail: dulichongde@gmail.com\n" .
                                   "🌐Website: www.ongde.vn\n";
                    $finalContent .= $contactInfo;

                    $fixedHashtags = "#ongde #dulichongde #khudulichongde #langdulichsinhthaiongde #homestay #phimtruong #mientay #VietNam #Thailand #Asian #thienvientruclam #chonoicairang #khachsancantho #dulichcantho #langdulichongde";
                    $finalContent .= $fixedHashtags;

                    if (!empty($hashtags) && is_array($hashtags)) {
                        $hashtagsString = implode(' ', $hashtags);
                        $finalContent .= " " . $hashtagsString;
                    }

                    // Đăng nội dung lên nền tảng và nhận danh sách bài đăng
                    $postResults = $this->postToPlatform($prompt, $now, $isScheduledAt, $repeatSchedule, $imagePaths, $videoPaths);

                    // Kiểm tra nếu không đăng được bài
                    if (empty($postResults)) {
                        $this->warn("⚠️ Không đăng được bài lên bất kỳ trang nào cho prompt ID: {$prompt->id}.");
                        $prompt->update(['status' => 'pending']);
                        continue;
                    }

                    // Cập nhật trạng thái và platform_account_id trong ai_post_prompts
                    $lastPostResult = end($postResults);
                    $prompt->update([
                        'status' => 'posted',
                        'posted_at' => $now,
                        'platform_account_id' => $lastPostResult['platform_account_id'] ?? null,
                    ]);

                    // Log việc cập nhật platform_account_id
                    if ($lastPostResult['platform_account_id']) {
                        $this->info("📝 Đã lưu platform_account_id: {$lastPostResult['platform_account_id']} vào ai_post_prompts cho prompt ID: {$prompt->id}");
                    } else {
                        $this->warn("⚠️ Không có platform_account_id để lưu vào ai_post_prompts cho prompt ID: {$prompt->id}");
                    }

                    // Nếu là scheduled_at, tạo hoặc cập nhật bản ghi trong repeat_scheduled cho mỗi trang
                    if ($isScheduledAt) {
                        foreach ($postResults as $postResult) {
                            $facebookPostId = $postResult['facebook_post_id'];
                            $platformAccountId = $postResult['platform_account_id'];

                            $existingSchedule = RepeatScheduled::where('ai_post_prompts_id', $prompt->id)
                                ->where('schedule', $prompt->scheduled_at)
                                ->where('platform_account_id', $platformAccountId)
                                ->first();

                            if ($existingSchedule) {
                                $existingSchedule->update([
                                    'facebook_post_id' => $facebookPostId,
                                    'platform_account_id' => $platformAccountId,
                                    'reposted_at' => $now,
                                    'title' => $title,
                                    'content' => $finalContent,
                                    'images' => $imageNames,
                                    'videos' => $videoNames,
                                ]);
                                $this->info("📝 Đã cập nhật thông tin bài đăng scheduled_at vào repeat_scheduled cho platform_account_id: {$platformAccountId}");
                            } else {
                                RepeatScheduled::create([
                                    'ai_post_prompts_id' => $prompt->id,
                                    'facebook_post_id' => $facebookPostId,
                                    'platform_account_id' => $platformAccountId,
                                    'reposted_at' => $now,
                                    'schedule' => $prompt->scheduled_at,
                                    'title' => $title,
                                    'content' => $finalContent,
                                    'images' => $imageNames,
                                    'videos' => $videoNames,
                                ]);
                                $this->info("📝 Đã tạo bản ghi mới cho scheduled_at trong repeat_scheduled cho platform_account_id: {$platformAccountId}");
                            }
                        }
                    }

                    // Nếu là repeat_scheduled, cập nhật facebook_post_id, platform_account_id và các thông tin bài đăng
                    if ($isRepeatSchedule && $repeatSchedule) {
                        foreach ($postResults as $postResult) {
                            $facebookPostId = $postResult['facebook_post_id'];
                            $platformAccountId = $postResult['platform_account_id'];

                            $repeatSchedule->update([
                                'facebook_post_id' => $facebookPostId,
                                'platform_account_id' => $platformAccountId,
                                'reposted_at' => $now,
                                'title' => $title,
                                'content' => $finalContent,
                                'images' => $imageNames,
                                'videos' => $videoNames,
                            ]);
                            $this->info("📝 Đã cập nhật thông tin bài đăng vào repeat_scheduled cho platform_account_id: {$platformAccountId}");
                        }
                    }

                    // Hiển thị thời gian đăng bài tiếp theo (nếu còn)
                    $nextScheduleTime = null;
                    if ($prompt->scheduled_at) {
                        $existingSchedule = RepeatScheduled::where('ai_post_prompts_id', $prompt->id)
                            ->where('schedule', $prompt->scheduled_at)
                            ->first();
                        if (!$existingSchedule || !$existingSchedule->facebook_post_id) {
                            if (!$nextScheduleTime || $prompt->scheduled_at->lessThan($nextScheduleTime)) {
                                $nextScheduleTime = $prompt->scheduled_at;
                            }
                        }
                    }
                    if ($prompt->repeatSchedules->isNotEmpty()) {
                        foreach ($prompt->repeatSchedules as $schedule) {
                            if ($schedule->schedule && !$schedule->facebook_post_id && (!$nextScheduleTime || $schedule->schedule->lessThan($nextScheduleTime))) {
                                $nextScheduleTime = $schedule->schedule;
                            }
                        }
                    }
                    if ($nextScheduleTime) {
                        $this->info("⏰ Thời gian đăng bài tiếp theo của ID: {$prompt->id} là {$nextScheduleTime->toDateTimeString()}");
                    } else {
                        $this->info("ℹ️ Không còn lịch đăng bài nào chưa được đăng cho ID: {$prompt->id}");
                    }

                    $this->info("✅ Bài viết ID: {$prompt->id} đã được xử lý và đăng thành công.");
                } catch (\Exception $e) {
                    $this->error("❌ Lỗi khi xử lý bài viết ID: {$prompt->id} - {$e->getMessage()}");
                    $prompt->update(['status' => 'pending']);
                }
            }
        }

        $this->info('✅ Đã kiểm tra và xử lý xong tất cả các bài viết đã lên lịch.');
    }

    /**
     * Gửi prompt đến ChatGPT và nhận nội dung trả về
     */
    protected function generateContentWithChatGPT($prompt)
    {
        $client = new Client();
        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            throw new \Exception('❌ API key OpenAI chưa được cấu hình trong file .env');
        }

        try {
            $this->info('🤖 Đang gửi prompt tới ChatGPT (vai trò: Chuyên gia viết nội dung quảng cáo Làng Du lịch Sinh thái Ông Đề)...');

            // Thiết lập các tham số mặc định
            $platform = 'facebook';
            $maxLength = 1000;
            $maxHashtags = 5;
            $existingHashtags = [];
            $topic = $prompt;
            $tone = 'thân thiện, thu hút,hoa mỹ,truyền cảm';
            $language = 'Tiếng Việt';

            // Xây dựng hướng dẫn cho hashtags
            $hashtagsInstruction = !empty($existingHashtags)
                ? "Sử dụng các hashtags sau: " . implode(', ', $existingHashtags) . ". Nếu cần, bạn có thể thêm các hashtag khác phù hợp với nội dung, nhưng không vượt quá $maxHashtags hashtag."
                : "Tự động tạo ít nhất 2 hashtag và tối đa $maxHashtags hashtag phù hợp với nội dung bài viết. Đảm bảo mỗi hashtag bắt đầu bằng ký tự # và liên quan đến Làng Du lịch Sinh thái Ông Đề.";

            // Tạo prompt chi tiết cho ChatGPT với yêu cầu thêm emoji và liên quan đến Làng Du lịch Sinh thái Ông Đề
            $chatGptPrompt = "Bạn là một chuyên gia viết bài quảng cáo trên mạng xã hội cho Làng Du lịch Sinh thái Ông Đề, một điểm đến nổi tiếng tại Cần Thơ với các dịch vụ homestay, phim trường, trải nghiệm văn hóa miền Tây, và thiên nhiên xanh mát. Hãy tạo một bài viết cho nền tảng $platform với các yêu cầu sau:\n" .
                             "- Chủ đề: $topic. Nội dung bài viết phải liên quan trực tiếp đến Làng Du lịch Sinh thái Ông Đề, quảng bá các dịch vụ, trải nghiệm, hoặc sự kiện tại đây (ví dụ: homestay, phim trường, ẩm thực miền Tây, văn hóa địa phương, cảnh quan thiên nhiên).\n" .
                             "- Phong cách: $tone\n" .
                             "- Ngôn ngữ: $language\n" .
                             "- Độ dài tối đa: $maxLength ký tự\n" .
                             "- Hashtags: $hashtagsInstruction\n" .
                             "- Thêm một biểu tượng cảm xúc (emoji) phù hợp ở đầu mỗi câu trong nội dung bài viết (`content`). Emoji phải liên quan đến nội dung hoặc cảm xúc của câu (ví dụ: 🌿 cho thiên nhiên, 😊 cho thân thiện, 🎉 cho kêu gọi hành động, 🏡 cho homestay, 📸 cho phim trường).\n" .
                             "Trả về bài viết dưới dạng JSON với các trường: `title` (tiêu đề), `content` (nội dung bài viết), và `hashtags` (danh sách hashtag dưới dạng mảng). Đảm bảo:\n" .
                             "- Nội dung bài viết (`content`) không được chứa bất kỳ thẻ HTML nào (như <p>, <br>, v.v.), chỉ sử dụng văn bản thuần túy.\n" .
                             "- Nội dung bài viết (`content`) **phải** được ngắt dòng sau mỗi câu hoàn chỉnh (kết thúc bằng dấu chấm '.', dấu chấm than '!', dấu hỏi '?', hoặc dấu ba chấm '...'). Sử dụng ký tự \\n để ngắt dòng. Không để nội dung dính liền trên một dòng.\n" .
                             "- Mỗi câu trong `content` bắt đầu bằng một emoji, theo sau là một khoảng trắng, rồi mới đến nội dung câu.\n" .
                             "- Trường `hashtags` phải là một mảng các chuỗi, mỗi chuỗi bắt đầu bằng ký tự # và liên quan đến Làng Du lịch Sinh thái Ông Đề. Nếu không có hashtag, trả về mảng rỗng [].\n" .
                             "- Chỉ trả về JSON hợp lệ, không thêm bất kỳ nội dung nào khác ngoài JSON. Ví dụ:\n" .
                             "{\n" .
                             "  \"title\": \"Khám phá Làng Du lịch Sinh thái Ông Đề\",\n" .
                             "  \"content\": \"🌿 Chào mừng bạn đến với Làng Du lịch Sinh thái Ông Đề! \\n😊 Trải nghiệm homestay đậm chất miền Tây. \\n🎉 Đặt chỗ ngay hôm nay!\",\n" .
                             "  \"hashtags\": [\"#LangDuLichOngDe\", \"#MienTay\"]\n" .
                             "}";

            // Gọi API OpenAI
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Bạn là một trợ lý AI chuyên viết content trên mạng xã hội cho Làng Du lịch Sinh thái Ông Đề. Chỉ trả về JSON hợp lệ, không thêm bất kỳ văn bản nào khác.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $chatGptPrompt
                        ],
                    ],
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            $content = $result['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                throw new \Exception('❌ Không nhận được nội dung trả về từ ChatGPT.');
            }

            // Ghi log nội dung thô để debug
            Log::info('Nội dung thô trả về từ ChatGPT', [
                'prompt' => $prompt,
                'raw_content' => $content,
            ]);

            // Làm sạch nội dung
            $content = trim($content);
            $content = preg_replace('/^\s+|\s+$/m', '', $content);
            $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);

            // Parse JSON từ nội dung trả về
            $parsedContent = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('❌ Nội dung trả về từ ChatGPT không phải là JSON hợp lệ: ' . $content . ' (Lỗi: ' . json_last_error_msg() . ')');
            }

            // Kiểm tra các trường bắt buộc
            if (!isset($parsedContent['title'], $parsedContent['content'], $parsedContent['hashtags'])) {
                throw new \Exception('❌ JSON trả về từ ChatGPT thiếu các trường bắt buộc (title, content, hashtags): ' . $content);
            }

            // Loại bỏ ký tự Unicode không hợp lệ
            $parsedContent['content'] = preg_replace('/[\x{FFFD}]/u', '', $parsedContent['content']);
            $parsedContent['content'] = trim($parsedContent['content']);

            // Ghi log nội dung đã parse
            Log::info('Nội dung trả về từ ChatGPT', [
                'prompt' => $prompt,
                'topic' => $topic,
                'tone' => $tone,
                'language' => $language,
                'platform' => $platform,
                'result' => $parsedContent,
            ]);

            $this->info('✅ Đã nhận được nội dung từ ChatGPT.');

            return [
                'title' => $parsedContent['title'],
                'content' => $parsedContent['content'],
                'hashtags' => $parsedContent['hashtags'],
            ];
        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi gọi API ChatGPT: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gửi hình ảnh đến ChatGPT Mini và tạo nội dung bài đăng
     */
    protected function generateContentFromImageWithChatGPTMini($imagePath)
    {
        $client = new Client();
        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            throw new \Exception('❌ API key OpenAI chưa được cấu hình trong file .env');
        }

        try {
            $this->info('🤖 Đang gửi hình ảnh tới ChatGPT Mini để phân tích và tạo nội dung...');

            // Đọc và mã hóa hình ảnh thành base64
            $absolutePath = storage_path('app/public/' . $imagePath);
            if (!file_exists($absolutePath)) {
                throw new \Exception("Hình ảnh không tồn tại: {$absolutePath}");
            }

            $imageData = file_get_contents($absolutePath);
            $base64Image = base64_encode($imageData);
            $mimeType = mime_content_type($absolutePath);

            // Thiết lập các tham số mặc định
            $platform = 'facebook';
            $maxLength = 1000;
            $maxHashtags = 5;
            $tone = 'thân thiện, thu hút,hoa mỹ,truyền cảm';
            $language = 'Tiếng Việt';

            // Tạo prompt chi tiết cho ChatGPT Mini với yêu cầu thêm emoji
            $chatGptPrompt = "Bạn là một chuyên gia viết bài quảng cáo trên mạng xã hội cho Làng Du lịch Sinh thái Ông Đề, một điểm đến nổi tiếng tại Cần Thơ với các dịch vụ homestay, phim trường, trải nghiệm văn hóa miền Tây, và thiên nhiên xanh mát. Dựa trên hình ảnh được cung cấp, hãy:\n" .
                             "1. Phân tích nội dung của hình ảnh (mô tả các yếu tố chính như cảnh vật, đối tượng, màu sắc, cảm xúc, v.v.).\n" .
                             "2. Tạo một bài viết quảng cáo cho nền tảng $platform với các yêu cầu sau:\n" .
                             "- Nội dung bài viết phải liên quan trực tiếp đến Làng Du lịch Sinh thái Ông Đề, quảng bá các dịch vụ, trải nghiệm, hoặc sự kiện tại đây (ví dụ: homestay, phim trường, ẩm thực miền Tây, văn hóa địa phương, cảnh quan thiên nhiên). Đảm bảo hình ảnh được mô tả hoặc liên kết với các đặc điểm của Làng Du lịch Sinh thái Ông Đề.\n" .
                             "- Phong cách: $tone\n" .
                             "- Ngôn ngữ: $language\n" .
                             "- Độ dài tối đa: $maxLength ký tự\n" .
                             "- Tạo từ 2 đến $maxHashtags hashtag phù hợp với nội dung bài viết. Mỗi hashtag phải bắt đầu bằng ký tự #.\n" .
                             "- Thêm một biểu tượng cảm xúc (emoji) phù hợp ở đầu mỗi câu trong nội dung bài viết (`content`). Emoji phải liên quan đến nội dung hoặc cảm xúc của câu (ví dụ: 🌿 cho thiên nhiên, 😊 cho thân thiện, 🎉 cho kêu gọi hành động, 🏡 cho homestay, 📸 cho phim trường).\n" .
                             "Trả về bài viết dưới dạng JSON với các trường: `title` (tiêu đề), `content` (nội dung bài viết), và `hashtags` (mảng các hashtag). Đảm bảo:\n" .
                             "- Nội dung bài viết (`content`) không chứa thẻ HTML, chỉ là văn bản thuần túy.\n" .
                             "- Nội dung bài viết (`content`) **phải** được ngắt dòng sau mỗi câu hoàn chỉnh bằng \\n. Không thêm hashtag vào `content`.\n" .
                             "- Mỗi câu trong `content` bắt đầu bằng một emoji, theo sau là một khoảng trắng, rồi mới đến nội dung câu.\n" .
                             "- Trường `hashtags` là mảng các chuỗi, mỗi chuỗi bắt đầu bằng # và liên quan đến Làng Du lịch Sinh thái Ông Đề (ví dụ: [\"#LangDuLichOngDe\", \"#MienTay\"]). Không để mảng rỗng.\n" .
                             "- **Chỉ trả về JSON hợp lệ**, không thêm văn bản, ký tự xuống dòng, hoặc markdown (như ```json). Ví dụ:\n" .
                             "{\n" .
                             "  \"title\": \"Khám phá Làng Du lịch Sinh thái Ông Đề\",\n" .
                             "  \"content\": \"🌿 Cảnh sắc thiên nhiên tuyệt đẹp tại Ông Đề. \\n😊 Trải nghiệm homestay đậm chất miền Tây! \\n🎉 Đặt chỗ ngay hôm nay. 😍\",\n" .
                             "  \"hashtags\": [\"#LangDuLichOngDe\", \"#MienTay\"]\n" .
                             "}";

            // Gọi API OpenAI với model gpt-4o-mini
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Bạn là một trợ lý AI chuyên phân tích hình ảnh và viết content trên mạng xã hội cho Làng Du lịch Sinh thái Ông Đề. Chỉ trả về JSON hợp lệ với các trường title, content, hashtags. Không thêm bất kỳ văn bản, markdown, hoặc ký tự nào khác.'
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $chatGptPrompt
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64Image}"
                                    ]
                                ]
                            ]
                        ],
                    ],
                    'max_tokens' => 5000,
                    'temperature' => 0.7,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            $content = $result['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                throw new \Exception('❌ Không nhận được nội dung trả về từ ChatGPT Mini.');
            }

            // Ghi log nội dung thô để debug
            Log::info('Nội dung thô trả về từ ChatGPT Mini', [
                'image_path' => $imagePath,
                'raw_content' => $content,
            ]);

            // Làm sạch nội dung
            $content = trim($content);
            $content = preg_replace('/^\s+|\s+$/m', '', $content);
            $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);

            // Thử parse JSON
            $parsedContent = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('❌ Nội dung trả về từ ChatGPT Mini không phải là JSON hợp lệ: ' . $content . ' (Lỗi: ' . json_last_error_msg() . ')');
            }

            // Kiểm tra các trường bắt buộc
            if (!isset($parsedContent['title'], $parsedContent['content'])) {
                throw new \Exception('❌ JSON trả về từ ChatGPT Mini thiếu các trường bắt buộc (title, content): ' . $content);
            }

            // Xử lý trường hashtags nếu thiếu
            if (!isset($parsedContent['hashtags']) || !is_array($parsedContent['hashtags'])) {
                $this->warn('⚠️ Trường hashtags không có hoặc không phải mảng. Trích xuất từ content...');
                $hashtags = [];
                // Trích xuất hashtag từ content nếu có
                if (preg_match_all('/#[\w]+/u', $parsedContent['content'], $matches)) {
                    $hashtags = array_slice($matches[0], 0, $maxHashtags);
                    // Loại bỏ hashtag khỏi content
                    $parsedContent['content'] = preg_replace('/#[\w]+/u', '', $parsedContent['content']);
                    $parsedContent['content'] = trim(preg_replace('/\s+/', ' ', $parsedContent['content']));
                }
                $parsedContent['hashtags'] = $hashtags;
                if (empty($hashtags)) {
                    $this->warn('⚠️ Không tìm thấy hashtag trong content. Sử dụng hashtag mặc định.');
                    $parsedContent['hashtags'] = ['#LangDuLichOngDe', '#MienTay'];
                }
            }

            // Loại bỏ ký tự Unicode không hợp lệ
            $parsedContent['content'] = preg_replace('/[\x{FFFD}]/u', '', $parsedContent['content']);
            $parsedContent['content'] = trim($parsedContent['content']);

            // Ghi log nội dung đã parse
            Log::info('Nội dung trả về từ ChatGPT Mini sau khi xử lý', [
                'image_path' => $imagePath,
                'tone' => $tone,
                'language' => $language,
                'platform' => $platform,
                'result' => $parsedContent,
            ]);

            $this->info('✅ Đã nhận được nội dung từ ChatGPT Mini.');

            return [
                'title' => $parsedContent['title'],
                'content' => $parsedContent['content'],
                'hashtags' => $parsedContent['hashtags'],
            ];
        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi gọi API ChatGPT Mini: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Đăng nội dung lên nền tảng
     */
    protected function postToPlatform($prompt, $now, $isScheduledAt, $repeatSchedule, $imagePaths, $videoPaths)
    {
        if (!$prompt->platform_id) {
            throw new \Exception("Platform ID is missing for prompt ID: {$prompt->id}");
        }

        // Lấy các tài khoản từ platform_accounts dựa trên platform_id
        $query = PlatformAccount::where('platform_id', $prompt->platform_id);

        // Nếu post_option là "selected", chỉ lấy các tài khoản trong selected_pages
        $pageIds = [];
        if ($prompt->post_option === 'selected') {
            if (!empty($prompt->selected_pages) && is_array($prompt->selected_pages)) {
                $pageIds = $prompt->selected_pages;
                $this->info("📋 Danh sách ID trang được chọn để đăng: " . (empty($pageIds) ? 'Không có' : implode(', ', $pageIds)));
                $query->whereIn('id', $pageIds);
            } else {
                $this->error("❌ Lỗi: Không có trang nào được chọn để đăng bài (prompt ID: {$prompt->id}). Vui lòng chỉnh sửa và chọn ít nhất một trang.");
                return [];
            }
        } else {
            $this->info("📋 Đăng lên tất cả trang của nền tảng ID: {$prompt->platform_id}");
        }

        $platformAccounts = $query->get();

        if ($platformAccounts->isEmpty()) {
            throw new \Exception("No platform accounts found for platform ID: {$prompt->platform_id}");
        }

        // Hiển thị danh sách các trang sẽ đăng
        $accountIds = $platformAccounts->pluck('id')->toArray();
        $accountNames = $platformAccounts->pluck('name')->toArray();
        $this->info("📋 Sẽ đăng lên các trang có ID: " . implode(', ', $accountIds) . " (Tên: " . implode(', ', $accountNames) . ")");

        $postResults = [];
        $facebookService = app(FacebookService::class);

        foreach ($platformAccounts as $account) {
            if (!$account->access_token) {
                $this->warn("Skipping account ID: {$account->id} - No valid access token");
                continue;
            }

            if ($account->expires_at && now()->greaterThan($account->expires_at)) {
                $this->warn("Skipping account ID: {$account->id} - Access token has expired");
                continue;
            }

            $pageId = $account->page_id ?? null;
            if (!$pageId) {
                $this->warn("Skipping account ID: {$account->id} - No page ID found");
                continue;
            }

            try {
                // Tạo nội dung hoàn chỉnh để đăng
                $finalContent = '';
                if (!empty($prompt->title)) {
                    $boldTitle = $this->toBoldText($prompt->title);
                    $finalContent .= $boldTitle . "\n";
                }
                if (!empty($prompt->generated_content)) {
                    $finalContent .= $prompt->generated_content . "\n";
                }
                $contactInfo = "🌿MỌI THÔNG TIN CHI TIẾT LIÊN HỆ 🌿\n" .
                               "🎯Địa chỉ: Tổ 26, ấp Mỹ Ái, xã Mỹ Khánh, huyện Phong Điền, TP Cần Thơ.\n" .
                               "🎯Địa chỉ google map: https://goo.gl/maps/padvdnsZeBHM6UC97\n" .
                               "☎️Hotline: 0901 095 709 |  0931 852 113\n" .
                               "🔰Zalo hỗ trợ: 078 2 918 222\n" .
                               "📧Mail: dulichongde@gmail.com\n" .
                               "🌐Website: www.ongde.vn\n";
                $finalContent .= $contactInfo;

                $fixedHashtags = "#ongde #dulichongde #khudulichongde #langdulichsinhthaiongde #homestay #phimtruong #mientay #VietNam #Thailand #Asian #thienvientruclam #chonoicairang #khachsancantho #dulichcantho #langdulichongde";
                $finalContent .= $fixedHashtags;

                if (!empty($prompt->hashtags) && is_array($prompt->hashtags)) {
                    $hashtagsString = implode(' ', $prompt->hashtags);
                    $finalContent .= " " . $hashtagsString;
                }

                // Đăng bài lên Facebook và lấy facebook_post_id
                $facebookPostId = null;
                if (!empty($videoPaths)) {
                    // Nếu có video, ưu tiên đăng video và bỏ qua hình ảnh
                    $this->info("📹 Đăng bài với video lên page {$pageId} ({$account->name})");
                    $facebookPostId = $facebookService->postVideoToPage($pageId, $account->access_token, $finalContent, $videoPaths);
                } else {
                    // Nếu không có video, đăng với hình ảnh (hoặc không có media)
                    $this->info("🖼️ Đăng bài với hình ảnh lên page {$pageId} ({$account->name})");
                    $facebookPostId = $facebookService->postToPage($pageId, $account->access_token, $finalContent, $imagePaths);
                }

                // Lưu kết quả bài đăng
                $postResults[] = [
                    'facebook_post_id' => $facebookPostId,
                    'platform_account_id' => $account->id,
                ];

                $this->info("✅ Đăng bài thành công lên page {$pageId} ({$account->name}) - Post ID: {$facebookPostId}");
            } catch (\Exception $e) {
                $this->error("❌ Lỗi khi đăng bài lên page {$pageId}: " . $e->getMessage());
            }
        }

        return $postResults;
    }

    /**
     * Chuyển đổi văn bản thành dạng in đậm bằng Unicode
     */
    protected function toBoldText($text)
    {
        $boldMap = [
            'A' => '𝐀', 'B' => '𝐁', 'C' => '𝐂', 'D' => '𝐃', 'E' => '𝐄', 'F' => '𝐅', 'G' => '𝐆', 'H' => '𝐇',
            'I' => '𝐈', 'J' => '𝐉', 'K' => '𝐊', 'L' => '𝐋', 'M' => '𝐌', 'N' => '𝐍', 'O' => '𝐎', 'P' => '𝐏',
            'Q' => '𝐐', 'R' => '𝐑', 'S' => '𝐒', 'T' => '𝐓', 'U' => '𝐔', 'V' => '𝐕', 'W' => '𝐖', 'X' => '𝐗',
            'Y' => '𝐘', 'Z' => '𝐙',
            'a' => '𝐚', 'b' => '𝐛', 'c' => '𝐜', 'd' => '𝐝', 'e' => '𝐞', 'f' => '𝐟', 'g' => '𝐠', 'h' => '𝐡',
            'i' => '𝐢', 'j' => '𝐣', 'k' => '𝐤', 'l' => '𝐥', 'm' => '𝐦', 'n' => '𝐧', 'o' => '𝐨', 'p' => '𝐩',
            'q' => '𝐪', 'r' => '𝐫', 's' => '𝐬', 't' => '𝐭', 'u' => '𝐮', 'v' => '𝐯', 'w' => '𝐰', 'x' => '𝐱',
            'y' => '𝐲', 'z' => '𝐳',
            '0' => '𝟎', '1' => '𝟏', '2' => '𝟐', '3' => '𝟑', '4' => '𝟒', '5' => '𝟓', '6' => '𝟔', '7' => '𝟕',
            '8' => '𝟈', '9' => '𝟗',
            '!' => '❗', '?' => '❓', '.' => '.', ',' => ',', ' ' => ' ', ':' => ':', ';' => ';', '-' => '-',
        ];

        // Chuyển đổi từng ký tự
        $boldText = '';
        for ($i = 0; $i < mb_strlen($text, 'UTF-8'); $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $boldText .= $boldMap[$char] ?? $char;
        }

        return $boldText;
    }
}