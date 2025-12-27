<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use App\Models\DatosPersonalesReparto;
use App\Models\CuentaBancariaReparto;
use App\Models\RegistroVehiculo;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class RepartoRegistroCompletoController extends Controller
{
    /**
     * Procesa el registro completo de un repartidor
     */
    public function registroCompleto(Request $request)
    {
        // Log::info('Iniciando registro completo de repartidor', $request->all());

        // Validar la estructura de la solicitud
        $validator = $this->validarSolicitud($request);
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
        $this->logDatosRecibidos($datosBasicos, $datosPersonales, $cuentaBancaria, $vehiculo);

        // Iniciar transacción
        DB::beginTransaction();

        try {
            // Verificar duplicados
            $resultadoVerificacion = $this->verificarDuplicados($datosBasicos);
            if ($resultadoVerificacion !== true) {
                DB::rollBack();
                return $resultadoVerificacion;
            }

            // Crear registro básico
            $registro = $this->crearRegistroBasico($datosBasicos);

            // Procesar documentos de identidad
            $this->procesarDocumentosIdentidad($registro, $datosBasicos);

            // Procesar documentos adicionales
            $this->procesarDocumentosAdicionales($registro, $datosBasicos);

            // Crear datos personales
            $this->crearDatosPersonales($registro, $datosPersonales);

            // Crear cuenta bancaria
            $this->crearCuentaBancaria($registro, $cuentaBancaria);

            // Procesar vehículo si corresponde
            if (!in_array($datosBasicos['vehiculo'], ['BICICLETA', 'MOTO ELECTRICA'])) {
                $this->procesarVehiculo($registro, $vehiculo);
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

    /**
     * Valida la estructura de la solicitud
     */
    private function validarSolicitud(Request $request)
    {
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

        return Validator::make($request->all(), $rules);
    }

    /**
     * Registra los datos recibidos en el log
     */
    private function logDatosRecibidos($datosBasicos, $datosPersonales, $cuentaBancaria, $vehiculo)
    {
        Log::info('Datos extraídos del request', [
            'datosBasicos_keys' => array_keys($datosBasicos),
            'datosPersonales_keys' => array_keys($datosPersonales),
            'cuentaBancaria_keys' => array_keys($cuentaBancaria),
            'vehiculo_keys' => $vehiculo ? array_keys($vehiculo) : [],
            'vehiculo_imagenes' => [
                'placa_imagen' => isset($vehiculo['placa_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'licenciaConducir_imagen' => isset($vehiculo['licenciaConducir_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'seguro_imagen' => isset($vehiculo['seguro_imagen']) ? 'PRESENTE' : 'AUSENTE',
                'tarjetaPropiedad_imagen' => isset($vehiculo['tarjetaPropiedad_imagen']) ? 'PRESENTE' : 'AUSENTE'
            ]
        ]);
    }

    /**
     * Verifica si existen registros duplicados
     * @return true|Response true si no hay duplicados, Response con error si hay duplicados
     */
    private function verificarDuplicados($datosBasicos)
    {
        // Verificar duplicados en RepartoRegistro
        $existingEmail = RepartoRegistro::where('email', $datosBasicos['email'])->first();
        $existingDocument = RepartoRegistro::where('nro_documento', $datosBasicos['nro_documento'])->first();

        if ($existingEmail || $existingDocument) {
            return response()->json([
                'error' => 'registro_duplicado',
                'message' => 'Ya existe un registro con este email o documento'
            ], 422);
        }

        // Verificar duplicados en BusinessRegistration
        $existingBusiness = BusinessRegistration::where('documentNumber', $datosBasicos['nro_documento'])
            ->orWhere('email', $datosBasicos['email'])
            ->first();

        if ($existingBusiness) {
            $message = $existingBusiness->documentNumber === $datosBasicos['nro_documento']
                ? 'Este número de documento ya está registrado como socio comercial'
                : 'Este correo electrónico ya está registrado como socio comercial';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }

        return true;
    }

    /**
     * Crea el registro básico del repartidor
     */
    private function crearRegistroBasico($datosBasicos)
    {
        return RepartoRegistro::create([
            'departamento' => $datosBasicos['departamento'],
            'vehiculo' => $datosBasicos['vehiculo'],
            'tipo_documento' => $datosBasicos['tipo_documento'],
            'nro_documento' => $datosBasicos['nro_documento'],
            'nombres' => $datosBasicos['nombres'],
            'apellidos' => $datosBasicos['apellidos'],
            'celular' => $datosBasicos['celular'],
            'email' => $datosBasicos['email'],
            'mayor_edad' => $datosBasicos['mayor_edad'] === 'si' || $datosBasicos['mayor_edad'] === true,
            'acepta_politica' => $datosBasicos['aceptaPolitica'] ?? false,
        ]);
    }

    /**
     * Procesa los documentos de identidad
     */
    private function procesarDocumentosIdentidad($registro, $datosBasicos)
    {
        if (isset($datosBasicos['documentoImagenFrente'])) {
            $imgPath = $this->procesarArchivoBase64(
                $datosBasicos['documentoImagenFrente'],
                'documento-motorizado',
                'documento_motorizado_frente',
                'image/jpeg',
                '.jpg',
                false // No forzar extensión para imágenes
            );
            if ($imgPath) {
                $registro->update(["documento_imagen_frente" => $imgPath]);
            }
        }

        if (isset($datosBasicos['documentoImagenReverso'])) {
            $imgPath = $this->procesarArchivoBase64(
                $datosBasicos['documentoImagenReverso'],
                'documento-motorizado',
                'documento_motorizado_reverso',
                'image/jpeg',
                '.jpg',
                false // No forzar extensión para imágenes
            );
            if ($imgPath) {
                $registro->update(["documento_imagen_reverso" => $imgPath]);
            }
        }
    }

    /**
     * Procesa los documentos adicionales
     */
    private function procesarDocumentosAdicionales($registro, $datosBasicos)
    {
        // ✅ LOG: Verificar si existen documentos adicionales
        if (!isset($datosBasicos['documentosAdicionales'])) {
            Log::info("No se recibieron documentos adicionales", [
                'registro_id' => $registro->id
            ]);
            return;
        }

        if (!is_array($datosBasicos['documentosAdicionales'])) {
            Log::warning("documentosAdicionales no es un array", [
                'registro_id' => $registro->id,
                'tipo' => gettype($datosBasicos['documentosAdicionales'])
            ]);
            return;
        }

        Log::info("Iniciando procesamiento de documentos adicionales", [
            'registro_id' => $registro->id,
            'cantidad_documentos' => count($datosBasicos['documentosAdicionales'])
        ]);

        $documentosGuardados = [];
        $documentosRechazados = [];

        foreach ($datosBasicos['documentosAdicionales'] as $index => $documento) {
            // ✅ LOG: Validar cada documento
            if (!isset($documento['archivo'])) {
                Log::warning("Documento adicional sin archivo", [
                    'registro_id' => $registro->id,
                    'index' => $index,
                    'documento_keys' => array_keys($documento)
                ]);
                $documentosRechazados[] = "Documento #{$index}: sin archivo";
                continue;
            }

            if (!isset($documento['nombre'])) {
                Log::warning("Documento adicional sin nombre", [
                    'registro_id' => $registro->id,
                    'index' => $index
                ]);
                $documentosRechazados[] = "Documento #{$index}: sin nombre";
                continue;
            }

            if (!isset($documento['categoria'])) {
                Log::warning("Documento adicional sin categoría", [
                    'registro_id' => $registro->id,
                    'index' => $index
                ]);
                $documentosRechazados[] = "Documento #{$index}: sin categoría";
                continue;
            }

            // Verificar que el archivo no esté vacío o sea null
            if (empty($documento['archivo']) || $documento['archivo'] === 'null') {
                Log::warning("Documento adicional con archivo vacío o null", [
                    'registro_id' => $registro->id,
                    'nombre' => $documento['nombre'],
                    'categoria' => $documento['categoria']
                ]);
                $documentosRechazados[] = "Documento {$documento['nombre']}: archivo vacío";
                continue;
            }

            // ✅ LOG: Intentar procesar
            Log::info("Procesando documento adicional", [
                'registro_id' => $registro->id,
                'nombre' => $documento['nombre'],
                'categoria' => $documento['categoria'],
                'tamano_base64' => strlen($documento['archivo'])
            ]);

            // Procesar el documento adicional - FORZAR PDF para documentos adicionales
            $filePath = $this->procesarArchivoBase64(
                $documento['archivo'],
                'documentos-adicionales',
                'documento_adicional',
                'application/pdf',
                '.pdf',
                true // Forzar extensión .pdf
            );

            if ($filePath) {
                // Detectar el tipo MIME real para el log
                $base64Parts = explode(',', $documento['archivo']);
                $mimeType = 'application/pdf'; // Valor por defecto

                if (count($base64Parts) > 1 && strpos($base64Parts[0], ':') !== false) {
                    $mimeHeader = explode(':', $base64Parts[0])[1];
                    if (strpos($mimeHeader, ';') !== false) {
                        $mimeType = explode(';', $mimeHeader)[0];
                    }
                }

                $documentosGuardados[] = [
                    'ruta' => $filePath,
                    'tipo' => $mimeType,
                    'categoria' => $documento['categoria'],
                    'fecha_carga' => now()->toDateTimeString()
                ];
                
                Log::info("Documento adicional guardado exitosamente", [
                    'registro_id' => $registro->id,
                    'categoria' => $documento['categoria'],
                    'nombre' => $documento['nombre'],
                    'ruta' => $filePath
                ]);
            } else {
                Log::error("Error al guardar documento adicional - procesarArchivoBase64 retornó null", [
                    'registro_id' => $registro->id,
                    'categoria' => $documento['categoria'],
                    'nombre' => $documento['nombre']
                ]);
                $documentosRechazados[] = "Documento {$documento['nombre']}: error al guardar archivo";
            }
        }

        // ✅ LOG: Resumen final
        Log::info("Resumen de procesamiento de documentos adicionales", [
            'registro_id' => $registro->id,
            'documentos_guardados' => count($documentosGuardados),
            'documentos_rechazados' => count($documentosRechazados),
            'rechazos' => $documentosRechazados
        ]);

        if (!empty($documentosGuardados)) {
            $registro->update(['documentos_adicionales' => $documentosGuardados]);
            Log::info("Campo documentos_adicionales actualizado en BD", [
                'registro_id' => $registro->id,
                'cantidad' => count($documentosGuardados)
            ]);
        } else {
            Log::warning("No se guardaron documentos adicionales", [
                'registro_id' => $registro->id,
                'motivo' => empty($documentosRechazados) ? 'No se procesaron documentos' : 'Todos los documentos fueron rechazados'
            ]);
        }
    }

    /**
     * Crea los datos personales del repartidor
     */
    private function crearDatosPersonales($registro, $datosPersonales)
    {
        $urlSelfie = null;
        if (isset($datosPersonales['selfie'])) {
            $urlSelfie = $this->procesarArchivoBase64(
                $datosPersonales['selfie'],
                'selfies',
                'selfie',
                'image/jpeg',
                '.jpg',
                false // No forzar extensión para imágenes
            );
        }

        DatosPersonalesReparto::create([
            'reparto_registro_id' => $registro->id,
            'fecha_nacimiento' => $datosPersonales['fecha_nacimiento'],
            'genero' => $datosPersonales['genero'],
            'url_selfie' => $urlSelfie,
            'ubigeo_id' => $datosPersonales['ubigeo_id']
        ]);
    }
/**
 * Detecta si un string base64 es una imagen o un PDF
 * 
 * @param string $base64Data Datos base64 del archivo
 * @return string 'image' o 'pdf'
 */
private function detectarTipoArchivo($base64Data)
{
    // Verificar el encabezado base64
    if (strpos($base64Data, 'data:image/') !== false) {
        return 'image';
    }
    
    if (strpos($base64Data, 'data:application/pdf') !== false) {
        return 'pdf';
    }
    
    // Si no hay un encabezado claro, verificar los primeros bytes del contenido
    $partes = explode(',', $base64Data);
    if (count($partes) === 2) {
        $contenido = base64_decode($partes[1]);
        
        // Verificar si es un PDF (comienza con %PDF-)
        if (substr($contenido, 0, 5) === '%PDF-') {
            return 'pdf';
        }
        
        // Verificar si es una imagen JPEG (comienza con JFIF o Exif)
        if (strpos($contenido, 'JFIF') !== false || strpos($contenido, 'Exif') !== false) {
            return 'image';
        }
        
        // Verificar si es una imagen PNG (comienza con PNG)
        if (strpos($contenido, 'PNG') !== false) {
            return 'image';
        }
    }
    
    // Por defecto, asumir que es un PDF
    return 'pdf';
}

/**
 * Crea la cuenta bancaria del repartidor
 */
private function crearCuentaBancaria($registro, $cuentaBancaria)
{
    $urlImagenCuenta = null;
    if (isset($cuentaBancaria['imagen_cuenta'])) {
        // Detectar si es una imagen o un PDF
        $tipoArchivo = $this->detectarTipoArchivo($cuentaBancaria['imagen_cuenta']);
        
        if ($tipoArchivo === 'image') {
            // Procesar como imagen
            $urlImagenCuenta = $this->procesarArchivoBase64(
                $cuentaBancaria['imagen_cuenta'],
                'cuentas_bancarias',
                'cuenta_bancaria',
                'image/jpeg',
                '.jpg',
                false // No forzar extensión para imágenes
            );
        } else {
            // Procesar como PDF
            $urlImagenCuenta = $this->procesarArchivoBase64(
                $cuentaBancaria['imagen_cuenta'],
                'cuentas_bancarias',
                'cuenta_bancaria',
                'application/pdf',
                '.pdf',
                true // Forzar extensión .pdf para documentos
            );
        }
        
        Log::info("Documento de cuenta bancaria procesado", [
            'tipo_detectado' => $tipoArchivo,
            'ruta_guardada' => $urlImagenCuenta
        ]);
    }

    CuentaBancariaReparto::create([
        'reparto_registro_id' => $registro->id,
        'titular' => $cuentaBancaria['titular'],
        'dni' => $cuentaBancaria['dni'],
        'banco_id' => $cuentaBancaria['banco_id'],
        'tipo_cuenta_id' => $cuentaBancaria['tipo_cuenta_id'],
        'numero_cuenta' => $cuentaBancaria['numero_cuenta'],
        'url_imagen_cuenta' => $urlImagenCuenta
    ]);
}

    /**
     * Procesa los datos del vehículo
     */
    private function procesarVehiculo($registro, $vehiculo)
    {
        if (!$vehiculo) {
            return;
        }

        Log::info("Datos de vehículo recibidos", [
            'registro_id' => $registro->id,
            'placa' => $vehiculo['placa'] ?? 'NO_DEFINIDA',
            'tiene_imagen_placa' => isset($vehiculo['placa_imagen']),
            'tiene_imagen_licencia' => isset($vehiculo['licenciaConducir_imagen']),
            'tiene_imagen_seguro' => isset($vehiculo['seguro_imagen']),
            'tiene_imagen_tarjeta' => isset($vehiculo['tarjetaPropiedad_imagen']),
            'keys_vehiculo' => array_keys($vehiculo)
        ]);

        // Procesar cada imagen individualmente
        $imagenPlaca = isset($vehiculo['placa_imagen']) 
            ? $this->procesarArchivoBase64(
                $vehiculo['placa_imagen'], 
                'placas', 
                'placa', 
                'image/jpeg', 
                '.jpg',
                false // No forzar extensión para imágenes
            )
            : null;
            
        $imagenLicencia = isset($vehiculo['licenciaConducir_imagen'])
            ? $this->procesarArchivoBase64(
                $vehiculo['licenciaConducir_imagen'], 
                'licencias', 
                'licencia', 
                'image/jpeg', 
                '.jpg',
                false // No forzar extensión para imágenes
            )
            : null;
            
        $imagenSeguro = isset($vehiculo['seguro_imagen'])
            ? $this->procesarArchivoBase64(
                $vehiculo['seguro_imagen'], 
                'seguros', 
                'seguro', 
                'image/jpeg', 
                '.jpg',
                false // No forzar extensión para imágenes
            )
            : null;
            
        $imagenTarjeta = isset($vehiculo['tarjetaPropiedad_imagen'])
            ? $this->procesarArchivoBase64(
                $vehiculo['tarjetaPropiedad_imagen'], 
                'tarjetas_propiedad', 
                'tarjeta_propiedad', 
                'image/jpeg', 
                '.jpg',
                false // No forzar extensión para imágenes
            )
            : null;

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
    }

  /**
 * Procesa un archivo base64 y lo guarda en el almacenamiento
 * 
 * @param string $base64Data Datos base64 del archivo
 * @param string $carpeta Carpeta donde guardar el archivo
 * @param string $prefijo Prefijo para el nombre del archivo
 * @param string $defaultMime Tipo MIME por defecto
 * @param string $defaultExt Extensión por defecto
 * @param bool $forzarExtension Si debe forzar la extensión independientemente del MIME detectado
 * @return string|null Ruta del archivo guardado o null si hay error
 */
private function procesarArchivoBase64(
    $base64Data, 
    $carpeta, 
    $prefijo = 'documento', 
    $defaultMime = 'application/pdf', 
    $defaultExt = '.pdf',
    $forzarExtension = false
) {
    if (!$base64Data) {
        Log::warning("No se recibieron datos para procesar", [
            'carpeta' => $carpeta,
            'prefijo' => $prefijo
        ]);
        return null;
    }

    try {
        // Verificar formato base64
        if (!str_contains($base64Data, ',')) {
            Log::error("Formato base64 inválido - no contiene coma separadora", [
                'prefijo' => $prefijo,
                'primeros_50_chars' => substr($base64Data, 0, 50)
            ]);
            return null;
        }

        $partes = explode(',', $base64Data);
        if (count($partes) !== 2) {
            Log::error("Formato base64 inválido - estructura incorrecta", [
                'prefijo' => $prefijo,
                'partes_encontradas' => count($partes)
            ]);
            return null;
        }

        // Detectar el tipo MIME del base64
        $mimeType = $defaultMime;

        if (strpos($partes[0], ':') !== false) {
            $mimeHeader = explode(':', $partes[0])[1];
            if (strpos($mimeHeader, ';') !== false) {
                $mimeType = explode(';', $mimeHeader)[0];
            }
        }

        // Determinar la extensión basada en el tipo MIME o forzarla
        $extension = $defaultExt;
        
        // Si estamos forzando la extensión, siempre usamos la predeterminada
        if ($forzarExtension) {
            $extension = $defaultExt;
        } else {
            $extension = match ($mimeType) {
                'application/pdf' => '.pdf',
                'image/jpeg' => '.jpg',
                'image/png' => '.png',
                default => $defaultExt
            };
        }

        // Decodificar archivo
        $archivo = base64_decode($partes[1]);
        if ($archivo === false) {
            Log::error("Error al decodificar base64", [
                'prefijo' => $prefijo
            ]);
            return null;
        }

        // Verificar si es un PDF (si estamos forzando extensión .pdf)
        if ($forzarExtension && $extension === '.pdf') {
            // Verificar si los primeros bytes corresponden a un PDF (%PDF-)
            $isPdf = substr($archivo, 0, 5) === '%PDF-';
            if (!$isPdf) {
                Log::warning("El archivo no parece ser un PDF válido, pero se forzará la extensión .pdf", [
                    'prefijo' => $prefijo,
                    'primeros_bytes' => bin2hex(substr($archivo, 0, 10))
                ]);
            }
        }

        // Generar nombre de archivo único con la extensión correcta
        $uniqueId = uniqid();
        $fileName = "{$prefijo}_{$uniqueId}{$extension}";
        
        // Ruta completa dentro de la carpeta
        $filePath = "{$carpeta}/{$fileName}";
        
        // Obtener la ruta física completa
        $fullPath = Storage::disk('custom_public')->path($filePath);
        $dirPath = dirname($fullPath);
        
        // Crear el directorio si no existe
        if (!file_exists($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                Log::error("No se pudo crear el directorio", [
                    'carpeta' => $carpeta,
                    'ruta_completa' => $dirPath
                ]);
                return null;
            }
            Log::info("Directorio creado exitosamente", ['ruta' => $dirPath]);
        }
        
        // Guardar el archivo usando file_put_contents directamente
        $bytesWritten = file_put_contents($fullPath, $archivo);
        
        if ($bytesWritten === false) {
            Log::error("Error al escribir el archivo en disco", [
                'prefijo' => $prefijo,
                'ruta_completa' => $fullPath
            ]);
            return null;
        }
        
        // Verificar que el archivo existe y tiene contenido
        if (!file_exists($fullPath) || filesize($fullPath) === 0) {
            Log::error("El archivo no existe o está vacío después de guardarlo", [
                'prefijo' => $prefijo,
                'ruta_completa' => $fullPath,
                'existe' => file_exists($fullPath),
                'tamano' => file_exists($fullPath) ? filesize($fullPath) : 0
            ]);
            return null;
        }

        Log::info("Archivo guardado exitosamente", [
            'prefijo' => $prefijo,
            'ruta_final' => $filePath,
            'ruta_completa' => $fullPath,
            'mime_detectado' => $mimeType,
            'extension_forzada' => $forzarExtension,
            'extension_final' => $extension,
            'tamano_bytes' => $bytesWritten
        ]);

        return $filePath;

    } catch (\Exception $e) {
        Log::error("Excepción al procesar archivo", [
            'prefijo' => $prefijo,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return null;
    }
}
}