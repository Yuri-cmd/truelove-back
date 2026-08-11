<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use App\Models\NotificationLog;
use App\Models\Cliente;
use App\Models\BusinessRegistration;
use App\Models\RepartoRegistro;

class FirebaseService
{
    private $config;

    public function __construct()
    {
        $this->config = [
            'type' => 'service_account',
            'project_id' => config('services.biker_firebase.project_id'),
            'private_key_id' => config('services.biker_firebase.private_key_id'),
            'private_key' => str_replace("\\n", "\n", config('services.biker_firebase.private_key')),
            'client_email' => config('services.biker_firebase.client_email'),
            'client_id' => config('services.biker_firebase.client_id'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40notifacacion.iam.gserviceaccount.com',
            'universe_domain' => 'googleapis.com'
        ];
    }

    public function getAccessToken()
    {
        // Cacheado: antes se pedía un token nuevo a Google en CADA notificación individual.
        // Al mandar una tanda a muchos clientes (ej. al crear una promoción), eso duplicaba
        // la cantidad de llamadas HTTP salientes. El token de Google dura ~1h, así que se
        // reutiliza mientras siga vigente.
        return Cache::remember('firebase_access_token', 3300, function () {
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
        });
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
            $responseJson = $response->json();
            Log::info("Respuesta de Firebase: " . json_encode($responseJson));

            // Manejo de tokens no registrados (UNREGISTERED)
            if ($response->status() === 404 && isset($responseJson['error']['details'])) {
                foreach ($responseJson['error']['details'] as $detail) {
                    if (isset($detail['errorCode']) && $detail['errorCode'] === 'UNREGISTERED') {
                        $this->handleUnregisteredToken($token, $userType, $userId);
                    }
                }
            }

            return $responseJson;
        } catch (\Exception $e) {
            Log::error("🔥 Error enviando notificación: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Igual que sendNotification(), pero para mandar una tanda grande de una sola vez
     * (ej. avisar a todos los clientes de una promoción nueva). FCM v1 no soporta un
     * array de tokens en un solo mensaje, así que en vez de una llamada HTTP secuencial
     * por cliente, se disparan en paralelo por lotes con Http::pool().
     *
     * @param array $recipients cada item: ['token','title','body','data'?,'appName'?,'userId'?,'userType'?]
     */
    public function sendNotificationsBatch(array $recipients, int $chunkSize = 20): void
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error("No se pudo obtener access token para el envío en lote");
            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config['project_id'] . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        foreach (array_chunk($recipients, $chunkSize) as $lote) {
            $logs = [];
            foreach ($lote as $item) {
                $logs[] = $this->createLog(
                    $item['token'],
                    $item['title'],
                    $item['body'],
                    $item['data'] ?? [],
                    $item['appName'] ?? null,
                    $item['userId'] ?? null,
                    $item['userType'] ?? null
                );
            }

            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($lote, $logs, $headers, $url) {
                return array_map(function ($item, $log) use ($pool, $headers, $url) {
                    $data = $item['data'] ?? [];
                    if ($log) {
                        $data['notification_id'] = (string) $log->id;
                    }

                    $dataPayload = array_map('strval', array_merge($data, [
                        'title' => $item['title'],
                        'body' => $item['body'],
                        'sound' => 'default',
                        'channel_id' => 'general_channel',
                    ]));

                    $payload = [
                        'message' => [
                            'token' => $item['token'],
                            'data' => $dataPayload,
                            'android' => ['priority' => 'high'],
                            'apns' => [
                                'headers' => ['apns-priority' => '10'],
                                'payload' => ['aps' => ['content-available' => 1, 'sound' => 'default']],
                            ],
                        ],
                    ];

                    return $pool->withHeaders($headers)->post($url, $payload);
                }, $lote, $logs);
            });

            foreach ($lote as $index => $item) {
                $response = $responses[$index] ?? null;

                if (!$response instanceof \Illuminate\Http\Client\Response) {
                    error_log("Fallo de red enviando notificación a token {$item['token']}");
                    continue;
                }

                if ($response->status() === 404) {
                    $responseJson = $response->json();
                    foreach ($responseJson['error']['details'] ?? [] as $detail) {
                        if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                            $this->handleUnregisteredToken($item['token'], $item['userType'] ?? null, $item['userId'] ?? null);
                        }
                    }
                }
            }
        }
    }

    private function handleUnregisteredToken($token, $userType = null, $userId = null)
    {
        Log::warning("⚠️ Token FCM no registrado detectado. Procediendo a limpiar el token: " . $token);

        try {
            if ($userType && $userId) {
                // Si tenemos el contexto, vamos directo al registro para ser más eficientes
                switch ($userType) {
                    case 'cliente':
                        Cliente::where('id', $userId)->where('token_fmc', $token)->update(['token_fmc' => null]);
                        break;
                    case 'socio':
                        BusinessRegistration::where('id', $userId)->where('token_fmc', $token)->update(['token_fmc' => null]);
                        break;
                    case 'socio_web':
                        BusinessRegistration::where('id', $userId)->where('token_fmc_web', $token)->update(['token_fmc_web' => null]);
                        break;
                    case 'motorizado':
                    case 'biker':
                        RepartoRegistro::where('id', $userId)->where('token_fmc', $token)->update(['token_fmc' => null]);
                        break;
                }
            } else {
                // Fallback: Si no hay contexto, buscar el token en todas las tablas posibles
                Cliente::where('token_fmc', $token)->update(['token_fmc' => null]);
                BusinessRegistration::where('token_fmc', $token)->update(['token_fmc' => null]);
                BusinessRegistration::where('token_fmc_web', $token)->update(['token_fmc_web' => null]);
                RepartoRegistro::where('token_fmc', $token)->update(['token_fmc' => null]);
            }

            Log::info("✅ Token FCM invalidado eliminado de la base de datos.");
        } catch (\Exception $e) {
            Log::error("❌ Error limpiando token FCM: " . $e->getMessage());
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
                            "sound" => $soundFile . ".wav",
                            "alert" => [
                                "title" => $title,
                                "body"  => $body
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config['project_id'] . '/messages:send';

        try {
            $response = Http::withHeaders($headers)->post($url, $payload);
            $responseJson = $response->json();
            Log::info("Respuesta de Firebase con sonido personalizado: " . json_encode($responseJson));

            // Manejo de tokens no registrados (UNREGISTERED)
            if ($response->status() === 404 && isset($responseJson['error']['details'])) {
                foreach ($responseJson['error']['details'] as $detail) {
                    if (isset($detail['errorCode']) && $detail['errorCode'] === 'UNREGISTERED') {
                        $this->handleUnregisteredToken($token, $userType, $userId);
                    }
                }
            }

            return $responseJson;
        } catch (\Exception $e) {
            Log::error("🔥 Error enviando notificación con sonido: " . $e->getMessage());
            return null;
        }
    }
}
