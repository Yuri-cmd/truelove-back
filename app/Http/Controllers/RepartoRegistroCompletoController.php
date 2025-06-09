<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use App\Models\DatosPersonalesReparto;
use App\Models\CuentaBancariaReparto;
use App\Models\RegistroVehiculo;
use App\Models\BusinessRegistration;
use App\Models\UbigeoInei;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepartoRegistroCompletoController extends Controller
{
    public function registroCompleto(Request $request)
    {
        Log::info('Iniciando registro completo de repartidor', $request->all());

        // // Validar la estructura de la solicitud
        // $validator = Validator::make($request->all(), [
        //     'datosBasicos' => 'required|array',
        //     'datosPersonales' => 'required|array',
        //     'cuentaBancaria' => 'required|array',
        //     'vehiculo' => 'required|array',
        // ]);
        $rules = [
            'datosBasicos' => 'required|array',
            'datosPersonales' => 'required|array',
            'cuentaBancaria' => 'required|array',
        ];

        // Solo requerir vehículo si no es bicicleta o moto eléctrica
        $datosBasicos = $request->input('datosBasicos', []);
        $vehiculo = $datosBasicos['vehiculo'] ?? '';

        if (!in_array($vehiculo, ['BICICLETA', 'MOTO ELECTRICA'])) {
            $rules['vehiculo'] = 'required|array';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::error('Error de validación en estructura de registro completo', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Extraer los datos de la solicitud
        $datosBasicos = $request->input('datosBasicos');
        $datosPersonales = $request->input('datosPersonales');
        $cuentaBancaria = $request->input('cuentaBancaria');
        $vehiculo = $request->input('vehiculo');

        // Log detallado de los datos recibidos
        Log::info('Datos extraídos del request', [
            'datosBasicos_keys' => array_keys($datosBasicos),
            'datosPersonales_keys' => array_keys($datosPersonales),
            'cuentaBancaria_keys' => array_keys($cuentaBancaria),
            // 'vehiculo_keys' => array_keys($vehiculo),
            'vehiculo_keys' => $vehiculo ? array_keys($vehiculo) : [],
            'vehiculo_imagenes' => [
                'placa_imagen' => isset($vehiculo['placa_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'licenciaConducir_imagen' => isset($vehiculo['licenciaConducir_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'seguro_imagen' => isset($vehiculo['seguro_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'tarjetaPropiedad_imagen' => isset($vehiculo['tarjetaPropiedad_imagen']) ? 'PRESENTE' : 'AUSENTE'
            ]
        ]);
        // Iniciar transacción
        DB::beginTransaction();

        try {
            // PASO 1: Verificar si existen registros duplicados
            $existingEmail = RepartoRegistro::where('email', $datosBasicos['email'])->first();
            $existingDocument = RepartoRegistro::where('nro_documento', $datosBasicos['nro_documento'])->first();

            if ($existingEmail || $existingDocument) {
                DB::rollBack();
                return response()->json([
                    'error' => 'registro_duplicado',
                    'message' => 'Ya existe un registro con este email o documento'
                ], 422);
            }

            // PASO 2: Verificar duplicados en BusinessRegistration
            $existingBusiness = BusinessRegistration::where('documentNumber', $datosBasicos['nro_documento'])
                ->orWhere('email', $datosBasicos['email'])
                ->first();

            if ($existingBusiness) {
                DB::rollBack();
                $message = $existingBusiness->documentNumber === $datosBasicos['nro_documento']
                    ? 'Este número de documento ya está registrado como socio comercial'
                    : 'Este correo electrónico ya está registrado como socio comercial';
                return response()->json(['errors' => ['duplicate' => [$message]]], 422);
            }

            // PASO 3: Crear registro básico
            $registro = RepartoRegistro::create([
                'departamento' => $datosBasicos['departamento'],
                'vehiculo' => $datosBasicos['vehiculo'],
                'tipo_documento' => $datosBasicos['tipo_documento'],
                'nro_documento' => $datosBasicos['nro_documento'],
                'nombres' => $datosBasicos['nombres'],
                'apellidos' => $datosBasicos['apellidos'],
                'celular' => $datosBasicos['celular'],
                'email' => $datosBasicos['email'],
                'mayor_edad' => $datosBasicos['mayor_edad'] === 'si' || $datosBasicos['mayor_edad'] === true,
                'acepta_politica' => $datosBasicos['aceptaPolitica'] || false,
            ]);

            // PASO 4: Procesar documentos de identidad si existen
            if (isset($datosBasicos['documentoImagenFrente'])) {
                $imagen = base64_decode(explode(',', $datosBasicos['documentoImagenFrente'])[1]);
                $fileName = "documento_motorizado_frente_" . uniqid() . '.jpg';

                $tempFile = tempnam(sys_get_temp_dir(), 'doc');
                file_put_contents($tempFile, $imagen);

                $imgPath = Storage::disk('custom_public')->putFileAs('documento-motorizado', $tempFile, $fileName);
                $registro->update(["documento_imagen_frente" => $imgPath]);
                unlink($tempFile);
            }

            if (isset($datosBasicos['documentoImagenReverso'])) {
                $imagen = base64_decode(explode(',', $datosBasicos['documentoImagenReverso'])[1]);
                $fileName = "documento_motorizado_reverso_" . uniqid() . '.jpg';

                $tempFile = tempnam(sys_get_temp_dir(), 'doc');
                file_put_contents($tempFile, $imagen);

                $imgPath = Storage::disk('custom_public')->putFileAs('documento-motorizado', $tempFile, $fileName);
                $registro->update(["documento_imagen_reverso" => $imgPath]);
                unlink($tempFile);
            }

            // PASO 5: Procesar documentos adicionales
            if (isset($datosBasicos['documentosAdicionales']) && is_array($datosBasicos['documentosAdicionales'])) {
                $documentosGuardados = [];

                foreach ($datosBasicos['documentosAdicionales'] as $documento) {
                    if (isset($documento['archivo']) && isset($documento['nombre']) && isset($documento['categoria'])) {
                        $archivo = base64_decode(explode(',', $documento['archivo'])[1]);
                        $fileName = "documento_adicional_" . uniqid() . '.pdf';

                        $tempFile = tempnam(sys_get_temp_dir(), 'doc_add');
                        file_put_contents($tempFile, $archivo);

                        $filePath = Storage::disk('custom_public')->putFileAs('documentos-adicionales', $tempFile, $fileName);

                        $documentosGuardados[] = [
                            'ruta' => $filePath,
                            'tipo' => 'application/pdf',
                            'categoria' => $documento['categoria'],
                            'fecha_carga' => now()->toDateTimeString()
                        ];

                        unlink($tempFile);
                    }
                }

                if (!empty($documentosGuardados)) {
                    $registro->update(['documentos_adicionales' => $documentosGuardados]);
                }
            }

            // PASO 6: Crear datos personales
            $urlSelfie = null;
            if (isset($datosPersonales['selfie'])) {
                $imagen = base64_decode(explode(',', $datosPersonales['selfie'])[1]);
                $fileName = "selfie_" . uniqid() . '.jpg';

                $tempFile = tempnam(sys_get_temp_dir(), 'selfie');
                file_put_contents($tempFile, $imagen);

                $urlSelfie = Storage::disk('custom_public')->putFileAs('selfies', $tempFile, $fileName);
                unlink($tempFile);
            }

            DatosPersonalesReparto::create([
                'reparto_registro_id' => $registro->id,
                'fecha_nacimiento' => $datosPersonales['fecha_nacimiento'],
                'genero' => $datosPersonales['genero'],
                'url_selfie' => $urlSelfie,
                'ubigeo_id' => $datosPersonales['ubigeo_id']
            ]);
            // PASO 7: Crear cuenta bancaria
            $urlImagenCuenta = null;
            if (isset($cuentaBancaria['imagen_cuenta'])) {
                // Obtener el tipo MIME del base64
                $mime = explode(';', explode(':', $cuentaBancaria['imagen_cuenta'])[1])[0];

                // Determinar la extensión basada en el tipo MIME
                $extension = match ($mime) {
                    'application/pdf' => '.pdf',
                    'image/jpeg' => '.jpg',
                    'image/png' => '.png',
                    default => '.jpg'
                };

                $imagen = base64_decode(explode(',', $cuentaBancaria['imagen_cuenta'])[1]);
                $fileName = "cuenta_bancaria_" . uniqid() . $extension;

                $tempFile = tempnam(sys_get_temp_dir(), 'cuenta');
                file_put_contents($tempFile, $imagen);

                // Usar Storage::disk en lugar de move_uploaded_file
                $urlImagenCuenta = Storage::disk('custom_public')->putFileAs(
                    'cuentas_bancarias',
                    $tempFile,
                    $fileName
                );

                unlink($tempFile);
            }


            CuentaBancariaReparto::create([
                'reparto_registro_id' => $registro->id,
                'titular' => $cuentaBancaria['titular'],
                'dni' => $cuentaBancaria['dni'],
                'banco_id' => $cuentaBancaria['banco_id'],
                'tipo_cuenta_id' => $cuentaBancaria['tipo_cuenta_id'],
                'numero_cuenta' => $cuentaBancaria['numero_cuenta'],
                'url_imagen_cuenta' => $urlImagenCuenta // Ahora guardará la ruta relativa
            ]);


            // PASO 8: Crear registro de vehículo (solo si no es bicicleta o moto eléctrica)
            if (!in_array($datosBasicos['vehiculo'], ['BICICLETA', 'MOTO ELECTRICA'])) {
                // Solo crear registro de vehículo si no es bicicleta o moto eléctrica
                $vehiculo = $request->input('vehiculo');

                $almacenarImagen = function ($base64Data, $carpeta, $tipoImagen = 'imagen') use ($registro) {
                    // Log inicial para verificar si se recibe la imagen
                    Log::info("Procesando imagen de vehículo", [
                        'registro_id' => $registro->id,
                        'tipo_imagen' => $tipoImagen,
                        'carpeta' => $carpeta,
                        'tiene_datos' => !empty($base64Data),
                        'longitud_datos' => $base64Data ? strlen($base64Data) : 0
                    ]);

                    if (!$base64Data) {
                        Log::warning("No se recibieron datos de imagen", [
                            'registro_id' => $registro->id,
                            'tipo_imagen' => $tipoImagen
                        ]);
                        return null;
                    }

                    try {
                        // Verificar formato base64
                        if (!str_contains($base64Data, ',')) {
                            Log::error("Formato base64 inválido - no contiene coma separadora", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'primeros_50_chars' => substr($base64Data, 0, 50)
                            ]);
                            return null;
                        }

                        $partes = explode(',', $base64Data);
                        if (count($partes) !== 2) {
                            Log::error("Formato base64 inválido - estructura incorrecta", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'partes_encontradas' => count($partes)
                            ]);
                            return null;
                        }

                        // Decodificar imagen
                        $imagen = base64_decode($partes[1]);
                        if ($imagen === false) {
                            Log::error("Error al decodificar base64", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen
                            ]);
                            return null;
                        }

                        $tamanoImagen = strlen($imagen);
                        Log::info("Imagen decodificada exitosamente", [
                            'registro_id' => $registro->id,
                            'tipo_imagen' => $tipoImagen,
                            'tamano_bytes' => $tamanoImagen
                        ]);

                        // Verificar tamaño mínimo de imagen (1KB)
                        if ($tamanoImagen < 1024) {
                            Log::warning("Imagen muy pequeña, posible corrupción", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'tamano_bytes' => $tamanoImagen
                            ]);
                        }

                        $fileName = time() . '_' . Str::random(10) . '.jpg';

                        // Crear archivo temporal
                        $tempFile = tempnam(sys_get_temp_dir(), 'img_vehiculo');
                        if ($tempFile === false) {
                            Log::error("No se pudo crear archivo temporal", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen
                            ]);
                            return null;
                        }

                        // Escribir datos al archivo temporal
                        $bytesEscritos = file_put_contents($tempFile, $imagen);
                        if ($bytesEscritos === false) {
                            Log::error("Error al escribir archivo temporal", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'archivo_temporal' => $tempFile
                            ]);
                            return null;
                        }

                        Log::info("Archivo temporal creado", [
                            'registro_id' => $registro->id,
                            'tipo_imagen' => $tipoImagen,
                            'archivo_temporal' => $tempFile,
                            'bytes_escritos' => $bytesEscritos
                        ]);

                        // Verificar que el archivo temporal existe y tiene contenido
                        if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                            Log::error("Archivo temporal inválido", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'existe' => file_exists($tempFile),
                                'tamano' => file_exists($tempFile) ? filesize($tempFile) : 'N/A'
                            ]);
                            return null;
                        }

                        // Intentar guardar en storage
                        $ruta = Storage::disk('custom_public')->putFileAs($carpeta, $tempFile, $fileName);

                        if ($ruta === false) {
                            Log::error("Error al guardar en storage", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'carpeta' => $carpeta,
                                'nombre_archivo' => $fileName
                            ]);
                            unlink($tempFile);
                            return null;
                        }

                        // Verificar que el archivo se guardó correctamente
                        if (!Storage::disk('custom_public')->exists($ruta)) {
                            Log::error("Archivo no existe después de guardarlo", [
                                'registro_id' => $registro->id,
                                'tipo_imagen' => $tipoImagen,
                                'ruta_esperada' => $ruta
                            ]);
                            unlink($tempFile);
                            return null;
                        }

                        $tamanoFinal = Storage::disk('custom_public')->size($ruta);
                        Log::info("Imagen guardada exitosamente", [
                            'registro_id' => $registro->id,
                            'tipo_imagen' => $tipoImagen,
                            'ruta_final' => $ruta,
                            'tamano_final' => $tamanoFinal
                        ]);

                        // Limpiar archivo temporal
                        unlink($tempFile);

                        return $ruta;

                    } catch (\Exception $e) {
                        Log::error("Excepción al procesar imagen de vehículo", [
                            'registro_id' => $registro->id,
                            'tipo_imagen' => $tipoImagen,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        // Limpiar archivo temporal si existe
                        if (isset($tempFile) && file_exists($tempFile)) {
                            unlink($tempFile);
                        }

                        return null;
                    }
                };

                // ✅ AQUÍ VA EL SEGUNDO BLOQUE DE LOGGING
// Log de datos recibidos para vehículo
                Log::info("Datos de vehículo recibidos", [
                    'registro_id' => $registro->id,
                    'placa' => $vehiculo['placa'] ?? 'NO_DEFINIDA',
                    'tiene_imagen_placa' => isset($vehiculo['placa_imagen']),
                    'tiene_imagen_licencia' => isset($vehiculo['licenciaConducir_imagen']),
                    'tiene_imagen_seguro' => isset($vehiculo['seguro_imagen']),
                    'tiene_imagen_tarjeta' => isset($vehiculo['tarjetaPropiedad_imagen']),
                    'keys_vehiculo' => array_keys($vehiculo)
                ]);

                // Procesar cada imagen individualmente con logging
                $imagenPlaca = null;
                $imagenLicencia = null;
                $imagenSeguro = null;
                $imagenTarjeta = null;

                if (isset($vehiculo['placa_imagen'])) {
                    Log::info("Procesando imagen de placa", ['registro_id' => $registro->id]);
                    $imagenPlaca = $almacenarImagen($vehiculo['placa_imagen'], 'placas', 'placa');
                    Log::info("Resultado imagen placa", [
                        'registro_id' => $registro->id,
                        'ruta' => $imagenPlaca,
                        'exitoso' => $imagenPlaca !== null
                    ]);
                }

                if (isset($vehiculo['licenciaConducir_imagen'])) {
                    Log::info("Procesando imagen de licencia", ['registro_id' => $registro->id]);
                    $imagenLicencia = $almacenarImagen($vehiculo['licenciaConducir_imagen'], 'licencias', 'licencia');
                    Log::info("Resultado imagen licencia", [
                        'registro_id' => $registro->id,
                        'ruta' => $imagenLicencia,
                        'exitoso' => $imagenLicencia !== null
                    ]);
                }

                if (isset($vehiculo['seguro_imagen'])) {
                    Log::info("Procesando imagen de seguro", ['registro_id' => $registro->id]);
                    $imagenSeguro = $almacenarImagen($vehiculo['seguro_imagen'], 'seguros', 'seguro');
                    Log::info("Resultado imagen seguro", [
                        'registro_id' => $registro->id,
                        'ruta' => $imagenSeguro,
                        'exitoso' => $imagenSeguro !== null
                    ]);
                }

                if (isset($vehiculo['tarjetaPropiedad_imagen'])) {
                    Log::info("Procesando imagen de tarjeta propiedad", ['registro_id' => $registro->id]);
                    $imagenTarjeta = $almacenarImagen($vehiculo['tarjetaPropiedad_imagen'], 'tarjetas_propiedad', 'tarjeta_propiedad');
                    Log::info("Resultado imagen tarjeta", [
                        'registro_id' => $registro->id,
                        'ruta' => $imagenTarjeta,
                        'exitoso' => $imagenTarjeta !== null
                    ]);
                }

                // Crear registro con logging de los valores finales
                $datosVehiculo = [
                    'reparto_registro_id' => $registro->id,
                    'placa' => $vehiculo['placa'],
                    'licencia_conducir' => $vehiculo['licenciaConducir'],
                    'seguro' => $vehiculo['seguro'],
                    'tarjeta_propiedad' => $vehiculo['tarjetaPropiedad'],
                    'imagen_placa' => $imagenPlaca,
                    'imagen_licencia' => $imagenLicencia,
                    'imagen_seguro' => $imagenSeguro,
                    'imagen_tarjeta_propiedad' => $imagenTarjeta
                ];

                Log::info("Creando registro de vehículo", [
                    'registro_id' => $registro->id,
                    'datos_vehiculo' => $datosVehiculo
                ]);

                $vehiculoCreado = RegistroVehiculo::create($datosVehiculo);

                Log::info("Registro de vehículo creado", [
                    'registro_id' => $registro->id,
                    'vehiculo_id' => $vehiculoCreado->id,
                    'imagenes_guardadas' => [
                        'placa' => $imagenPlaca !== null,
                        'licencia' => $imagenLicencia !== null,
                        'seguro' => $imagenSeguro !== null,
                        'tarjeta' => $imagenTarjeta !== null
                    ]
                ]);
            } else {
                Log::info("Vehículo sin documentos motorizados, saltando registro de vehículo", [
                    'registro_id' => $registro->id,
                    'tipo_vehiculo' => $datosBasicos['vehiculo']
                ]);
            }

            // Confirmar transacción
            DB::commit();

            return response()->json([
                'message' => 'Registro completado exitosamente',
                'data' => [
                    'id' => $registro->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en registro completo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'error_interno',
                'message' => 'Ocurrió un error al procesar el registro. Por favor, intente nuevamente.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
