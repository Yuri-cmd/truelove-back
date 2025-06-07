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
                // Construir URL completa si solo tiene el path
                if ($promocion->imagen && !str_starts_with($promocion->imagen, 'http')) {
                    $promocion->imagen = env('APP_URL') . '/storage/' . $promocion->imagen;
                }
                return $promocion;
            });

        return response()->json($promociones);
    }

    public function store(Request $request)
    {
        try {
            \Log::info('Iniciando método store de Promocion', [
                'datos_request' => $request->all(),
                'tiene_imagen' => $request->hasFile('imagen'),
                'file_imagen' => $request->file('imagen')
            ]);

            $data = $request->validate([
                'titulo' => 'required|string|max:255',
                'subtitulo' => 'required|string|max:255',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
                'estado' => 'boolean'
            ]);

            $promocion = new Promocion();
            $promocion->titulo = $data['titulo'];
            $promocion->subtitulo = $data['subtitulo'];
            $promocion->estado = $data['estado'] ?? 1;

            // Solo guardar la imagen si fue enviada
            if ($request->hasFile('imagen')) {
                $imagePath = $request->file('imagen')->store('promociones-img', 'custom_public');
                \Log::info('Imagen subida correctamente', [
                    'path' => $imagePath
                ]);
                $promocion->imagen = $imagePath;
            } else {
                \Log::info('No se envió imagen o no es válida');
                $promocion->imagen = null; // O simplemente no lo asignes si es nullable
            }

            $promocion->save();

            \Log::info('Promocion guardada', [
                'promocion' => $promocion->toArray()
            ]);

            return response()->json($promocion, 201);
        } catch (\Exception $e) {
            \Log::error('Error al guardar promoción', [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error procesando la solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $promocion = Promocion::findOrFail($id);

        // Construir URL completa para la respuesta
        if ($promocion->imagen && !str_starts_with($promocion->imagen, 'http')) {
            $promocion->imagen = env('APP_URL') . '/storage/' . $promocion->imagen;
        }

        return response()->json($promocion);
    }

    public function update(Request $request, $id)
    {
        try {
            $promocion = Promocion::findOrFail($id);
            $data = $request->validate([
                'titulo' => 'sometimes|required|string|max:255',
                'subtitulo' => 'sometimes|required|string|max:255',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
                'estado' => 'sometimes|boolean'
            ]);

            // Actualizar campos básicos
            $promocion->update([
                'titulo' => $data['titulo'] ?? $promocion->titulo,
                'subtitulo' => $data['subtitulo'] ?? $promocion->subtitulo,
                'estado' => $data['estado'] ?? $promocion->estado
            ]);

            // Procesar nueva imagen si se envió
            if ($request->hasFile('imagen')) {
                \Log::info('Actualizando imagen de promoción');

                // Eliminar imagen anterior si existe
                if ($promocion->imagen) {
                    $this->eliminarImagenAnterior($promocion->imagen);
                }

                // Guardar nueva imagen
                $imagePath = $request->file('imagen')->store('promociones-img', 'custom_public');

                if ($imagePath) {
                    $promocion->imagen = $imagePath; // Guardar solo el path
                    $promocion->save();

                    \Log::info('Nueva imagen de promoción guardada', [
                        'promocion_id' => $promocion->id,
                        'path' => $imagePath
                    ]);
                } else {
                    throw new \Exception('No se pudo guardar la nueva imagen');
                }
            }

            return response()->json($promocion);
        } catch (\Exception $e) {
            \Log::error('Error en update de promoción', [
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

            // Eliminar imagen si existe
            if ($promocion->imagen) {
                $this->eliminarImagenAnterior($promocion->imagen);
            }

            $promocion->delete();
            return response()->json(['message' => 'Promoción eliminada correctamente']);
        } catch (\Exception $e) {
            \Log::error('Error en destroy de promoción', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error eliminando la promoción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar imagen anterior del storage
     */
    private function eliminarImagenAnterior($imagenPath)
    {
        try {
            // Si es una URL completa, extraer solo el path
            if (str_starts_with($imagenPath, 'http') || str_starts_with($imagenPath, '/storage/')) {
                $path = str_replace('/storage/', '', parse_url($imagenPath, PHP_URL_PATH));
            } else {
                $path = $imagenPath; // Ya es solo el path
            }

            if ($path && Storage::disk('custom_public')->exists($path)) {
                Storage::disk('custom_public')->delete($path);
                \Log::info('Imagen anterior eliminada', ['path' => $path]);
            }
        } catch (\Exception $e) {
            \Log::warning('No se pudo eliminar imagen anterior', [
                'path' => $imagenPath,
                'error' => $e->getMessage()
            ]);
        }
    }
}
