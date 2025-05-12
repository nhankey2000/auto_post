<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PageAccessToken;

class FacebookController extends Controller
{
    // Chuyển hướng người dùng đến trang đăng nhập Facebook
    public function redirectToFacebook()
    {
        $appId = config('services.facebook.client_id');
        $redirectUri = urlencode(config('services.facebook.redirect'));
        $permissions = 'manage_pages,pages_show_list,pages_read_engagement';
        $loginUrl = "https://www.facebook.com/v19.0/dialog/oauth?client_id={$appId}&redirect_uri={$redirectUri}&scope={$permissions}";

        return redirect($loginUrl);
    }

    // Xử lý callback từ Facebook và lưu access token
    public function handleFacebookCallback(Request $request)
    {
        $appId = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');
        $redirectUri = config('services.facebook.redirect');
        $code = $request->query('code');

        if (!$code) {
            return redirect('/admin/platform-accounts')->with('error', 'Không lấy được mã ủy quyền từ Facebook.');
        }

        // Lấy access token của người dùng
        $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if ($response->failed()) {
            return redirect('/admin/platform-accounts')->with('error', 'Không thể lấy access token: ' . $response->body());
        }

        $userAccessToken = $response->json()['access_token'];

        // Lấy danh sách trang của người dùng
        $pagesResponse = Http::get("https://graph.facebook.com/v19.0/me/accounts", [
            'access_token' => $userAccessToken,
        ]);

        if ($pagesResponse->failed()) {
            return redirect('/admin/platform-accounts')->with('error', 'Không thể lấy danh sách trang: ' . $pagesResponse->body());
        }

        $pages = $pagesResponse->json()['data'];

        // Lưu access token của từng trang vào cơ sở dữ liệu
        foreach ($pages as $page) {
            PageAccessToken::updateOrCreate(
                ['page_id' => $page['id']],
                [
                    'platform_account_id' => $page['id'], // Cần ánh xạ với bảng platform_accounts
                    'page_name' => $page['name'],
                    'access_token' => $page['access_token'],
                ]
            );
        }

        return redirect('/admin/platform-accounts')->with('success', 'Đã lấy và lưu danh sách trang thành công!');
    }
}