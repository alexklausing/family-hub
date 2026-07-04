<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AuraFramesService
{
    const API_BASE_URL = 'https://api.pushd.com/v5';
    const USER_AGENT = 'Aura/4.7.790 (Android 30; Client)';
    const APP_IDENTIFIER = 'com.pushd.Aura';

    protected $email;
    protected $password;
    protected $deviceId;

    public function __init__()
    {
    }

    public function getImages()
    {
        $this->email = config('services.aura.email');
        $this->password = config('services.aura.password');
        $this->deviceId = 'family-hub-kiosk';

        if (!$this->email || !$this->password) {
            return [];
        }

        $authData = Cache::remember('aura_auth_token', 86400, function () {
            return $this->login();
        });

        if (!$authData) {
            dump("Login failed or token not found");
            return [];
        }

        $frames = $this->getFrames($authData);
        if (empty($frames)) {
            dump("No frames found", $frames);
            return [];
        }

        // Just fetch assets from the first frame for now
        $frameId = $frames[0]['id'];
        $assets = $this->getAssets($authData, $frameId);
        dump("Found " . count($assets) . " assets");

        $photos = [];
        foreach ($assets as $asset) {
            $url = $asset['landscape_16_10_url'] ?? $asset['landscape_url'] ?? $asset['portrait_url'] ?? $asset['thumbnail_url'] ?? null;
            if ($url) {
                $photos[] = [
                    'id' => $asset['id'],
                    'url' => $url,
                    'taken_at' => $asset['taken_at'] ?? null,
                ];
            }
        }

        return $photos;
    }

    protected function login()
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'en-US',
        ])->post(self::API_BASE_URL . '/login.json', [
            'user' => [
                'email' => $this->email,
                'password' => $this->password,
            ],
            'locale' => 'en-US',
            'app_identifier' => self::APP_IDENTIFIER,
            'identifier_for_vendor' => $this->deviceId,
            'client_device_id' => $this->deviceId,
        ]);

        if ($response->successful()) {
            return [
                'token' => $response->json('result.current_user.auth_token'),
                'user_id' => $response->json('result.current_user.id'),
            ];
        }

        \Log::error('Aura login failed', ['body' => $response->body()]);
        return null;
    }

    protected function getFrames($authData)
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'en-US',
            'x-token-auth' => $authData['token'],
            'x-user-id' => $authData['user_id'],
        ])->get(self::API_BASE_URL . '/frames.json');

        if ($response->successful()) {
            return $response->json('frames') ?? [];
        }

        return [];
    }

    protected function getAssets($authData, $frameId)
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'en-US',
            'x-token-auth' => $authData['token'],
            'x-user-id' => $authData['user_id'],
        ])->get(self::API_BASE_URL . "/frames/{$frameId}/assets.json", [
            'limit' => 1000
        ]);

        if ($response->successful()) {
            return $response->json('assets') ?? [];
        }

        return [];
    }
}
