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

        // Validar la estructura de la solicitud
        $validator = Validator::make($request->all(), [
            'datosBasicos' => 'required|array',
            'datosPersonales' => 'required|array',
            'cuentaBancaria' => 'required|array',
            'vehiculo' => 'required|array',
        ]);

        if ($validator->fails()) {
            Log::error('Error de validación en estructura de registro completo', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Extraer los datos de la solicitud
        $datosBasicos = $request->input('datosBasicos');
        $datosPersonales = $request->input('datosPersonales');
        $cuentaBancaria = $request->input('cuentaBancaria');
        $vehiculo = $request->input('vehiculo');

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


            // PASO 8: Crear registro de vehículo
            $almacenarImagen = function ($base64Data, $carpeta) {
                if (!$base64Data)
                    return null;

                $imagen = base64_decode(explode(',', $base64Data)[1]);
                $fileName = time() . '_' . Str::random(10) . '.jpg';

                $tempFile = tempnam(sys_get_temp_dir(), 'img');
                file_put_contents($tempFile, $imagen);

                $ruta = Storage::disk('custom_public')->putFileAs($carpeta, $tempFile, $fileName);
                unlink($tempFile);

                return $ruta;
            };

            RegistroVehiculo::create([
                'reparto_registro_id' => $registro->id,
                'placa' => $vehiculo['placa'],
                'licencia_conducir' => $vehiculo['licenciaConducir'],
                'seguro' => $vehiculo['seguro'],
                'tarjeta_propiedad' => $vehiculo['tarjetaPropiedad'],
                'imagen_placa' => isset($vehiculo['placa_imagen']) ? $almacenarImagen($vehiculo['placa_imagen'], 'placas') : null,
                'imagen_licencia' => isset($vehiculo['licenciaConducir_imagen']) ? $almacenarImagen($vehiculo['licenciaConducir_imagen'], 'licencias') : null,
                'imagen_seguro' => isset($vehiculo['seguro_imagen']) ? $almacenarImagen($vehiculo['seguro_imagen'], 'seguros') : null,
                'imagen_tarjeta_propiedad' => isset($vehiculo['tarjetaPropiedad_imagen']) ? $almacenarImagen($vehiculo['tarjetaPropiedad_imagen'], 'tarjetas_propiedad') : null
            ]);

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
