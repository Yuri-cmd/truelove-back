<?php

namespace App\Console\Commands;

use Google\Client;
use Illuminate\Console\Command;

class GoogleDriveAuthorize extends Command
{
    protected $signature = 'backup:google-drive-authorize';

    protected $description = 'Genera el refresh token de Google Drive necesario para los backups';

    public function handle(): int
    {
        $clientId = $this->ask('GOOGLE_DRIVE_CLIENT_ID');
        $clientSecret = $this->ask('GOOGLE_DRIVE_CLIENT_SECRET');

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes(['https://www.googleapis.com/auth/drive']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri('http://localhost');

        $authUrl = $client->createAuthUrl();

        $this->info('Abre esta URL en tu navegador, inicia sesión con la cuenta de Google que usarás para los backups y autoriza el acceso:');
        $this->line($authUrl);
        $this->newLine();
        $this->info('Después de autorizar, el navegador te llevará a una URL que empieza con "http://localhost/?code=..." (puede mostrar "no se puede acceder al sitio", eso es normal).');
        $this->info('Copia SOLO el valor entre "code=" y "&scope" (o hasta el final si no hay "&scope") de esa URL, sin el resto.');

        $code = $this->ask('Pega aquí ese código');

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error('Error al obtener el token: '.($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        if (! isset($token['refresh_token'])) {
            $this->error('Google no devolvió un refresh_token. Revoca el acceso en https://myaccount.google.com/permissions y vuelve a intentar (asegúrate de que sea la primera autorización de esta app).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Listo. Copia estos valores a tu archivo .env:');
        $this->line('GOOGLE_DRIVE_CLIENT_ID='.$clientId);
        $this->line('GOOGLE_DRIVE_CLIENT_SECRET='.$clientSecret);
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN='.$token['refresh_token']);
        $this->line('GOOGLE_DRIVE_FOLDER_ID= (opcional, ID de la carpeta de Drive donde guardar los backups)');

        return self::SUCCESS;
    }
}
