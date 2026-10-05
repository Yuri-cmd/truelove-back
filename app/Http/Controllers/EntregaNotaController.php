<?php

namespace App\Http\Controllers;

use App\Models\EntregaNota;
use App\Models\Pedido;
use App\Models\RepartoRegistro;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Notas y fotos de los motorizados sobre un lugar de entrega. Requiere el token
 * del repartidor (Sanctum): el repartidor se identifica por el token, no por un
 * id enviado en la petición. Solo ve y agrega notas quien tiene asignado el pedido.
 */
class EntregaNotaController extends Controller
{
    /** Radio (km) dentro del cual una nota se considera del mismo lugar. */
    private const RADIO_KM = 0.06;

    public function __construct(private PedidoService $pedidoService)
    {
    }

    /** Repartidor dueño del token; null si el usuario no es un repartidor. */
    private function repartidorActual(Request $request): ?RepartoRegistro
    {
        $user = $request->user();
        return $user ? RepartoRegistro::where('user_id', $user->id)->first() : null;
    }

    /** Pedido, solo si está asignado a este repartidor. */
    private function pedidoDelRepartidor($idPedido, RepartoRegistro $reparto): ?Pedido
    {
        $pedido = Pedido::find($idPedido);
        if (!$pedido || !$pedido->id_motorizado || (int) $pedido->id_motorizado !== (int) $reparto->id) {
            return null;
        }
        return $pedido;
    }

    public function index(Request $request, $idPedido)
    {
        $reparto = $this->repartidorActual($request);
        $pedido = $reparto ? $this->pedidoDelRepartidor($idPedido, $reparto) : null;
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no asignado a este repartidor'], 403);
        }

        $lat = (float) $pedido->latitud;
        $lng = (float) $pedido->longitud;
        // Caja aproximada (~66 m por 0.0006°) para filtrar en SQL; luego se afina por distancia real.
        $delta = 0.0006;

        $notas = EntregaNota::visiblesParaRepartidores()
            ->with('motorizado:id,nombres,apellidos,foto_perfil')
            ->whereBetween('latitud', [$lat - $delta, $lat + $delta])
            ->whereBetween('longitud', [$lng - $delta, $lng + $delta])
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(fn ($n) => $this->pedidoService->calcularDistanciaHaversine($lat, $lng, $n->latitud, $n->longitud) <= self::RADIO_KM)
            ->values();

        $data = $notas->map(fn ($n) => [
            'id' => $n->id,
            'nota' => $n->nota,
            'foto_url' => $n->foto_url,
            'autor' => trim(($n->motorizado->nombres ?? '') . ' ' . substr($n->motorizado->apellidos ?? '', 0, 1) . '.') ?: 'Repartidor',
            'autor_foto' => $n->motorizado->foto_perfil_url ?? null,
            'es_mia' => (int) $n->motorizado_id === (int) $reparto->id,
            'fecha' => $n->created_at?->toIso8601String(),
            'hace' => Carbon::parse($n->created_at)->locale('es')->diffForHumans(),
        ])->all();

        return response()->json(['total' => count($data), 'notas' => $data]);
    }

    public function store(Request $request, $idPedido)
    {
        $request->validate([
            'nota' => 'nullable|string|max:500',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $nota = trim((string) $request->nota);
        if ($nota === '' && !$request->hasFile('foto')) {
            return response()->json(['error' => 'Escribe una nota o agrega una foto'], 422);
        }

        $reparto = $this->repartidorActual($request);
        $pedido = $reparto ? $this->pedidoDelRepartidor($idPedido, $reparto) : null;
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no asignado a este repartidor'], 403);
        }

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('entrega_notas', 'custom_public')
            : null;

        $registro = EntregaNota::create([
            'motorizado_id' => $reparto->id,
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->id_cliente,
            'latitud' => $pedido->latitud,
            'longitud' => $pedido->longitud,
            'nota' => $nota !== '' ? $nota : null,
            'foto_path' => $fotoPath,
            'estado' => EntregaNota::PENDIENTE,
        ]);

        return response()->json(['success' => true, 'id' => $registro->id, 'foto_url' => $registro->foto_url], 201);
    }

    public function destroy(Request $request, $id)
    {
        $reparto = $this->repartidorActual($request);
        $nota = EntregaNota::find($id);
        if (!$nota) {
            return response()->json(['error' => 'Nota no encontrada'], 404);
        }
        if (!$reparto || (int) $nota->motorizado_id !== (int) $reparto->id) {
            return response()->json(['error' => 'Solo puedes borrar tus propias notas'], 403);
        }

        if ($nota->foto_path) {
            Storage::disk('custom_public')->delete($nota->foto_path);
        }
        $nota->delete();

        return response()->json(['success' => true]);
    }
}
