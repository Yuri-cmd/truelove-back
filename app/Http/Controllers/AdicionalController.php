<?php

namespace App\Http\Controllers;

use App\Models\Adicional;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class AdicionalController extends Controller
{
    public function index()
    {
        try {
            $adicionales = Adicional::all();
            // si esta vacio devuelve not fount
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
                'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB = 5120 KB
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            // Guardar la imagen en el almacenamiento
            if ($request->hasFile('foto')) {
                // Se guarda la imagen en el disco público y se obtiene el path
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                // Obtiene la ruta relativa para almacenar en la base de datos
                $fotoUrl = Storage::url($fotoPath);
            }

            $adicional = Adicional::create([
                'empresa_id' => $request->empresa_id,
                'categoria_adicional_id' => $request->categoria_adicional_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'foto' => $fotoUrl ?? null,  // Solo guarda la URL de la imagen si fue cargada
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

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
                'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB = 5120 KB
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            // Actualizar la foto si se proporciona una nueva
            if ($request->hasFile('foto')) {
                // Eliminar la foto anterior si existe
                if ($adicional->foto) {
                    Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
                }
                
                // Se guarda la nueva imagen en el disco público y se obtiene el path
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                // Obtiene la ruta relativa para almacenar en la base de datos
                $fotoUrl = Storage::url($fotoPath);
                $adicional->foto = $fotoUrl;
            }

            $adicional->update($request->except('foto'));
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
            
            // Eliminar la foto del almacenamiento si existe
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
    public function obtenerAdicionales($empresa_id)
    {
        try {
            $adicionales = Adicional::where('empresa_id', $empresa_id)->get();
            // Retornar 200 con array vacío si no hay adicionales, en lugar de 404
            return response()->json($adicionales, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener los adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    public function crearAdicional(Request $request)
    {
        try {
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'categoria_adicional_id' => 'required|exists:categoria_adicional,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB = 5120 KB
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            // Guardar la imagen en el almacenamiento
            if ($request->hasFile('foto')) {
                // Se guarda la imagen en el disco público y se obtiene el path
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                // Obtiene la ruta relativa para almacenar en la base de datos
                $fotoUrl = Storage::url($fotoPath);
            }

            $adicional = Adicional::create([
                'empresa_id' => $request->empresa_id,
                'categoria_adicional_id' => $request->categoria_adicional_id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'foto' => $fotoUrl ?? null,  // Solo guarda la URL de la imagen si fue cargada
                'precio' => $request->precio,
                'status' => $request->status,
            ]);

            return response()->json($adicional, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el adicional', 'error' => $e->getMessage()], 500);
        }
    }

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

    public function actualizarAdicional(Request $request, $id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'categoria_adicional_id' => 'required|exists:categoria_adicional,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB = 5120 KB
                'precio' => 'required|numeric',
                'status' => 'required|in:active,inactive',
            ]);

            // Actualizar la foto si se proporciona una nueva
            if ($request->hasFile('foto')) {
                // Eliminar la foto anterior si existe
                if ($adicional->foto) {
                    Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
                }
                
                // Se guarda la nueva imagen en el disco público y se obtiene el path
                $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
                // Obtiene la ruta relativa para almacenar en la base de datos
                $fotoUrl = Storage::url($fotoPath);
                $adicional->foto = $fotoUrl;
            }

            $adicional->update($request->except('foto'));
            return response()->json($adicional);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function eliminarAdicional($id)
    {
        try {
            $adicional = Adicional::findOrFail($id);
            
            // Eliminar la foto del almacenamiento si existe
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
}