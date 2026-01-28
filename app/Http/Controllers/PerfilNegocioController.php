<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use App\Models\PerfilNegocio;
use Illuminate\Support\Facades\File;
// use Illuminate\Support\Facades\Log;

class PerfilNegocioController extends Controller
{
    public function actualizarLogo(Request $request)
    {
        try {
            // Debug para ver qué token está llegando
            // Log::info('Token recibido:', [
            //     'token' => $request->header('Authorization')
            // ]);
    
            // Verificar autenticación
            if (!$request->user()) {
                // Log::error('Usuario no autenticado');
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }
    
            // Debug para ver el usuario autenticado
            // Log::info('Usuario autenticado:', [
            //     'user_id' => $request->user()->id
            // ]);
    
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
            // Log::info('URL del logo generada:', [
            //     'url' => $logoUrl
            // ]);
    
            return response()->json([
                'success' => true,
                'ruta_logo' => $logoUrl,
                'message' => 'Logo actualizado correctamente'
            ]);
    
        } catch (\Exception $e) {
            // Log::error('Error en actualizarLogo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  public function actualizarBanner(Request $request)
{
    try {
        // Verificar autenticación
        if (!$request->user()) {
            // \Log::error('Usuario no autenticado');
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        // Validar el archivo con reglas más específicas
        $validator = \Validator::make($request->all(), [
            'banner' => 'required|file|mimes:jpeg,jpg,png,gif|max:4096' // 4MB máximo
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar que el archivo sea válido
        if (!$request->hasFile('banner') || !$request->file('banner')->isValid()) {
            return response()->json([
                'message' => 'El archivo de banner no es válido'
            ], 422);
        }

        $file = $request->file('banner');
        
        // Validación adicional del tipo MIME
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'message' => 'Formato de imagen no válido. Solo se permiten archivos JPG, PNG y GIF.'
            ], 422);
        }

        // Verificar tamaño del archivo
        if ($file->getSize() > 4 * 1024 * 1024) { // 4MB
            return response()->json([
                'message' => 'La imagen es demasiado grande. El tamaño máximo permitido es 4MB.'
            ], 422);
        }

        $fileName = time() . '_' . $file->getClientOriginalName();
        
        // Crear directorio si no existe
        $path = public_path('banners-negocio');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        // Mover el archivo
        $file->move($path, $fileName);
        
        $rutaRelativa = 'banners-negocio/' . $fileName;

        // Obtener el business_registration_id directamente del usuario autenticado
        $businessRegistrationId = $request->user()->businessRegistration->id;

        // Obtener el perfil actual para eliminar el banner anterior si existe
        $perfilActual = PerfilNegocio::where('business_registration_id', $businessRegistrationId)->first();
        
        if ($perfilActual && $perfilActual->banner) {
            $rutaAnterior = public_path($perfilActual->banner);
            if (File::exists($rutaAnterior)) {
                File::delete($rutaAnterior);
            }
        }

        $perfil = PerfilNegocio::updateOrCreate(
            ['business_registration_id' => $businessRegistrationId],
            ['banner' => $rutaRelativa]
        );

        // Construir la URL completa
        $bannerUrl = url($rutaRelativa);

        return response()->json([
            'success' => true,
            'banner' => $bannerUrl,
            'message' => 'Banner actualizado correctamente'
        ]);

    } catch (\Exception $e) {
        // \Log::error('Error en actualizarBanner: ' . $e->getMessage(), [
        //     'trace' => $e->getTraceAsString(),
        //     'request_data' => $request->except(['banner'])
        // ]);
        
        return response()->json([
            'message' => 'Error interno del servidor',
            'error' => config('app.debug') ? $e->getMessage() : 'Error al procesar la imagen'
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
            
            // Modificar la ruta del banner para incluir la URL completa
            if ($perfil->banner) {
                $perfil->banner = url($perfil->banner);
            }
    
            return response()->json($perfil);
        } catch (\Exception $e) {
            // Log::error('Error en obtenerPerfil: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function guardarHorario(Request $request, $id = null)
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

            if ($id) {
                // Actualizar horario existente
                $horario = \App\Models\HorarioNegocio::where('id', $id)
                    ->whereHas('perfilNegocio', function ($query) use ($businessRegistrationId) {
                        $query->where('business_registration_id', $businessRegistrationId);
                    })
                    ->firstOrFail();

                $horario->update([
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
            } else {
                // Crear nuevo horario asociado al perfil
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
            }

            return response()->json($horario);

        } catch (\Exception $e) {
            // Log::error('Error en guardarHorario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al guardar el horario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarHorario(Request $request, $id)
    {
        try {
            if (!$request->user()) {
                // Log::error('Usuario no autenticado');
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $businessRegistrationId = $request->user()->businessRegistration->id;

            // Buscar el horario por ID y asegurarse de que pertenece al negocio del usuario autenticado
            $horario = \App\Models\HorarioNegocio::where('id', $id)
                                                ->whereHas('perfilNegocio', function ($query) use ($businessRegistrationId) {
                                                    $query->where('business_registration_id', $businessRegistrationId);
                                                })
                                                ->first();

            if (!$horario) {
                return response()->json(['message' => 'Horario no encontrado o no pertenece a este negocio'], 404);
            }

            $horario->delete();

            return response()->json(['message' => 'Horario eliminado correctamente'], 200);

        } catch (\Exception $e) {
            // Log::error('Error al eliminar horario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el horario',
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
            // Log::error('Error en actualizarFotoPerfil: ' . $e->getMessage());
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
            // Log::info('Perfil del negocio:', [
            //     'perfil' => $perfil,
            //     'foto_perfil' => $perfil ? $perfil->foto_perfil : null
            // ]);

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
            // Log::error('Error en obtenerDatosNegocio: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los datos del negocio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
   
    public function obtenerEstablecimientoActual(Request $request)
    {
        try {
            // Verificar autenticación
            if (!$request->user()) {
                // Log::error('Usuario no autenticado');
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $user = $request->user();
            $businessRegistration = $user->businessRegistration;
            
            if (!$businessRegistration) {
                return response()->json(['message' => 'No se encontró el registro de negocio'], 404);
            }

            $establecimiento = $businessRegistration->establecimiento;
            
            if (!$establecimiento) {
                return response()->json(['message' => 'No se encontró el establecimiento'], 404);
            }

            // Retornar los datos del establecimiento
            return response()->json([
                'nombre_establecimiento' => $establecimiento->nombre_establecimiento,
                'calle' => $establecimiento->calle,
                'numero' => $establecimiento->numero,
                'codigo_postal' => $establecimiento->codigo_postal,
                'provincia' => $establecimiento->provincia,
                'ciudad' => $establecimiento->ciudad,
                'referencia' => $establecimiento->referencia,
                'latitud' => $establecimiento->latitud,
                'longitud' => $establecimiento->longitud,
                'direccion_completa' => $establecimiento->direccion_completa,
                'id' => $establecimiento->id,
                'business_registration_id' => $establecimiento->business_registration_id
            ]);

        } catch (\Exception $e) {
            // Log::error('Error en obtenerEstablecimientoActual: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los datos del establecimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar establecimiento del usuario autenticado
     */
    public function actualizarEstablecimiento(Request $request)
    {
        try {
            // Verificar autenticación
            if (!$request->user()) {
                // Log::error('Usuario no autenticado');
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Validar los datos recibidos
            $validator = \Validator::make($request->all(), [
                'businessName' => 'required|string|min:2',
                'street' => 'required|string|min:2',
                'number' => 'required|string|min:1',
                'postalCode' => 'required|string|min:5',
                'province' => 'required|string|min:2',
                'city' => 'required|string|min:2',
                'reference' => 'nullable|string',
                'coordinates' => 'required|array',
                'coordinates.0' => 'required|numeric', // longitud
                'coordinates.1' => 'required|numeric', // latitud
                'fullAddress' => 'required|string',
            ]);

            if ($validator->fails()) {
                // Log::error('Validación fallida:', $validator->errors()->toArray());
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = $request->user();
            $businessRegistration = $user->businessRegistration;
            
            if (!$businessRegistration) {
                return response()->json(['message' => 'No se encontró el registro de negocio'], 404);
            }

            $establecimiento = $businessRegistration->establecimiento;
            
            if (!$establecimiento) {
                return response()->json(['message' => 'No se encontró el establecimiento'], 404);
            }

            // Actualizar el establecimiento
            $establecimiento->update([
                'nombre_establecimiento' => $request->businessName,
                'calle' => $request->street,
                'numero' => $request->number,
                'codigo_postal' => $request->postalCode,
                'provincia' => $request->province,
                'ciudad' => $request->city,
                'referencia' => $request->reference,
                'latitud' => $request->coordinates[1], // latitud
                'longitud' => $request->coordinates[0], // longitud
                'direccion_completa' => $request->fullAddress,
            ]);

            // Log::info('Establecimiento actualizado correctamente:', [
            //     'establecimiento_id' => $establecimiento->id,
            //     'business_registration_id' => $businessRegistration->id
            // ]);

            return response()->json([
                'message' => 'Establecimiento actualizado correctamente',
                'establecimiento' => [
                    'nombre_establecimiento' => $establecimiento->nombre_establecimiento,
                    'calle' => $establecimiento->calle,
                    'numero' => $establecimiento->numero,
                    'codigo_postal' => $establecimiento->codigo_postal,
                    'provincia' => $establecimiento->provincia,
                    'ciudad' => $establecimiento->ciudad,
                    'referencia' => $establecimiento->referencia,
                    'latitud' => $establecimiento->latitud,
                    'longitud' => $establecimiento->longitud,
                    'direccion_completa' => $establecimiento->direccion_completa,
                ]
            ]);

        } catch (\Exception $e) {
            // Log::error('Error en actualizarEstablecimiento: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar el establecimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
   public function obtenerConfiguracionPOS(Request $request)
{
    try {
        $businessRegistrationId = $request->user()->businessRegistration->id;
        $businessRegistration = BusinessRegistration::find($businessRegistrationId);
        
        return response()->json([
            'posToDriver' => $businessRegistration->posToDriver,
            'entrega_documento_venta' => $businessRegistration->entrega_documento_venta,
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al obtener configuración'], 500);
    }
}

public function actualizarConfiguracionPOS(Request $request)
{
    try {
        $request->validate([
            'posToDriver' => 'required|integer|in:0,1,2,3',
            'entrega_documento_venta' => 'required|integer|in:0,1',
        ]);

        $businessRegistrationId = $request->user()->businessRegistration->id;
        $businessRegistration = BusinessRegistration::find($businessRegistrationId);
        
        $businessRegistration->update([
            'posToDriver' => $request->posToDriver,
            'entrega_documento_venta' => $request->entrega_documento_venta,
        ]);

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'posToDriver' => $businessRegistration->posToDriver,
            'entrega_documento_venta' => $businessRegistration->entrega_documento_venta,
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al actualizar configuración'], 500);
    }
}
public function obtenerConfiguracionPagoDigital(Request $request)
{
    try {
        $businessRegistrationId = $request->user()->businessRegistration->id;
        $negocio = \App\Models\Negocio::where('business_registration_id', $businessRegistrationId)->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        return response()->json([
            'tipo_pago_digital' => $negocio->tipo_pago_digital,
            'numero_pago_digital' => $negocio->numero_pago_digital,
            'nombre_titular_pago_digital' => $negocio->nombre_titular_pago_digital,
        ]);
    } catch (\Exception $e) {
        // Log::error('Error al obtener configuración de pago digital: ' . $e->getMessage());
        return response()->json(['message' => 'Error al obtener configuración'], 500);
    }
}

public function actualizarConfiguracionPagoDigital(Request $request)
{
    try {
        $request->validate([
            'tipo_pago_digital' => 'required|integer|in:0,1,2',
            'numero_pago_digital' => 'nullable|string|regex:/^\d{9}$/',
            'nombre_titular_pago_digital' => 'nullable|string|min:2|max:255',
        ]);

        $businessRegistrationId = $request->user()->businessRegistration->id;
        $negocio = \App\Models\Negocio::where('business_registration_id', $businessRegistrationId)->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        $negocio->update([
            'tipo_pago_digital' => $request->tipo_pago_digital,
            'numero_pago_digital' => $request->numero_pago_digital,
            'nombre_titular_pago_digital' => $request->nombre_titular_pago_digital,
        ]);

        return response()->json([
            'message' => 'Configuración de pago digital actualizada correctamente',
            'tipo_pago_digital' => $negocio->tipo_pago_digital,
            'numero_pago_digital' => $negocio->numero_pago_digital,
            'nombre_titular_pago_digital' => $negocio->nombre_titular_pago_digital,
        ]);
    } catch (\Exception $e) {
        // Log::error('Error al actualizar configuración de pago digital: ' . $e->getMessage());
        return response()->json(['message' => 'Error al actualizar configuración'], 500);
    }
}


}
