<?php

namespace App\Http\Controllers;

use App\Models\Adicional;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class AdicionalController extends Controller
{
    /**
     * Obtener todos los adicionales
     */
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

    /**
     * Crear un adicional
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'menu_id' => 'required|exists:menu,id',
                'empresa_id' => 'required|exists:business_registrations,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            $fotoUrl = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                $fotoUrl = Storage::url($fotoPath);
            }

            $adicional = Adicional::create([
                'menu_id' => $request->menu_id,
                'empresa_id' => $request->empresa_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? '',
                'foto' => $fotoUrl,
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

            return response()->json($adicional, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un adicional
     */
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

    /**
     * Actualizar un adicional
     */
    public function update(Request $request, $id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            $request->validate([
                'menu_id' => 'required|exists:menu,id',
                'empresa_id' => 'required|exists:business_registrations,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            if ($request->hasFile('foto')) {
                if ($adicional->foto) {
                    Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
                }
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                $adicional->foto = Storage::url($fotoPath);
            }

            $adicional->update([
                'menu_id' => $request->menu_id,
                'empresa_id' => $request->empresa_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? '',
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

            return response()->json($adicional);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un adicional
     */
    public function destroy($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);

            if ($adicional->foto) {
                Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
            }

            $adicional->delete();
            return response()->json(['message' => 'Adicional eliminado con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener adicionales por empresa (con información del menú)
     */
    public function obtenerAdicionales($empresa_id)
    {
        try {
            $adicionales = Adicional::with('menu:id,titulo')
                ->where('empresa_id', $empresa_id)
                ->get();
            return response()->json($adicionales, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener los adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener adicionales por menú (producto)
     */
    public function obtenerAdicionalesPorMenu($menu_id)
    {
        try {
            $adicionales = Adicional::where('menu_id', $menu_id)
                ->where('status', 'active')
                ->get();
            return response()->json($adicionales, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener los adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear adicional (versión web)
     */
    public function crearAdicional(Request $request)
    {
        try {
            $request->validate([
                'menu_id' => 'required|exists:menu,id',
                'empresa_id' => 'required|exists:business_registrations,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            $fotoUrl = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                $fotoUrl = Storage::url($fotoPath);
            }

            $adicional = Adicional::create([
                'menu_id' => $request->menu_id,
                'empresa_id' => $request->empresa_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? '',
                'foto' => $fotoUrl,
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

            return response()->json(['success' => true, 'data' => $adicional], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un adicional (versión web)
     */
    public function obtenerAdicional($id)
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

    /**
     * Actualizar adicional (versión web)
     */
    public function actualizarAdicional(Request $request, $id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            $request->validate([
                'menu_id' => 'required|exists:menu,id',
                'empresa_id' => 'required|exists:business_registrations,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            if ($request->hasFile('foto')) {
                if ($adicional->foto) {
                    Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
                }
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                $adicional->foto = Storage::url($fotoPath);
            }

            $adicional->update([
                'menu_id' => $request->menu_id,
                'empresa_id' => $request->empresa_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? '',
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

            return response()->json(['success' => true, 'data' => $adicional]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar adicional (versión web)
     */
    public function eliminarAdicional($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);

            if ($adicional->foto) {
                Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
            }

            $adicional->delete();
            return response()->json(['success' => true, 'message' => 'Adicional eliminado con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el adicional', 'error' => $e->getMessage()], 500);
        }
    }
}
