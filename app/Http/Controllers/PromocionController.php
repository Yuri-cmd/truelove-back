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
                // Si ya tiene la URL completa, no la modifiques
                if ($promocion->imagen && !str_starts_with($promocion->imagen, 'http')) {
                    $promocion->imagen = env('APP_URL') . $promocion->imagen;
                }
                return $promocion;
            });

        return response()->json($promociones);
    }

    public function store(Request $request)
    {
        \Log::info('=== INICIO DEBUG PROMOCION ===');
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
                $file = $request->file('imagen');
                \Log::info('Archivo recibido', [
                    'nombre' => $file->getClientOriginalName(),
                    'tamaño' => $file->getSize(),
                    'valido' => $file->isValid(),
                    'mime' => $file->getMimeType()
                ]);
                if (!$file->isValid()) {
                    throw new \Exception('Archivo inválido: ' . $file->getErrorMessage());
                }

                $directory = 'promociones-img';

                // Asegura que el directorio exista
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }

                // Guarda la imagen en el disco 'public'
                $imagePath = $file->store($directory, 'public');
                \Log::info('Store ejecutado', ['resultado' => $imagePath]);

                if (!$imagePath || !Storage::disk('public')->exists($imagePath)) {
                    throw new \Exception('No se pudo guardar la imagen.');
                }

                // Genera la URL accesible públicamente
                $fullUrl = Storage::url($imagePath);
                \Log::info('URL generada', ['url' => $fullUrl, 'path' => $imagePath]);

                $data['imagen'] = $fullUrl;
            } else {
                \Log::info('No se detectó archivo de imagen');
                $data['imagen'] = null;
            }

            $promocion = Promocion::create($data);
            \Log::info('Promoción creada exitosamente', ['promocion_id' => $promocion->id]);
            \Log::info('=== FIN DEBUG PROMOCION ===');

            return response()->json($promocion, 201);
        } catch (\Exception $e) {
            \Log::error('Error en store de promoción', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
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

                $directory = 'promociones-img';

                // Crear directorio si no existe
                if (!Storage::disk('custom_public')->exists($directory)) {
                    Storage::disk('custom_public')->makeDirectory($directory);
                    \Log::info('Directorio creado', ['directory' => $directory]);
                }

                // Eliminar imagen anterior si existe
                if ($promocion->imagen) {
                    // Extraer solo el path de la URL completa
                    $oldPath = str_replace('/storage/', '', parse_url($promocion->imagen, PHP_URL_PATH));
                    if ($oldPath && Storage::disk('custom_public')->exists($oldPath)) {
                        Storage::disk('custom_public')->delete($oldPath);
                        \Log::info('Imagen anterior eliminada', ['path' => $oldPath]);
                    }
                }

                $imagePath = $request->file('imagen')->store($directory, 'custom_public');

                if (!$imagePath) {
                    throw new \Exception('No se pudo guardar la imagen');
                }

                // Generar URL completa
                $data['imagen'] = Storage::url($imagePath);
                \Log::info('Nueva imagen guardada', ['path' => $imagePath, 'url' => $data['imagen']]);
            }

            $promocion->update($data);
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
                $imagePath = str_replace('/storage/', '', parse_url($promocion->imagen, PHP_URL_PATH));
                if ($imagePath && Storage::disk('custom_public')->exists($imagePath)) {
                    Storage::disk('custom_public')->delete($imagePath);
                    \Log::info('Imagen eliminada', ['path' => $imagePath]);
                }
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
}
