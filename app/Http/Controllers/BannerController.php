<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        if ($request->showAll === 'true') {
            return response()->json(Banner::all());
        }

        $banners = Banner::where('estado', 1)->get()->map(function ($banner) {
            // Construir URL completa si solo tiene el path
            if ($banner->url_imagen && !str_starts_with($banner->url_imagen, 'http')) {
                $banner->url_imagen = config('app.url') . '/storage/' . $banner->url_imagen;
            }
            return $banner;
        });

        return response()->json($banners);
    }

    public function show($id)
    {
        $banner = Banner::findOrFail($id);
        
        // Construir URL completa para la respuesta
        if ($banner->url_imagen && !str_starts_with($banner->url_imagen, 'http')) {
            $banner->url_imagen = config('app.url') . '/storage/' . $banner->url_imagen;
        }
        
        return response()->json($banner);
    }

  public function store(Request $request)
{
    try {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'subtitulo' => 'required|string|max:255',
            'color_fondo' => 'required|string|max:7',
            'texto_boton' => 'required|string|max:255',
            'url_boton' => 'nullable|url',
            'url_imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
            'estado' => 'required|boolean'
        ]);

        // Procesar imagen si existe
        if ($request->hasFile('url_imagen')) {
            $imagePath = $request->file('url_imagen')->store('banners', 'custom_public');
            $data['url_imagen'] = $imagePath; // Agregar el path al array de datos
        } else {
            $data['url_imagen'] = null; // Asegurar que sea null si no hay imagen
        }

        // Crear el banner con todos los datos incluyendo la imagen (si existe)
        $banner = Banner::create($data);

        \Log::info('Banner creado exitosamente', [
            'banner_id' => $banner->id,
            'data' => $data
        ]);

        return response()->json($banner, 201);
        
    } catch (\Exception $e) {
        \Log::error('Error en store de banner', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        return response()->json(['error' => 'Error procesando la solicitud: ' . $e->getMessage()], 500);
    }
}

    public function update(Request $request, $id)
    {
        try {
            $banner = Banner::findOrFail($id);
            $data = $request->validate([
                'titulo' => 'sometimes|required|string|max:255',
                'subtitulo' => 'sometimes|required|string|max:255',
                'color_fondo' => 'sometimes|required|string|max:7',
                'texto_boton' => 'sometimes|required|string|max:255',
                'url_boton' => 'sometimes|nullable|url',
                'url_imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048',
                'estado' => 'sometimes|required|boolean'
            ]);

            // Actualizar campos básicos
            $banner->update([
                'titulo' => $data['titulo'] ?? $banner->titulo,
                'subtitulo' => $data['subtitulo'] ?? $banner->subtitulo,
                'color_fondo' => $data['color_fondo'] ?? $banner->color_fondo,
                'texto_boton' => $data['texto_boton'] ?? $banner->texto_boton,
                'url_boton' => $data['url_boton'] ?? $banner->url_boton,
                'estado' => $data['estado'] ?? $banner->estado
            ]);

            // Procesar nueva imagen si se envió
            if ($request->hasFile('url_imagen')) {
                \Log::info('Actualizando imagen de banner');
                
                // Eliminar imagen anterior si existe
                if ($banner->url_imagen) {
                    $this->eliminarImagenAnterior($banner->url_imagen);
                }

                // Guardar nueva imagen
                $imagePath = $request->file('url_imagen')->store('banners', 'custom_public');
                
                if ($imagePath) {
                    $banner->url_imagen = $imagePath; // Guardar solo el path
                    $banner->save();
                    
                    \Log::info('Nueva imagen de banner guardada', [
                        'banner_id' => $banner->id,
                        'path' => $imagePath
                    ]);
                } else {
                    throw new \Exception('No se pudo guardar la nueva imagen');
                }
            }

            return response()->json($banner);
            
        } catch (\Exception $e) {
            \Log::error('Error en update de banner', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error actualizando el banner: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $banner = Banner::findOrFail($id);
            
            // Eliminar imagen si existe
            if ($banner->url_imagen) {
                $this->eliminarImagenAnterior($banner->url_imagen);
            }
            
            $banner->delete();
            return response()->json(['message' => 'Banner eliminado correctamente']);
            
        } catch (\Exception $e) {
            \Log::error('Error en destroy de banner', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error eliminando el banner: ' . $e->getMessage()], 500);
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