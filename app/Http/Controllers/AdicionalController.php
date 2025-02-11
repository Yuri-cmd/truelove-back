<?php

namespace App\Http\Controllers;

use App\Models\Adicional;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdicionalController extends Controller
{
    public function index()
    {
        try {
            $adicionales = Adicional::all();
            if ($adicionales->isEmpty()) {
                return response()->json(['message' => 'No se encontraron adicionales'], 404);
            }
            return response()->json($adicionales);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener los adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'categoria_adicional_id' => 'required|exists:categoria_adicional,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'required|string|max:255',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            $adicional = Adicional::create($request->all());
            return response()->json($adicional, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            return response()->json($adicional);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'categoria_adicional_id' => 'required|exists:categoria_adicional,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'required|string|max:255',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            $adicional->update($request->all());
            return response()->json($adicional);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            $adicional->delete();
            return response()->json(['message' => 'Adicional eliminado con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el adicional', 'error' => $e->getMessage()], 500);
        }
    }
}