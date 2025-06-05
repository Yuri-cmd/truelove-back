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
            // Si ya tiene la URL completa, no la modifiques
            if ($banner->url_imagen && !str_starts_with($banner->url_imagen, 'http')) {
                $banner->url_imagen = env('APP_URL') . $banner->url_imagen;
            }
            return $banner;
        });

        return response()->json($banners);
    }

    public function show($id)
    {
        $banner = Banner::findOrFail($id);
        return response()->json($banner);
    }

    public function store(Request $request)
    {
        \Log::info('=== INICIO DEBUG BANNER ===');
        \Log::info('Request completo', ['request' => $request->all()]);
        
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

            \Log::info('Datos validados', ['data' => $data]);

            if ($request->hasFile('url_imagen')) {
                $file = $request->file('url_imagen');
                
                \Log::info('Archivo recibido', [
                    'nombre' => $file->getClientOriginalName(),
                    'tamaño' => $file->getSize(),
                    'valido' => $file->isValid(),
                    'mime' => $file->getMimeType()
                ]);
                
                // Verificar que el archivo sea válido
                if (!$file->isValid()) {
                    throw new \Exception('Archivo inválido: ' . $file->getErrorMessage());
                }
                
                $directory = 'banners';
                
                // Verificar espacio en disco
                $storagePath = storage_path('app/public');
                if (is_dir($storagePath)) {
                    $diskSpace = disk_free_space($storagePath);
                    \Log::info('Espacio disponible', ['bytes' => $diskSpace, 'MB' => round($diskSpace / 1024 / 1024, 2)]);
                }
                
                // Crear directorio si no existe
                if (!Storage::disk('custom_public')->exists($directory)) {
                    try {
                        $created = Storage::disk('custom_public')->makeDirectory($directory);
                        \Log::info('Directorio creado', ['success' => $created, 'directory' => $directory]);
                    } catch (\Exception $e) {
                        \Log::error('Error creando directorio', ['error' => $e->getMessage()]);
                        throw new \Exception('No se pudo crear el directorio de imágenes: ' . $e->getMessage());
                    }
                }

                // Intentar guardar el archivo
                try {
                    $imagePath = $file->store($directory, 'custom_public');
                    \Log::info('Store ejecutado', ['resultado' => $imagePath]);
                    
                    if (!$imagePath) {
                        throw new \Exception('El método store() retornó false - posible problema de permisos');
                    }
                    
                    // Verificar que el archivo realmente se guardó
                    if (!Storage::disk('custom_public')->exists($imagePath)) {
                        throw new \Exception('El archivo no existe después del store()');
                    }
                    
                    // Generar URL 
                    $fullUrl = Storage::url($imagePath);
                    \Log::info('URL generada', ['url' => $fullUrl, 'path' => $imagePath]);
                    
                    $data['url_imagen'] = $fullUrl;
                    
                } catch (\Exception $e) {
                    \Log::error('Error en store de archivo', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Error guardando imagen: ' . $e->getMessage());
                }
            } else {
                \Log::info('No se detectó archivo de imagen');
            }

            $banner = Banner::create($data);
            \Log::info('Banner creado exitosamente', ['banner_id' => $banner->id]);
            \Log::info('=== FIN DEBUG BANNER ===');
            
            return response()->json($banner, 201);
            
        } catch (\Exception $e) {
            \Log::error('Error en store de banner', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
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

            if ($request->hasFile('url_imagen')) {
                \Log::info('Actualizando imagen de banner');
                
                $directory = 'banners';
                
                // Crear directorio si no existe
                if (!Storage::disk('custom_public')->exists($directory)) {
                    Storage::disk('custom_public')->makeDirectory($directory);
                    \Log::info('Directorio creado', ['directory' => $directory]);
                }

                // Eliminar imagen anterior si existe
                if ($banner->url_imagen) {
                    // Extraer solo el path de la URL completa
                    $oldPath = str_replace('/storage/', '', parse_url($banner->url_imagen, PHP_URL_PATH));
                    if ($oldPath && Storage::disk('custom_public')->exists($oldPath)) {
                        Storage::disk('custom_public')->delete($oldPath);
                        \Log::info('Imagen anterior eliminada', ['path' => $oldPath]);
                    }
                }

                $imagePath = $request->file('url_imagen')->store($directory, 'custom_public');
                
                if (!$imagePath) {
                    throw new \Exception('No se pudo guardar la imagen');
                }
                
                // Generar URL completa
                $data['url_imagen'] = Storage::url($imagePath);
                \Log::info('Nueva imagen guardada', ['path' => $imagePath, 'url' => $data['url_imagen']]);
            }

            $banner->update($data);
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
                $imagePath = str_replace('/storage/', '', parse_url($banner->url_imagen, PHP_URL_PATH));
                if ($imagePath && Storage::disk('custom_public')->exists($imagePath)) {
                    Storage::disk('custom_public')->delete($imagePath);
                    \Log::info('Imagen eliminada', ['path' => $imagePath]);
                }
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
}