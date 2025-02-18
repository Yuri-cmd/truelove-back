<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;

class FirebaseService
{
    private $config;

    public function __construct()
    {
        $this->config = [
            'type' => 'service_account',
            'project_id' => env('BIKER_FIREBASE_PROJECT_ID'),
            'private_key_id' => env('BIKER_FIREBASE_GOOGLE_PRIVATE_KEY_ID'),
            'private_key' => str_replace("\\n", "\n", env('BIKER_FIREBASE_GOOGLE_PRIVATE_KEY')),
            'client_email' => env('BIKER_FIREBASE_GOOGLE_CLIENT_EMAIL'),
            'client_id' => env('BIKER_FIREBASE_GOOGLE_CLIENT_ID'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40notifacacion.iam.gserviceaccount.com',
            'universe_domain' => 'googleapis.com'
        ];
    }

    public function getAccessToken()
    {
        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->config);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $accessToken = $client->fetchAccessTokenWithAssertion();

            if (isset($accessToken["error"])) {
                Log::error("Error obteniendo access token: " . json_encode($accessToken));
                return null;
            }

            Log::info("Access Token obtenido: " . json_encode($accessToken));
            return $accessToken["access_token"] ?? null;
        } catch (\Exception $e) {
            Log::error("Excepción obteniendo Access Token: " . $e->getMessage());
            return null;
        }
    }

    public function sendNotification($token, $title, $body, $data = [])
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        $payload = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
            ]
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config['project_id'] . '/messages:send';

        try {
            $response = Http::withHeaders($headers)->post($url, $payload);
            Log::info("Respuesta de Firebase: " . $response->body());

            return $response->json();
        } catch (\Exception $e) {
            Log::error("🔥 Error enviando notificación: " . $e->getMessage());
            return null;
        }
    }
}
