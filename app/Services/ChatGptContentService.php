<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ChatGptContentService
{
    /**
     * Sinh nội dung bài viết bằng ChatGPT.
     *
     * @param mixed $post
     * @param string $topic
     * @param string $tone
     * @param string $language
     * @param array $config
     * @return array
     * @throws \Exception
     */
    public static function generatePostContent($post, string $topic, string $tone, string $language, array $config): array
    {
        try {
            // 1️⃣ Lấy thông tin từ config
            $platform = $config['platform'] ?? 'facebook'; // Nhận platform từ config
            $maxLength = $config['max_length'] ?? 1000;
            $maxHashtags = $config['max_hashtags'] ?? 5;
            $existingHashtags = $config['existing_hashtags'] ?? [];

            // 2️⃣ Tạo prompt để yêu cầu GPT sinh nội dung với ngắt dòng
            $hashtagsInstruction = !empty($existingHashtags)
                ? "Sử dụng các hashtags sau: " . implode(', ', $existingHashtags) . ". Nếu cần, bạn có thể thêm các hashtag khác phù hợp với nội dung, nhưng không vượt quá $maxHashtags hashtag."
                : "Tự động tạo ít nhất 2 hashtag và tối đa $maxHashtags hashtag phù hợp với nội dung bài viết. Đảm bảo mỗi hashtag bắt đầu bằng ký tự #.";

            $prompt = "Bạn là một chuyên gia viết bài quảng cáo trên mạng xã hội. Hãy tạo một bài viết cho nền tảng $platform với các yêu cầu sau:\n" .
                "- Chủ đề: $topic\n" .
                "- Phong cách: $tone\n" .
                "- Ngôn ngữ: $language\n" .
                "- Độ dài tối đa: $maxLength ký tự\n" .
                "- Hashtags: $hashtagsInstruction\n" .
                "Trả về bài viết dưới dạng JSON với các trường: `title` (tiêu đề), `content` (nội dung bài viết), và `hashtags` (danh sách hashtag dưới dạng mảng). Đảm bảo:\n" .
                "- Nội dung bài viết (`content`) không được chứa bất kỳ thẻ HTML nào (như <p>, <br>, v.v.), chỉ sử dụng văn bản thuần túy.\n" .
                "- Nội dung bài viết (`content`) **phải** được ngắt dòng sau mỗi câu hoàn chỉnh (kết thúc bằng dấu chấm '.', dấu chấm than '!', dấu hỏi '?', hoặc dấu ba chấm '...'). Sử dụng ký tự \\n để ngắt dòng. Không để nội dung dính liền trên một dòng.\n" .
                "- Trường `hashtags` phải là một mảng các chuỗi, mỗi chuỗi bắt đầu bằng ký tự #. Nếu không có hashtag, trả về mảng rỗng [].\n" .
                "- Chỉ trả về JSON hợp lệ, không thêm bất kỳ nội dung nào khác ngoài JSON. Ví dụ:\n" .
                "{\n" .
                "  \"title\": \"Tiêu đề bài viết\",\n" .
                "  \"content\": \"Câu 1: Nội dung chính của bài viết. \\nCâu 2: Chi tiết thú vị khác! \\nCâu 3: Kêu gọi hành động. 😍\",\n" .
                "  \"hashtags\": [\"#hashtag1\", \"#hashtag2\"]\n" .
                "}";

            // 3️⃣ Gọi API OpenAI để sinh nội dung
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Bạn là một trợ lý AI chuyên viết content trên mạng xã hội.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

            $result = $response->choices[0]->message->content;

            // 4️⃣ Parse kết quả JSON từ GPT
            $generated = json_decode($result, true);

            // Kiểm tra lỗi JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Lỗi khi parse JSON từ GPT', [
                    'post_id' => $post ? ($post->id ?? 'new_instance') : 'new_instance',
                    'json_error' => json_last_error_msg(),
                    'raw_result' => $result,
                ]);
                throw new \Exception('Nội dung trả về từ GPT không phải JSON hợp lệ: ' . json_last_error_msg());
            }

            // Kiểm tra các trường bắt buộc trong JSON
            if (!$generated || !isset($generated['title']) || !isset($generated['content']) || !isset($generated['hashtags'])) {
                Log::error('JSON từ GPT không chứa các trường mong muốn', [
                    'post_id' => $post ? ($post->id ?? 'new_instance') : 'new_instance',
                    'parsed_result' => $generated,
                ]);
                throw new \Exception('Nội dung trả về từ GPT không đúng định dạng JSON mong muốn. Thiếu các trường title, content, hoặc hashtags.');
            }

            // 5️⃣ Loại bỏ thẻ HTML từ title và content
            $title = strip_tags($generated['title']); // Loại bỏ thẻ HTML từ tiêu đề
            $content = strip_tags($generated['content']); // Loại bỏ thẻ HTML từ nội dung

            // 6️⃣ Chuẩn hóa nội dung: đảm bảo \n sau mỗi câu
            // Thay thế các ký tự xuống dòng không mong muốn
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            // Thêm \n sau các ký tự kết thúc câu (., !, ?, ...)
            $content = preg_replace('/([.!?])\s*(?![.!?\s])/', "$1\n", $content); // Thêm \n sau . ! ? (tránh lặp lại nếu đã có dấu câu hoặc \n ngay sau)
            $content = preg_replace('/(\.{3}|\…)\s*(?![.!?\s])/', "$1\n", $content); // Thêm \n sau dấu ba chấm

            // Chuẩn hóa nội dung: loại bỏ các ký tự xuống dòng thừa
            $content = preg_replace('/\n{3,}/', "\n", $content); // Thay thế nhiều \n liên tiếp bằng 1 \n
            $lines = explode("\n", $content);
            $lines = array_map('trim', $lines); // Loại bỏ khoảng trắng thừa ở đầu và cuối mỗi dòng
            $lines = array_filter($lines, fn($line) => $line !== ''); // Loại bỏ các dòng trống
            $content = implode("\n", $lines); // Ghép lại với 1 \n giữa các dòng

            // 7️⃣ Đảm bảo hashtags là một mảng và bắt đầu bằng #
            $hashtags = $generated['hashtags'];
            if (!is_array($hashtags)) {
                Log::warning('Hashtags từ GPT không phải mảng, chuyển đổi thành mảng', [
                    'post_id' => $post ? ($post->id ?? 'new_instance') : 'new_instance',
                    'hashtags' => $hashtags,
                ]);
                $hashtags = [$hashtags];
            }

            // Đảm bảo mỗi hashtag bắt đầu bằng #
            $hashtags = array_map(function ($tag) {
                return strpos($tag, '#') === 0 ? $tag : '#' . $tag;
            }, $hashtags);

            // Giới hạn số lượng hashtag
            if (count($hashtags) > $maxHashtags) {
                $hashtags = array_slice($hashtags, 0, $maxHashtags);
            }

            // Nếu không có hashtag, thêm hashtag mặc định
            if (empty($hashtags) && empty($existingHashtags)) {
                // Tạo hashtag mặc định dựa trên chủ đề và nền tảng
                $topicWords = explode(' ', strtolower($topic));
                $defaultHashtags = [];
                foreach ($topicWords as $word) {
                    if (strlen($word) > 3) { // Chỉ lấy các từ dài hơn 3 ký tự
                        $defaultHashtags[] = '#' . preg_replace('/[^a-z0-9]/', '', $word);
                    }
                }
                $defaultHashtags[] = "#{$platform}";
                $hashtags = array_slice($defaultHashtags, 0, $maxHashtags);
            }

            return [
                'title' => $title,
                'content' => $content,
                'hashtags' => $hashtags,
            ];
        } catch (\Exception $e) {
            Log::error("Lỗi khi tạo nội dung bằng GPT", [
                'post_id' => $post ? ($post->id ?? 'new_instance') : 'new_instance',
                'topic' => $topic,
                'tone' => $tone,
                'language' => $language,
                'platform' => $platform ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}