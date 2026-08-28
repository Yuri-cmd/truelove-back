<?php

namespace App\Http\Controllers;

use App\Services\DocumentLookupService;
use Illuminate\Http\Request;

/**
 * Endpoints públicos de consulta DNI/RUC usados por formularios de registro
 * (socio, motorizado, datos de negocio, etc.) ANTES de que el usuario tenga
 * sesión. Centraliza la consulta en el backend (con su fallback) en vez de
 * que cada formulario del front llame directo a la API externa.
 */
class DocumentoController extends Controller
{
    private DocumentLookupService $documentLookupService;

    public function __construct(DocumentLookupService $documentLookupService)
    {
        $this->documentLookupService = $documentLookupService;
    }

    public function dni(Request $request, string $numero)
    {
        if (!preg_match('/^\d{8}$/', $numero)) {
            return response()->json(['success' => false, 'message' => 'El DNI debe tener 8 dígitos'], 422);
        }

        $data = $this->documentLookupService->buscarDni($numero);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron datos para el DNI proporcionado',
            ], 200);
        }

        return response()->json(array_merge(['success' => true], $data));
    }

    public function ruc(Request $request, string $numero)
    {
        if (!preg_match('/^\d{11}$/', $numero)) {
            return response()->json(['ruc' => null, 'message' => 'El RUC debe tener 11 dígitos'], 422);
        }

        $data = $this->documentLookupService->buscarRuc($numero);

        if (!$data) {
            return response()->json([
                'ruc' => null,
                'message' => 'No se encontraron datos para el RUC proporcionado',
            ], 200);
        }

        return response()->json($data);
    }
}
