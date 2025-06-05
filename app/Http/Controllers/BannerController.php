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
            $banner->url_imagen = $banner->url_imagen ?  env('APP_URL') . '/storage/' . $banner->url_imagen : '';
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
        \Log::info('Iniciando store de banner');
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
                \Log::info('Tiene archivo de imagen');
                \Log::info('Nombre original', ['nombre' => $request->file('url_imagen')->getClientOriginalName()]);
                
                // Verificar si el directorio existe, si no, crearlo
                $directory = 'banners';
                if (!Storage::disk('custom_public')->exists($directory)) {
                    Storage::disk('custom_public')->makeDirectory($directory);
                    \Log::info('Directorio creado', ['directory' => $directory]);
                }

                $imagePath = $request->file('url_imagen')->store($directory, 'custom_public');
                
                if (!$imagePath) {
                    throw new \Exception('No se pudo guardar la imagen');
                }
                
                \Log::info('Imagen guardada', ['path' => $imagePath]);
                $data['url_imagen'] = $imagePath;
            } else {
                \Log::info('No se detectó archivo de imagen');
            }

            $banner = Banner::create($data);
            return response()->json($banner, 201);
            
        } catch (\Exception $e) {
            \Log::error('Error en store', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error procesando la solicitud: ' . $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'subtitulo' => 'sometimes|required|string|max:255',
            'color_fondo' => 'sometimes|required|string|max:7',
            'texto_boton' => 'sometimes|required|string|max:255',
            'url_boton' => 'sometimes|required|url',
            'url_imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:2048', // Validar la imagen
            'estado' => 'sometimes|required|boolean'
        ]);

        if ($request->hasFile('url_imagen')) {
            if ($banner->url_imagen) {
                Storage::disk('public')->delete($banner->url_imagen);
            }

            $imagePath = $request->file('url_imagen')->store('banners', 'custom_public');
            $data['url_imagen'] = $imagePath;
        }

        $banner->update($data);

        return response()->json($banner);
    }


    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();
        return response()->json(['message' => 'Eliminado correctamente']);
    }
}