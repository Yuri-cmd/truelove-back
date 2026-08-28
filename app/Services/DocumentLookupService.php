<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta DNI/RUC contra dniruc.apisperu.com (API principal) y, si falla
 * o no encuentra resultados, cae automáticamente a api.apiperu.dev (respaldo).
 * Centraliza esta lógica para que ningún frontend (web ni apps) necesite
 * llamar a los proveedores externos directamente.
 */
class DocumentLookupService
{
    private const DNI_PRIMARIO_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6Inl1cmltMTZAaG90bWFpbC5jb20ifQ.AEnuNMXrrkul5ZPLj7L0WM-lUqfvGkAXDAAlrHYFQqs';

    /**
     * Devuelve ['numeroDocumento','nombres','apellidoPaterno','apellidoMaterno','status'] o null.
     */
    public function buscarDni(string $documento): ?array
    {
        $data = $this->consultarDniPrimario($documento);

        if (!$this->dniEncontrado($data)) {
            Log::warning('API DNI primaria sin resultado, usando fallback apiperu.dev', ['documento' => $documento]);
            $data = $this->consultarDniFallback($documento);
        }

        return $this->dniEncontrado($data) ? $data : null;
    }

    /**
     * Devuelve ['ruc','razonSocial','estado','condicion'] o null.
     */
    public function buscarRuc(string $ruc): ?array
    {
        $data = $this->consultarRucPrimario($ruc);

        if (!$this->rucEncontrado($data)) {
            Log::warning('API RUC primaria sin resultado, usando fallback apiperu.dev', ['ruc' => $ruc]);
            $data = $this->consultarRucFallback($ruc);
        }

        return $this->rucEncontrado($data) ? $data : null;
    }

    private function dniEncontrado($data)
    {
        return is_array($data) && !empty($data['nombres']);
    }

    private function rucEncontrado($data)
    {
        return is_array($data) && !empty($data['ruc']) && !empty($data['razonSocial']);
    }

    private function consultarDniPrimario(string $documento): ?array
    {
        $url = "https://dniruc.apisperu.com/api/v1/dni/{$documento}?token=" . self::DNI_PRIMARIO_TOKEN;

        try {
            $response = @file_get_contents($url);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            if (!is_array($data) || !($data['success'] ?? false)) {
                return null;
            }

            return [
                'numeroDocumento' => $data['dni'] ?? $documento,
                'nombres' => $data['nombres'] ?? '',
                'apellidoPaterno' => $data['apellidoPaterno'] ?? '',
                'apellidoMaterno' => $data['apellidoMaterno'] ?? '',
                'status' => 200,
            ];
        } catch (Exception $e) {
            Log::warning('Excepción en API DNI primaria: ' . $e->getMessage());
            return null;
        }
    }

    private function consultarDniFallback(string $documento): ?array
    {
        try {
            $response = Http::withToken(config('services.apiperu.token'))
                ->acceptJson()
                ->timeout(10)
                ->post('https://api.apiperu.dev/dni', ['dni' => $documento]);

            if (!$response->successful()) {
                Log::warning('Fallo API DNI fallback (apiperu.dev): status ' . $response->status());
                return null;
            }

            $json = $response->json();
            if (!($json['success'] ?? false) || empty($json['data'])) {
                return null;
            }

            $datosPersona = $json['data'];

            return [
                'numeroDocumento' => $datosPersona['numero'] ?? $documento,
                'nombres' => $datosPersona['nombres'] ?? '',
                'apellidoPaterno' => $datosPersona['apellido_paterno'] ?? '',
                'apellidoMaterno' => $datosPersona['apellido_materno'] ?? '',
                'status' => 200,
            ];
        } catch (Exception $e) {
            Log::warning('Excepción en API DNI fallback: ' . $e->getMessage());
            return null;
        }
    }

    private function consultarRucPrimario(string $ruc): ?array
    {
        $url = "https://dniruc.apisperu.com/api/v1/ruc/{$ruc}?token=" . self::DNI_PRIMARIO_TOKEN;

        try {
            $response = @file_get_contents($url);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            if (!is_array($data) || empty($data['ruc'])) {
                return null;
            }

            return [
                'ruc' => $data['ruc'],
                'razonSocial' => $data['razonSocial'] ?? '',
                'estado' => $data['estado'] ?? null,
                'condicion' => $data['condicion'] ?? null,
            ];
        } catch (Exception $e) {
            Log::warning('Excepción en API RUC primaria: ' . $e->getMessage());
            return null;
        }
    }

    private function consultarRucFallback(string $ruc): ?array
    {
        try {
            $response = Http::withToken(config('services.apiperu.token'))
                ->acceptJson()
                ->timeout(10)
                ->post('https://api.apiperu.dev/ruc', ['ruc' => $ruc]);

            if (!$response->successful()) {
                Log::warning('Fallo API RUC fallback (apiperu.dev): status ' . $response->status());
                return null;
            }

            $json = $response->json();
            if (!($json['success'] ?? false) || empty($json['data'])) {
                return null;
            }

            $datosEmpresa = $json['data'];

            return [
                'ruc' => $datosEmpresa['ruc'] ?? $ruc,
                'razonSocial' => $datosEmpresa['nombre_o_razon_social'] ?? '',
                'estado' => $datosEmpresa['estado'] ?? null,
                'condicion' => $datosEmpresa['condicion'] ?? null,
            ];
        } catch (Exception $e) {
            Log::warning('Excepción en API RUC fallback: ' . $e->getMessage());
            return null;
        }
    }
}
