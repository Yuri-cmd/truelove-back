<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use App\Models\PerfilNegocio;
use App\Models\Establecimiento;
use App\Models\HorarioNegocio;
use Illuminate\Support\Facades\File;

class PerfilNegocioController extends Controller
{
    public function actualizarLogo(Request $request)
    {
        try {
            // Debug para ver qué token está llegando
            \Log::info('Token recibido:', [
                'token' => $request->header('Authorization')
            ]);
    
            // Verificar autenticación
            if (!$request->user()) {
                \Log::error('Usuario no autenticado');
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }
    
            // Debug para ver el usuario autenticado
            \Log::info('Usuario autenticado:', [
                'user_id' => $request->user()->id
            ]);
    
            // Validar el archivo
            $request->validate([
                'logo' => 'required|image|max:2048'
            ]);
    
            $file = $request->file('logo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Crear directorio si no existe
            $path = public_path('logos-negocio');
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0777, true, true);
            }
    
            // Mover el archivo
            $file->move($path, $fileName);
            
            $rutaRelativa = 'logos-negocio/' . $fileName;
    
            // Obtener el business_registration_id directamente del usuario autenticado
            $businessRegistrationId = $request->user()->businessRegistration->id;
    
            // Obtener el perfil actual para eliminar el logo anterior si existe
            $perfilActual = PerfilNegocio::where('business_registration_id', $businessRegistrationId)->first();
            
            if ($perfilActual && $perfilActual->ruta_logo) {
                $rutaAnterior = public_path($perfilActual->ruta_logo);
                if (File::exists($rutaAnterior)) {
                    File::delete($rutaAnterior);
                }
            }
    
            $perfil = PerfilNegocio::updateOrCreate(
                ['business_registration_id' => $businessRegistrationId],
                ['ruta_logo' => $rutaRelativa]
            );
    
            // Construir la URL completa usando url() en lugar de asset()
            $logoUrl = url($rutaRelativa);
    
            // Debug para ver la URL generada
            \Log::info('URL del logo generada:', [
                'url' => $logoUrl
            ]);
    
            return response()->json([
                'success' => true,
                'ruta_logo' => $logoUrl,
                'message' => 'Logo actualizado correctamente'
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error en actualizarLogo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    
    public function obtenerLogo(Request $request)
    {
        try {
            $businessRegistrationId = $request->user()->businessRegistration->id;
    
            $perfil = PerfilNegocio::firstOrCreate(
                ['business_registration_id' => $businessRegistrationId],
                [] // valores por defecto vacíos
            );
    
            // Cargar los horarios
            $perfil->load('horarios');
    
            // Modificar la ruta del logo para incluir la URL completa
            if ($perfil->ruta_logo) {
                $perfil->ruta_logo = url($perfil->ruta_logo);
            }
    
            return response()->json($perfil);
        } catch (\Exception $e) {
            \Log::error('Error en obtenerPerfil: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function guardarHorario(Request $request)
{
    try {
        $request->validate([
            'nombre' => 'required|string',
            'lunes' => 'required|boolean',
            'martes' => 'required|boolean',
            'miercoles' => 'required|boolean',
            'jueves' => 'required|boolean',
            'viernes' => 'required|boolean',
            'sabado' => 'required|boolean',
            'domingo' => 'required|boolean',
            'hora_apertura' => 'required|date_format:H:i',
            'hora_cierre' => 'required|date_format:H:i',
            'activo' => 'required|boolean'
        ]);

        // Obtener el business_registration_id del usuario autenticado
        $businessRegistrationId = $request->user()->businessRegistration->id;

        // Primero, asegurarnos de que existe un perfil para este negocio
        $perfil = PerfilNegocio::firstOrCreate(
            ['business_registration_id' => $businessRegistrationId]
        );

        // Crear el horario asociado al perfil
        $horario = $perfil->horarios()->create([
            'nombre' => $request->nombre,
            'lunes' => $request->lunes,
            'martes' => $request->martes,
            'miercoles' => $request->miercoles,
            'jueves' => $request->jueves,
            'viernes' => $request->viernes,
            'sabado' => $request->sabado,
            'domingo' => $request->domingo,
            'hora_apertura' => $request->hora_apertura,
            'hora_cierre' => $request->hora_cierre,
            'activo' => $request->activo
        ]);

        return response()->json($horario);

    } catch (\Exception $e) {
        \Log::error('Error en guardarHorario: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al guardar el horario',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function actualizarFotoPerfil(Request $request)
{
    try {
        $request->validate([
            'foto' => 'required|image|max:2048'
        ]);

        $user = $request->user();
        $businessRegistration = $user->businessRegistration;

        // Obtener el perfil actual para eliminar la foto anterior si existe
        $perfilActual = PerfilNegocio::where('business_registration_id', $businessRegistration->id)->first();
        if ($perfilActual && $perfilActual->foto_perfil) {
            $rutaAnterior = public_path($perfilActual->foto_perfil);
            if (File::exists($rutaAnterior)) {
                File::delete($rutaAnterior);
            }
        }

        $file = $request->file('foto');
        $fileName = time() . '_' . $file->getClientOriginalName();
        
        $path = public_path('fotos-perfil');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $file->move($path, $fileName);
        
        $rutaRelativa = 'fotos-perfil/' . $fileName;

        $perfil = PerfilNegocio::updateOrCreate(
            ['business_registration_id' => $businessRegistration->id],
            ['foto_perfil' => $rutaRelativa]
        );

        $fotoUrl = url($rutaRelativa);

        return response()->json([
            'success' => true,
            'foto_perfil' => $fotoUrl,
            'message' => 'Foto de perfil actualizada correctamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en actualizarFotoPerfil: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error interno del servidor',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function obtenerDatosNegocio(Request $request)
{
    try {
        $user = $request->user();
        $businessRegistration = $user->businessRegistration;
        $negocio = $businessRegistration->negocio;
        $establecimiento = $businessRegistration->establecimiento;
        $perfil = $businessRegistration->perfilNegocio;

        // Debug para ver qué contiene el perfil
        \Log::info('Perfil del negocio:', [
            'perfil' => $perfil,
            'foto_perfil' => $perfil ? $perfil->foto_perfil : null
        ]);

        $datos = [
            'nombre' => $businessRegistration->name . ' ' . $businessRegistration->lastName,
            'email' => $businessRegistration->email,
            'telefono' => $businessRegistration->phone,
            'fecha_registro' => $businessRegistration->created_at->format('Y-m-d'),
            'nombre_negocio' => $negocio->nombre,
            'direccion' => $establecimiento->direccion_completa,
            'sucursales' => $negocio->total_sucursales,
           
        ];

        return response()->json($datos);

    } catch (\Exception $e) {
        \Log::error('Error en obtenerDatosNegocio: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al obtener los datos del negocio',
            'error' => $e->getMessage()
        ], 500);
    }
}

}