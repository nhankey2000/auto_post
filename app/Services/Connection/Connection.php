<?php

namespace App\Services\Connection;

use App\Models\PlatformAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Connection
{
    public function check(PlatformAccount $account)
    {
        if (!$account->access_token || !$account->app_id || !$account->app_secret) {
            return false;
        }

        if (strtolower($account->platform->name) !== 'facebook') {
            return false;
        }

        try {
            $client = new Client();
            $response = $client->get('https://graph.facebook.com/debug_token', [
                'query' => [
                    'input_token' => $account->access_token,
                    'access_token' => $account->app_id . '|' . $account->app_secret,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                
                if (isset($data['data']['is_valid']) && $data['data']['is_valid']) {
                    $expiresAt = null;
                    if (isset($data['data']['expires_at']) && $data['data']['expires_at'] > 0) {
                        // Chỉ tạo DateTime nếu expires_at lớn hơn 0
                        $expiresAt = new \DateTime();
                        $expiresAt->setTimestamp($data['data']['expires_at']);
                    }
                    
                    return [
                        'success' => true,
                        'expires_at' => $expiresAt
                    ];
                }
            }

            return false;
        } catch (RequestException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}