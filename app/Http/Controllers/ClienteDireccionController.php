<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Services\CoordenadasService;
use Illuminate\Http\Request;

/**
 * Múltiples direcciones por cliente (app de clientes nueva). La dirección "activa" es la que
 * usan los pedidos y el resto del sistema (listado de locales, precio de delivery…), así que
 * las apps antiguas, que solo conocen una dirección, siguen funcionando con la activa.
 */
class ClienteDireccionController extends Controller
{
    private const MAXIMO_POR_CLIENTE = 10;

    public function __construct(private CoordenadasService $coordenadas)
    {
    }

    public function index($idCliente)
    {
        $direcciones = ClienteDireccion::where('id_cliente', $idCliente)
            ->orderByDesc('activa')
            ->orderBy('id')
            ->get();

        return response()->json($direcciones->map(fn ($d) => $this->formatear($d))->values());
    }

    public function store(Request $request, $idCliente)
    {
        if (!Cliente::whereKey($idCliente)->exists()) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $datos = $request->validate([
            'direccion' => 'required|string|max:255',
            'selectedPosition' => 'required',
            'alias' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:255',
            'departamento' => 'nullable|string|max:255',
            'activar' => 'nullable|boolean',
        ]);

        $existentes = ClienteDireccion::where('id_cliente', $idCliente)->count();
        if ($existentes >= self::MAXIMO_POR_CLIENTE) {
            return response()->json(['message' => 'Llegaste al máximo de ' . self::MAXIMO_POR_CLIENTE . ' direcciones. Elimina una para agregar otra.'], 422);
        }

        $direccion = ClienteDireccion::create([
            'id_cliente' => $idCliente,
            'direccion' => $datos['direccion'],
            'departamento' => $datos['departamento'] ?? '',
            'referencia' => $datos['referencia'] ?? null,
            'alias' => $datos['alias'] ?? null,
            'coordenadas' => $this->coordenadas->geoJson($request->selectedPosition),
            'activa' => false,
        ]);

        // La primera dirección es siempre la activa; las demás, si el cliente lo pide (por defecto sí)
        if ($existentes === 0 || $request->boolean('activar', true)) {
            $direccion->activar();
        }

        return response()->json($this->formatear($direccion->fresh()), 201);
    }

    public function update(Request $request, $idCliente, $id)
    {
        $direccion = $this->propia($idCliente, $id);

        $request->validate([
            'direccion' => 'sometimes|required|string|max:255',
            'alias' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:255',
            'departamento' => 'nullable|string|max:255',
        ]);

        if ($request->filled('direccion') && $request->filled('selectedPosition')) {
            // Si el lugar cambia de verdad sube la versión: las notas de la casa de antes dejan de aplicar
            $direccion->actualizarUbicacion([
                'direccion' => $request->direccion,
                'coordenadas' => $this->coordenadas->geoJson($request->selectedPosition),
                'departamento' => $request->departamento,
                'referencia' => $request->referencia,
                'alias' => $request->alias,
            ]);
        } else {
            // Solo etiqueta/referencia: el lugar es el mismo
            $direccion->update(array_filter([
                'alias' => $request->alias,
                'referencia' => $request->referencia,
                'departamento' => $request->departamento,
            ], fn ($v) => $v !== null));
        }

        return response()->json($this->formatear($direccion->fresh()));
    }

    public function activar($idCliente, $id)
    {
        $direccion = $this->propia($idCliente, $id);
        $direccion->activar();

        return response()->json($this->formatear($direccion->fresh()));
    }

    public function destroy($idCliente, $id)
    {
        $direccion = $this->propia($idCliente, $id);

        if (ClienteDireccion::where('id_cliente', $idCliente)->count() <= 1) {
            return response()->json(['message' => 'Debes tener al menos una dirección.'], 422);
        }

        $eraActiva = $direccion->activa;
        $direccion->delete();

        // Si se borró la activa, pasa a serlo la más reciente que quede
        if ($eraActiva) {
            ClienteDireccion::where('id_cliente', $idCliente)->orderByDesc('id')->first()?->activar();
        }

        return response()->json(['success' => true]);
    }

    private function propia($idCliente, $id): ClienteDireccion
    {
        return ClienteDireccion::where('id', $id)->where('id_cliente', $idCliente)->firstOrFail();
    }

    private function formatear(ClienteDireccion $d): array
    {
        $c = $this->coordenadas->desdeDireccion($d);

        return [
            'id' => $d->id,
            'alias' => $d->alias,
            'direccion' => $d->direccion,
            'referencia' => $d->referencia,
            'departamento' => $d->departamento,
            'latitud' => $c['lat'] ?? null,
            'longitud' => $c['lng'] ?? null,
            'activa' => (bool) $d->activa,
        ];
    }
}
