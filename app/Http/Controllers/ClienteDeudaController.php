<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteDeuda;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Deudas de clientes. El admin las crea (a mano o al aprobar la cancelación de un
 * pedido), las edita y las cierra como pagadas o anuladas; la app del cliente solo
 * consulta su estado de cuenta.
 */
class ClienteDeudaController extends Controller
{
    public function __construct(private FirebaseService $firebaseService)
    {
    }
    // ───────────────────────── Admin ─────────────────────────

    public function index(Request $request)
    {
        $request->validate([
            'estado' => ['nullable', Rule::in(ClienteDeuda::ESTADOS)],
            'q' => 'nullable|string|max:100',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = ClienteDeuda::with([
            'cliente:id,nombre,apellido,documento,celular',
            'motorizado:id,nombres,apellidos',
        ])->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($w) use ($q, $request) {
                $w->whereHas('cliente', fn ($c) => $c->where('nombre', 'like', $q)
                    ->orWhere('apellido', 'like', $q)
                    ->orWhere('documento', 'like', $q)
                    ->orWhere('celular', 'like', $q));
                if (ctype_digit(trim($request->q))) {
                    $w->orWhere('pedido_id', (int) $request->q);
                }
            });
        }

        $page = $query->paginate((int) $request->input('per_page', 20));
        $page->getCollection()->transform(fn ($d) => $this->formatear($d));

        return response()->json([
            'conteos' => [
                'pendiente' => ClienteDeuda::where('estado', ClienteDeuda::PENDIENTE)->count(),
                'pagado' => ClienteDeuda::where('estado', ClienteDeuda::PAGADO)->count(),
                'anulado' => ClienteDeuda::where('estado', ClienteDeuda::ANULADO)->count(),
                'monto_pendiente' => round((float) ClienteDeuda::where('estado', ClienteDeuda::PENDIENTE)->sum('monto'), 2),
            ],
            'deudas' => $page,
        ]);
    }

    /** Buscar clientes para crear una deuda manual (nombre, documento o celular). */
    public function buscarClientes(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);
        $q = '%' . $request->q . '%';

        $clientes = Cliente::where(fn ($w) => $w->where('nombre', 'like', $q)
                ->orWhere('apellido', 'like', $q)
                ->orWhere('documento', 'like', $q)
                ->orWhere('celular', 'like', $q))
            ->limit(15)
            ->get(['id', 'nombre', 'apellido', 'documento', 'celular']);

        return response()->json($clientes);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'pedido_id' => 'nullable|integer|exists:pedidos,id',
            'monto' => 'required|numeric|min:0.01|max:99999',
            'motivo' => 'required|string|max:255',
            'observaciones_admin' => 'nullable|string|max:1000',
        ]);

        $deuda = ClienteDeuda::create($datos + [
            'estado' => ClienteDeuda::PENDIENTE,
            'registrado_por' => 'admin',
            'gestionada_por' => $request->user()->id,
            'gestionada_at' => now(),
        ]);

        return response()->json(['success' => true, 'deuda' => $this->formatear($deuda->load('cliente', 'motorizado'))], 201);
    }

    public function update(Request $request, $id)
    {
        $deuda = ClienteDeuda::findOrFail($id);

        $datos = $request->validate([
            'monto' => 'sometimes|numeric|min:0.01|max:99999',
            'motivo' => 'sometimes|string|max:255',
            'estado' => ['sometimes', Rule::in(ClienteDeuda::ESTADOS)],
            'observaciones_admin' => 'nullable|string|max:1000',
        ]);

        $estadoAnterior = $deuda->estado;
        $deuda->update($datos + [
            'gestionada_por' => $request->user()->id,
            'gestionada_at' => now(),
        ]);

        // El cliente queda libre: se le avisa para que sepa que ya puede pedir
        if ($estadoAnterior === ClienteDeuda::PENDIENTE && $deuda->estado !== ClienteDeuda::PENDIENTE) {
            $this->avisarDeudaCerrada($deuda);
        }

        return response()->json(['success' => true, 'deuda' => $this->formatear($deuda->fresh()->load('cliente', 'motorizado'))]);
    }

    /** Aviso push al cliente cuando se le revoca o se marca como pagada una deuda. */
    private function avisarDeudaCerrada(ClienteDeuda $deuda): void
    {
        try {
            $cliente = Cliente::find($deuda->cliente_id);
            if (!$cliente || !$cliente->token_fmc) {
                return;
            }
            $pendientes = ClienteDeuda::resumenPendiente((int) $cliente->id);
            $mensaje = $pendientes['tiene_deuda']
                ? 'Se actualizó tu deuda. Aún tienes S/ ' . number_format($pendientes['total_adeudado'], 2) . ' pendientes.'
                : '¡Listo! Tu deuda fue regularizada. Ya puedes volver a hacer pedidos.';

            $this->firebaseService->sendNotification(
                $cliente->token_fmc,
                'Estado de tu cuenta',
                $mensaje,
                ['type' => 'deuda_actualizada'],
                'cliente',
                $cliente->id,
                'cliente'
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo avisar al cliente del cierre de su deuda: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        ClienteDeuda::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    private function formatear(ClienteDeuda $d): array
    {
        return [
            'id' => $d->id,
            'cliente_id' => $d->cliente_id,
            'cliente' => $d->cliente ? trim($d->cliente->nombre . ' ' . $d->cliente->apellido) : null,
            'documento' => $d->cliente->documento ?? null,
            'telefono' => $d->cliente->celular ?? null,
            'pedido_id' => $d->pedido_id,
            'motorizado' => $d->motorizado ? trim($d->motorizado->nombres . ' ' . $d->motorizado->apellidos) : null,
            'monto' => number_format((float) $d->monto, 2, '.', ''),
            'motivo' => $d->motivo,
            'estado' => $d->estado,
            'registrado_por' => $d->registrado_por,
            'observaciones_admin' => $d->observaciones_admin,
            'foto_evidencia_url' => $d->foto_evidencia_url,
            'created_at' => $d->created_at?->toIso8601String(),
            'gestionada_at' => $d->gestionada_at?->toIso8601String(),
        ];
    }

    // ───────────────────────── App cliente ─────────────────────────

    /** Estado de cuenta del cliente: deudas pendientes y total. */
    public function estadoCuenta($idCliente)
    {
        return response()->json(ClienteDeuda::resumenPendiente((int) $idCliente));
    }
}
