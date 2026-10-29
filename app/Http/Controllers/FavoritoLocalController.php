<?php

namespace App\Http\Controllers;

use App\Models\FavoritoLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FavoritoLocalController extends Controller
{
    /**
     * Marca/desmarca un restaurante como favorito para un cliente.
     * Body: { cliente_id, local_id } (local_id = business_registration_id)
     */
    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'cliente_id' => 'required|integer|exists:clientes,id',
                'local_id' => 'required|integer|exists:business_registrations,id',
            ]);

            $favorito = FavoritoLocal::where('cliente_id', $request->cliente_id)
                ->where('business_registration_id', $request->local_id)
                ->first();

            if ($favorito) {
                $favorito->delete();
                return response()->json(['success' => true, 'is_favorito' => false]);
            }

            FavoritoLocal::create([
                'cliente_id' => $request->cliente_id,
                'business_registration_id' => $request->local_id,
            ]);

            return response()->json(['success' => true, 'is_favorito' => true]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en FavoritoLocalController@toggle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el favorito',
            ], 500);
        }
    }

    /** Si el cliente ya marcó este restaurante como favorito (para pintar el corazón). */
    public function estado($idCliente, $idLocal)
    {
        $esFavorito = FavoritoLocal::where('cliente_id', $idCliente)
            ->where('business_registration_id', $idLocal)
            ->exists();

        return response()->json(['success' => true, 'is_favorito' => $esFavorito]);
    }
}
