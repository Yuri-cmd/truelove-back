<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use App\Models\NotificationLog;

class FirebaseService
{
    private $config;

    public function __construct()
    {
        $this->config = [
            'type' => 'service_account',
            'project_id' => 'notifacacion',
            'private_key_id' => 'ad4067141d0b83c8a42957039af93a126c4f171a',
            'private_key' => str_replace("\\n", "\n", "***GOOGLE_PRIVATE_KEY_REMOVED***"),
            'client_email' => 'firebase-adminsdk-fbsvc@notifacacion.iam.gserviceaccount.com',
            'client_id' => '103081417099398986366',
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

    private function createLog($token, $title, $body, $data, $appName = null, $userId = null, $userType = null)
    {
        try {
            return NotificationLog::create([
                'fcm_token' => $token,
                'app_name' => $appName,
                'user_id' => $userId,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error creando log de notificación: " . $e->getMessage());
            return null;
        }
    }

    public function sendNotification($token, $title, $body, $data = [], $appName = null, $userId = null, $userType = null)
    {
        $log = $this->createLog($token, $title, $body, $data, $appName, $userId, $userType);
        
        if ($log) {
            $data['notification_id'] = (string)$log->id;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        // Mensaje data-only: Android lo entrega de inmediato con priority=high
        // El campo "notification" queda sujeto al Doze Mode del SO (causa retrasos)
        $dataPayload = array_map('strval', array_merge($data, [
            'title'  => $title,
            'body'   => $body,
            'sound'  => 'default',
            'channel_id' => 'general_channel',
        ]));

        $payload = [
            "message" => [
                "token" => $token,
                "data"  => $dataPayload,
                "android" => [
                    "priority" => "high",
                ],
                "apns" => [
                    "headers" => [
                        "apns-priority" => "10"
                    ],
                    "payload" => [
                        "aps" => [
                            "content-available" => 1,
                            "sound" => "default"
                        ]
                    ]
                ]
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

    public function sendNotificationWithSound($token, $title, $body, $soundFile = 'nuevo_pedido', $channelId = 'pedidos_v3', $data = [], $appName = null, $userId = null, $userType = null)
    {
        $log = $this->createLog($token, $title, $body, $data, $appName, $userId, $userType);
        
        if ($log) {
            $data['notification_id'] = (string)$log->id;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        // Mensaje data-only: Android lo entrega de inmediato con priority=high
        // El campo "notification" queda sujeto al Doze Mode del SO (causa retrasos)
        $dataPayload = array_map('strval', array_merge($data, [
            'title'        => $title,
            'body'         => $body,
            'sound'        => $soundFile,
            'channel_id'   => $channelId,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'tipo'         => 'nuevo_pedido',
        ]));

        $payload = [
            "message" => [
                "token" => $token,
                "data"  => $dataPayload,
                "android" => [
                    "priority" => "high",
                ],
                "apns" => [
                    "headers" => [
                        "apns-priority" => "10"
                    ],
                    "payload" => [
                        "aps" => [
                            "content-available" => 1,
                            "sound" => $soundFile . ".wav"
                        ]
                    ]
                ]
            ]
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config['project_id'] . '/messages:send';

        try {
            $response = Http::withHeaders($headers)->post($url, $payload);
            Log::info("Respuesta de Firebase con sonido personalizado: " . $response->body());

            return $response->json();
        } catch (\Exception $e) {
            Log::error("🔥 Error enviando notificación con sonido: " . $e->getMessage());
            return null;
        }
    }
}
