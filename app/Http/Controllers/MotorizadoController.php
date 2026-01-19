<?php

namespace App\Http\Controllers;

use App\Models\EntregaCalendario;
use App\Models\RepartoRegistro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\CredencialesMotorizado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MotorizadoController extends Controller
{
    public function all()
    {
        return response()->json(RepartoRegistro::all());
    }

    public function changeState($id)
    {
        $motorizado = RepartoRegistro::find($id);
        $motorizado->estado = !$motorizado->estado;
        $motorizado->save();
        return response()->json(['message' => 'Estado actualizado'], 200);
    }

    public function getDetails($id)
    {
        try {
            $motorizado = RepartoRegistro::with([
                'datosPersonales',
                'datosBancarios',
                'registroVehiculo',
                'entregaCalendario'
            ])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $motorizado->id,
                    'personal' => [
                        'name' => $motorizado->nombres,
                        'lastName' => $motorizado->apellidos,
                        'email' => $motorizado->email,
                        'phone' => $motorizado->celular,
                        'tipo_documento' => $motorizado->tipo_documento,
                        'nro_documento' => $motorizado->nro_documento,
                        'created_at' => $motorizado->created_at,
                        'documento_imagen_frente' => $motorizado->documento_imagen_frente,
                        'documento_imagen_reverso' => $motorizado->documento_imagen_reverso,
                        'documentos_adicionales' => $motorizado->documentos_adicionales,
                        'vehiculo' => $motorizado->vehiculo,
                        'departamento' => $motorizado->departamento,
                    ],
                    'datosPersonales' => $motorizado->datosPersonales ? [
                        'fecha_nacimiento' => $motorizado->datosPersonales->fecha_nacimiento,
                        'genero' => $motorizado->datosPersonales->genero,
                        'url_selfie' => $motorizado->datosPersonales->url_selfie,
                        'departamento' => $motorizado->datosPersonales->getDepartamentoAttribute(),
                        'distrito' => $motorizado->datosPersonales->getDistritoAttribute(),
                        // Mantener ciudad para compatibilidad con el frontend existente
                        'provincia' => $motorizado->datosPersonales->getProvinciaAttribute()
                    ] : null,
                    'datosBancarios' => $motorizado->datosBancarios ? [
                        'titular' => $motorizado->datosBancarios->titular,
                        'dni' => $motorizado->datosBancarios->dni,
                        'banco' => $motorizado->datosBancarios->banco->nombre,
                        'tipo_cuenta' => $motorizado->datosBancarios->tipoCuenta->nombre,
                        'numero_cuenta' => $motorizado->datosBancarios->numero_cuenta,
                        'imagen_cuenta' => $motorizado->datosBancarios->url_imagen_cuenta
                    ] : null,
                    'registroVehiculo' => $motorizado->registroVehiculo ? [
                        'placa' => $motorizado->registroVehiculo->placa,
                        'licencia_conducir' => $motorizado->registroVehiculo->licencia_conducir,
                        'seguro' => $motorizado->registroVehiculo->seguro,
                        'tarjeta_propiedad' => $motorizado->registroVehiculo->tarjeta_propiedad,
                        'imagen_placa' => $motorizado->registroVehiculo->imagen_placa,
                        'imagen_licencia' => $motorizado->registroVehiculo->imagen_licencia,
                        'imagen_seguro' => $motorizado->registroVehiculo->imagen_seguro,
                        'imagen_tarjeta_propiedad' => $motorizado->registroVehiculo->imagen_tarjeta_propiedad
                    ] : null,
                    'entregaCalendario' => $motorizado->entregaCalendario,
                    'aprobado' => $motorizado->aprobado,
                    'pedidos_consecutivos' => $motorizado->pedidos_consecutivos

                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los detalles del motorizado'
            ], 500);
        }
    }

    public function aprobar(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $motorizado = RepartoRegistro::findOrFail($id);

            if ($motorizado->aprobado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El motorizado ya está aprobado'
                ], 400);
            }

            // Verificar si ya existe un usuario con el mismo correo
            $existingUser = User::where('email', $motorizado->email)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            $motorizado->aprobado = true;
            // Guardar cantidad de pedidos si se proporciona
            if ($request->has('pedidos_consecutivos')) {
                $motorizado->pedidos_consecutivos = $request->pedidos_consecutivos;
            }

            // Generar credenciales únicas
            // $username = $this->generateUniqueUsername($motorizado->nombres, $motorizado->apellidos);
            // $password = Str::random(10);

            $password = Str::random(10);

            // Obtener el rol de motorizado
            $rolMotorizado = Role::where('name', 'motorizado')->firstOrFail();

            // Crear un nuevo usuario
            $user = new User();
            $user->usuario = $motorizado->email; // Usar el email como usuario
            $user->name = $motorizado->nombres . ' ' . $motorizado->apellidos;
            $user->email = $motorizado->email;
            $user->password = bcrypt($password);
            $user->role_id = $rolMotorizado->id;
            $user->save();

            // Asociar el usuario al motorizado
            $motorizado->user_id = $user->id;
            $motorizado->save();

            // Enviar correo con las credenciales
          Mail::to($motorizado->email)->send(new CredencialesMotorizado($motorizado, $password));
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado aprobado exitosamente y credenciales enviadas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar motorizado: ' . $e->getMessage());

            // Verificar si es un error de duplicación de correo
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email_unique') !== false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el motorizado: ' . $e->getMessage()
            ], 500);
        }
    }
    // este es para motorizado
    private function generateUniqueUsername($nombres, $apellidos)
    {
        // Dividir nombres y apellidos
        $nombresArray = explode(' ', trim($nombres));
        $apellidosArray = explode(' ', trim($apellidos));

        // Obtener la primera letra del primer nombre en mayúscula
        $primeraNombre = ucfirst(substr($nombresArray[0], 0, 1));

        // // Obtener el segundo nombre si existe, si no, usar el primer nombre
        // $segundoNombre = isset($nombresArray[1]) ? strtolower($nombresArray[1]) : strtolower($nombresArray[0]);

        // Obtener la primera letra del primer apellido en mayúscula
        // $primeraApellido = isset($apellidosArray[0]) ? ucfirst(substr($apellidosArray[0], 0, 1)) : '';
        $primeraApellido = isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '';
        // // Obtener el segundo apellido si existe, si no, usar el primer apellido
        // $segundoApellido = isset($apellidosArray[1]) ? strtolower($apellidosArray[1]) : 
        //                    (isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '');

        // Construir el nombre de usuario base
        // $baseUsername = $primeraNombre . $segundoNombre . $primeraApellido . $segundoApellido;
        $baseUsername = $primeraNombre . $primeraApellido;
        $username = $baseUsername;
        $counter = 1;

        // Verificar si el usuario ya existe y agregar número si es necesario
        while (User::where('usuario', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
    public function delete($id)
    {
        DB::beginTransaction();
        try {
            // Buscar el motorizado
            $motorizado = RepartoRegistro::findOrFail($id);

            // Si el motorizado tiene un usuario asociado, eliminarlo también
            if ($motorizado->user_id) {
                $user = User::find($motorizado->user_id);
                if ($user) {
                    $user->delete();
                }
            }

            // Eliminar registros relacionados
            if ($motorizado->datosPersonales) {
                $motorizado->datosPersonales->delete();
            }

            if ($motorizado->datosBancarios) {
                $motorizado->datosBancarios->delete();
            }

            if ($motorizado->registroVehiculo) {
                $motorizado->registroVehiculo->delete();
            }

            if ($motorizado->entregaCalendario) {
                foreach ($motorizado->entregaCalendario as $entrega) {
                    $entrega->delete();
                }
            }

            // Finalmente eliminar el motorizado
            $motorizado->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el motorizado: ' . $e->getMessage()
            ], 500);
        }
    }
    public function actualizarCantidadPedidos(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pedidos_consecutivos' => 'required|integer|min:1|max:10',
                'nivel' => 'required|integer|min:1|max:5'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $motorizado = RepartoRegistro::findOrFail($id);
            $motorizado->pedidos_consecutivos = $request->pedidos_consecutivos;
            $motorizado->nivel = $request->nivel;
            $motorizado->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Nivel y cantidad de pedidos actualizados correctamente',
                'data' => [
                    'nivel' => $motorizado->nivel,
                    'pedidos_consecutivos' => $motorizado->pedidos_consecutivos
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar nivel y pedidos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos del vehículo (números de documentos)
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'placa' => 'required|string|max:255',
                'licencia_conducir' => 'required|string|max:255',
                'seguro' => 'required|string|max:255',
                'tarjeta_propiedad' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $motorizado = RepartoRegistro::findOrFail($id);

            // Verificar si tiene registro de vehículo
            $registroVehiculo = $motorizado->registroVehiculo;
            
            if (!$registroVehiculo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Este motorizado no tiene registro de vehículo'
                ], 404);
            }

            // Actualizar datos del vehículo
            $registroVehiculo->placa = $request->placa;
            $registroVehiculo->licencia_conducir = $request->licencia_conducir;
            $registroVehiculo->seguro = $request->seguro;
            $registroVehiculo->tarjeta_propiedad = $request->tarjeta_propiedad;
            $registroVehiculo->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Datos del vehículo actualizados correctamente',
                'data' => $registroVehiculo
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar datos del vehículo: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar los datos del vehículo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar documentos del motorizado (imágenes/PDFs)
     */
    public function updateDocumentos(Request $request, $id)
    {
        try {
            $motorizado = RepartoRegistro::findOrFail($id);

            // Actualizar documento de identidad frente
            if ($request->has('documento_imagen_frente') && $request->documento_imagen_frente) {
                $imagePath = $this->procesarImagenBase64(
                    $request->documento_imagen_frente,
                    'documento-motorizado',
                    'documento_motorizado_frente'
                );
                if ($imagePath) {
                    $motorizado->documento_imagen_frente = $imagePath;
                }
            }

            // Actualizar documento de identidad reverso
            if ($request->has('documento_imagen_reverso') && $request->documento_imagen_reverso) {
                $imagePath = $this->procesarImagenBase64(
                    $request->documento_imagen_reverso,
                    'documento-motorizado',
                    'documento_motorizado_reverso'
                );
                if ($imagePath) {
                    $motorizado->documento_imagen_reverso = $imagePath;
                }
            }

            // Actualizar documentos adicionales
            if ($request->has('documentos_adicionales') && is_array($request->documentos_adicionales)) {
                $documentosGuardados = [];
                foreach ($request->documentos_adicionales as $documento) {
                    if (isset($documento['archivo']) && isset($documento['categoria'])) {
                        $filePath = $this->procesarPDFBase64(
                            $documento['archivo'],
                            'documentos-adicionales',
                            'documento_adicional'
                        );
                        if ($filePath) {
                            $documentosGuardados[] = [
                                'ruta' => $filePath,
                                'tipo' => 'application/pdf',
                                'categoria' => $documento['categoria'],
                                'fecha_carga' => now()->toDateTimeString()
                            ];
                        }
                    }
                }
                if (!empty($documentosGuardados)) {
                    $motorizado->documentos_adicionales = $documentosGuardados;
                }
            }

            $motorizado->save();

            // Actualizar documentos del vehículo si existen
            if ($motorizado->registroVehiculo) {
                $registroVehiculo = $motorizado->registroVehiculo;

                if ($request->has('imagen_placa') && $request->imagen_placa) {
                    $imagePath = $this->procesarImagenBase64(
                        $request->imagen_placa,
                        'placas',
                        'placa'
                    );
                    if ($imagePath) {
                        $registroVehiculo->imagen_placa = $imagePath;
                    }
                }

                if ($request->has('imagen_licencia') && $request->imagen_licencia) {
                    $imagePath = $this->procesarImagenBase64(
                        $request->imagen_licencia,
                        'licencias',
                        'licencia'
                    );
                    if ($imagePath) {
                        $registroVehiculo->imagen_licencia = $imagePath;
                    }
                }

                if ($request->has('imagen_seguro') && $request->imagen_seguro) {
                    $imagePath = $this->procesarImagenBase64(
                        $request->imagen_seguro,
                        'seguros',
                        'seguro'
                    );
                    if ($imagePath) {
                        $registroVehiculo->imagen_seguro = $imagePath;
                    }
                }

                if ($request->has('imagen_tarjeta_propiedad') && $request->imagen_tarjeta_propiedad) {
                    $imagePath = $this->procesarImagenBase64(
                        $request->imagen_tarjeta_propiedad,
                        'tarjetas_propiedad',
                        'tarjeta_propiedad'
                    );
                    if ($imagePath) {
                        $registroVehiculo->imagen_tarjeta_propiedad = $imagePath;
                    }
                }

                $registroVehiculo->save();
            }

            // Actualizar documento de cuenta bancaria
            if ($motorizado->datosBancarios && $request->has('imagen_cuenta_bancaria') && $request->imagen_cuenta_bancaria) {
                $imagePath = $this->procesarImagenBase64(
                    $request->imagen_cuenta_bancaria,
                    'cuentas_bancarias',
                    'cuenta_bancaria'
                );
                if ($imagePath) {
                    $motorizado->datosBancarios->url_imagen_cuenta = $imagePath;
                    $motorizado->datosBancarios->save();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Documentos actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar documentos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar los documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar imagen base64 y guardarla
     */
    private function procesarImagenBase64($base64Data, $carpeta, $prefijo)
    {
        if (!$base64Data || !str_contains($base64Data, ',')) {
            return null;
        }

        try {
            $partes = explode(',', $base64Data);
            if (count($partes) !== 2) {
                return null;
            }

            $archivo = base64_decode($partes[1]);
            if ($archivo === false) {
                return null;
            }

            $uniqueId = uniqid();
            $fileName = "{$prefijo}_{$uniqueId}.jpg";
            $filePath = "{$carpeta}/{$fileName}";

            Storage::disk('custom_public')->put($filePath, $archivo);

            return $filePath;
        } catch (\Exception $e) {
            Log::error("Error al procesar imagen: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar PDF base64 y guardarlo
     */
    private function procesarPDFBase64($base64Data, $carpeta, $prefijo)
    {
        if (!$base64Data || !str_contains($base64Data, ',')) {
            return null;
        }

        try {
            $partes = explode(',', $base64Data);
            if (count($partes) !== 2) {
                return null;
            }

            $archivo = base64_decode($partes[1]);
            if ($archivo === false) {
                return null;
            }

            $uniqueId = uniqid();
            $fileName = "{$prefijo}_{$uniqueId}.pdf";
            $filePath = "{$carpeta}/{$fileName}";

            Storage::disk('custom_public')->put($filePath, $archivo);

            return $filePath;
        } catch (\Exception $e) {
            Log::error("Error al procesar PDF: " . $e->getMessage());
            return null;
        }
    }
    // Agregar este método al MotorizadoController
    public function getEntregaCalendarios($motorizadoId)
    {
        try {
            $entregas = EntregaCalendario::where('reparto_registro_id', $motorizadoId)
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc')
                ->get();

            return response()->json($entregas);
        } catch (\Exception $e) {
            Log::error('Error al obtener calendario de entregas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el calendario de entregas'
            ], 500);
        }
    }


}