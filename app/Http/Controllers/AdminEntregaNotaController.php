<?php

namespace App\Http\Controllers;

use App\Models\EntregaNota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Revisión por el admin de las notas y fotos que dejan los repartidores sobre
 * los lugares de entrega. Las rechazadas dejan de verse en la app del repartidor.
 */
class AdminEntregaNotaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'estado' => 'nullable|in:pendiente,aprobada,rechazada',
            'q' => 'nullable|string|max:100',
            'con_foto' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = EntregaNota::with([
            'motorizado:id,nombres,apellidos,celular,foto_perfil',
            'pedido:id,id_cliente,direccion,referencia',
        ])->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->boolean('con_foto')) {
            $query->whereNotNull('foto_path');
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($w) use ($q) {
                $w->where('nota', 'like', $q)
                    ->orWhereHas('motorizado', fn ($m) => $m->where('nombres', 'like', $q)->orWhere('apellidos', 'like', $q))
                    ->orWhereHas('pedido', fn ($p) => $p->where('direccion', 'like', $q));
            });
        }

        $page = $query->paginate((int) $request->input('per_page', 20));

        $page->getCollection()->transform(fn ($n) => [
            'id' => $n->id,
            'nota' => $n->nota,
            'foto_url' => $n->foto_url,
            'estado' => $n->estado,
            'motivo_rechazo' => $n->motivo_rechazo,
            'revisada_at' => $n->revisada_at?->toIso8601String(),
            'latitud' => $n->latitud,
            'longitud' => $n->longitud,
            'pedido_id' => $n->pedido_id,
            'direccion' => $n->pedido->direccion ?? null,
            'referencia' => $n->pedido->referencia ?? null,
            'repartidor' => $n->motorizado ? [
                'id' => $n->motorizado->id,
                'nombre' => trim($n->motorizado->nombres . ' ' . $n->motorizado->apellidos),
                'celular' => $n->motorizado->celular,
                'foto' => $n->motorizado->foto_perfil_url,
            ] : null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'conteos' => [
                'pendiente' => EntregaNota::where('estado', EntregaNota::PENDIENTE)->count(),
                'aprobada' => EntregaNota::where('estado', EntregaNota::APROBADA)->count(),
                'rechazada' => EntregaNota::where('estado', EntregaNota::RECHAZADA)->count(),
            ],
            'notas' => $page,
        ]);
    }

    public function aprobar(Request $request, $id)
    {
        $nota = EntregaNota::findOrFail($id);
        $nota->update([
            'estado' => EntregaNota::APROBADA,
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
            'motivo_rechazo' => null,
        ]);

        return response()->json(['success' => true, 'estado' => $nota->estado]);
    }

    public function rechazar(Request $request, $id)
    {
        $request->validate(['motivo' => 'nullable|string|max:255']);

        $nota = EntregaNota::findOrFail($id);
        $nota->update([
            'estado' => EntregaNota::RECHAZADA,
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
            'motivo_rechazo' => $request->motivo,
        ]);

        return response()->json(['success' => true, 'estado' => $nota->estado]);
    }

    public function destroy($id)
    {
        $nota = EntregaNota::findOrFail($id);
        if ($nota->foto_path) {
            Storage::disk('custom_public')->delete($nota->foto_path);
        }
        $nota->delete();

        return response()->json(['success' => true]);
    }
}
