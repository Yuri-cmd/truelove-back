<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromocionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->showAll === 'true') {
            return response()->json(Promocion::all());
        }

        $promociones = Promocion::where('estado', 1)->get(['id', 'titulo', 'subtitulo', 'imagen', 'estado'])
            ->map(function ($promocion) {
                $promocion->imagen = $promocion->imagen ?  env('APP_URL') . '/storage/' . $promocion->imagen : '';
                return $promocion;
            });

        return response()->json($promociones);
    }

    public function store(Request $request)
    {
        \Log::info('Iniciando store de promoción');
        \Log::info('Request completo', ['request' => $request->all()]);
        
        try {
            $data = $request->validate([
                'titulo' => 'required|string|max:255',
                'subtitulo' => 'required|string|max:255',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
                'estado' => 'boolean'
            ]);

            \Log::info('Datos validados', ['data' => $data]);

            if ($request->hasFile('imagen')) {
                \Log::info('Tiene archivo de imagen');
                \Log::info('Nombre original', ['nombre' => $request->file('imagen')->getClientOriginalName()]);
                
                // Verificar si el directorio existe, si no, crearlo
                $directory = 'promociones-img';
                if (!Storage::disk('custom_public')->exists($directory)) {
                    Storage::disk('custom_public')->makeDirectory($directory);
                    \Log::info('Directorio creado', ['directory' => $directory]);
                }

                $imagePath = $request->file('imagen')->store($directory, 'custom_public');
                
                if (!$imagePath) {
                    throw new \Exception('No se pudo guardar la imagen');
                }
                
                \Log::info('Imagen guardada', ['path' => $imagePath]);
                $data['imagen'] = $imagePath;
            } else {
                \Log::info('No se detectó archivo de imagen');
            }

            $promocion = Promocion::create($data);
            return response()->json($promocion, 201);
            
        } catch (\Exception $e) {
            \Log::error('Error en store', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error procesando la solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Promocion::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        try {
            $promocion = Promocion::findOrFail($id);
            $data = $request->validate([
                'titulo' => 'sometimes|required|string|max:255',
                'subtitulo' => 'sometimes|required|string|max:255',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
                'estado' => 'boolean'
            ]);

            if ($request->hasFile('imagen')) {
                \Log::info('Actualizando imagen de promoción');
                
                // Verificar si el directorio existe
                $directory = 'promociones-img';
                if (!Storage::disk('custom_public')->exists($directory)) {
                    Storage::disk('custom_public')->makeDirectory($directory);
                    \Log::info('Directorio creado', ['directory' => $directory]);
                }

                if ($promocion->imagen) {
                    Storage::disk('custom_public')->delete($promocion->imagen);
                }

                $imagePath = $request->file('imagen')->store($directory, 'custom_public');
                
                if (!$imagePath) {
                    throw new \Exception('No se pudo guardar la imagen');
                }
                
                $data['imagen'] = $imagePath;
            }

            $promocion->update($data);
            return response()->json($promocion);
            
        } catch (\Exception $e) {
            \Log::error('Error en update', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error actualizando la promoción: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $promocion = Promocion::findOrFail($id);
            if ($promocion->imagen) {
                Storage::disk('custom_public')->delete($promocion->imagen);
            }
            $promocion->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            \Log::error('Error en destroy', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error eliminando la promoción: ' . $e->getMessage()], 500);
        }
    }
}
