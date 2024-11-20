<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\Establecimiento;
use App\Models\DatosClaveNegocio;
use App\Models\DatosBancarios;
use Illuminate\Http\Request;

class IdsController extends Controller
{
    public function obtenerUltimosIds()
    {
        try {
            $ultimoNegocio = Negocio::latest()->first();
            $ultimoEstablecimiento = Establecimiento::latest()->first();
            $ultimosDatosClave = DatosClaveNegocio::latest()->first();
            $ultimosDatosBancarios = DatosBancarios::latest()->first();

            return response()->json([
                'negocioId' => $ultimoNegocio->id,
                'establecimientoId' => $ultimoEstablecimiento->id,
                'datosClaveId' => $ultimosDatosClave->id,
                'datosBancariosId' => $ultimosDatosBancarios->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener los últimos IDs',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}