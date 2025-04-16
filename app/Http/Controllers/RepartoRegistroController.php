<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RepartoRegistroController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Datos recibidos en store:', $request->all());
        
        $validator = Validator::make($request->except(['documento_imagen_frente', 'documento_imagen_reverso']), [
            'departamento' => 'required|string',
            'vehiculo' => 'required|string',
            'tipo_documento' => 'required|string',
            'nro_documento' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'nullable|string',
            'celular' => 'required|string',
            'email' => 'required|email',
            'mayor_edad' => 'required|boolean',
            'acepta_politica' => 'required|boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        // PASO 1: Verificar si existe un registro con el mismo documento pero diferente correo
        $existingDocument = RepartoRegistro::where('nro_documento', $request->nro_documento)
            ->where('email', '!=', $request->email)
            ->first();
        
        if ($existingDocument) {
            Log::info('Documento duplicado con correo diferente encontrado:', [
                'nro_documento' => $request->nro_documento,
                'existing_email' => $existingDocument->email,
                'new_email' => $request->email
            ]);
            
            // Verificar si el registro está incompleto
            $completionStatus = $this->checkCompletionStatus($existingDocument);
            
            if (!$completionStatus['isComplete']) {
                // Si el registro está incompleto y el correo es diferente, devolver información para mostrar alerta
                return response()->json([
                    'error' => 'different_email',
                    'original_email' => $existingDocument->email,
                    'message' => 'Ya existe un registro con este documento pero con otro correo electrónico.',
                    'registration_id' => $existingDocument->id
                ], 422);
            } else {
                // Si el registro está completo, devolver error de documento duplicado
                return response()->json([
                    'errors' => ['duplicate' => ['Este número de documento ya está registrado como repartidor']],
                    'error' => 'dni_registered'
                ], 422);
            }
        }
    
        // PASO 2: Verificar si existe un registro con el mismo correo pero diferente documento
        $existingEmail = RepartoRegistro::where('email', $request->email)
            ->where('nro_documento', '!=', $request->nro_documento)
            ->first();
        
        if ($existingEmail) {
            Log::info('Correo duplicado con documento diferente encontrado:', [
                'email' => $request->email,
                'existing_id' => $existingEmail->id
            ]);
            
            return response()->json([
                'errors' => ['duplicate' => ['Este correo electrónico ya está registrado con otro documento.']],
                'error' => 'email_registered'
            ], 422);
        }
    
        // PASO 3: Verificar si existe un registro incompleto con el mismo documento o correo
        $existingReparto = RepartoRegistro::where('nro_documento', $request->nro_documento)
            ->orWhere('email', $request->email)
            ->first();
    
        if ($existingReparto) {
            // Verificar si el registro está incompleto
            $completionStatus = $this->checkCompletionStatus($existingReparto);
        
            if (!$completionStatus['isComplete']) {
                // Si el registro está incompleto, devolver información para continuar
                return response()->json([
                    'status' => 'incomplete',
                    'registration_id' => $existingReparto->id,
                    'current_step' => $completionStatus['nextStep'],
                    'message' => 'Ya tienes un registro en proceso. Puedes continuar desde donde lo dejaste.'
                ], 200);
            }
        
            // Si el registro está completo, devolver error de duplicado
            $message = $existingReparto->nro_documento === $request->nro_documento 
                ? 'Este número de documento ya está registrado como repartidor'
                : 'Este correo electrónico ya está registrado como repartidor';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }
    
        // PASO 4: Verificar duplicados en BusinessRegistration para evitar que un repartidor se registre como socio comercial
        $existingBusiness = BusinessRegistration::where('documentNumber', $request->nro_documento)
            ->orWhere('email', $request->email)
            ->first();
    
        if ($existingBusiness) { 
            $message = $existingBusiness->documentNumber === $request->nro_documento  
                ? 'Este número de documento ya está registrado como socio comercial'
                : 'Este correo electrónico ya está registrado como socio comercial';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }
    
        // PASO 5: Si no hay problemas, crear el nuevo registro
        $registro = RepartoRegistro::create($validator->validated());
    
        $imagenes = ['frente', 'reverso'];
        foreach ($imagenes as $lado) {
            if ($request->has("documento_imagen_$lado")) {
                $imagen = base64_decode($request->{"documento_imagen_$lado"});
                $fileName = "documento_motorizado_{$lado}_" . uniqid() . '.jpg';
    
                $tempFile = tempnam(sys_get_temp_dir(), 'doc');
                file_put_contents($tempFile, $imagen);
    
                $uploadedFile = new UploadedFile($tempFile, $fileName);
    
                $imgPath = Storage::disk('custom_public')->putFileAs('documento-motorizado', $uploadedFile, $fileName);
                $registro->update(["documento_imagen_$lado" => $imgPath]);
                unlink($tempFile);
            }
        }
    
        // Modificación para indicar que es un registro nuevo
        // Usamos un campo 'status' con valor 'new_registration' en lugar de una ruta específica
        return response()->json([
            'message' => 'Registro creado exitosamente', 
            'data' => $registro,
            'status' => 'new_registration'  // Indicador genérico de registro nuevo
        ], 201);
    }

    public function checkStatus(Request $request)
    {
        // Registrar la solicitud para depuración
        Log::info('Solicitud de verificación de estado de repartidor recibida', [
            'nroDocumento' => $request->input('nroDocumento'),
            'email' => $request->input('email')
        ]);

        $nroDocumento = $request->input('nroDocumento');
        $email = $request->input('email');

        // Buscar registro existente
        $registro = RepartoRegistro::where(function ($query) use ($nroDocumento, $email) {
            if ($nroDocumento) {
                $query->where('nro_documento', $nroDocumento);
            }
            if ($email) {
                $query->orWhere('email', $email);
            }
        })->latest()->first();

        if (!$registro) {
            return response()->json(['status' => 'new']);
        }

        // Verificar el estado del registro
        $completionStatus = $this->checkCompletionStatus($registro);

        if ($completionStatus['isComplete']) {
            return response()->json(['status' => 'complete']);
        }

        return response()->json([
            'status' => 'incomplete',
            'registration_id' => $registro->id,
            'current_step' => $completionStatus['nextStep'],
            'last_completed_step' => $completionStatus['lastCompletedStep']
        ]);
    }

    private function checkCompletionStatus($registro)
    {
        // Define el orden de los pasos
        $stepsOrder = [
            'registro',
            'zonas',
            'documentos',
            'documento-motorizado',
            'entrega-material'
        ];
        
        // Verificar cada paso del registro
        $steps = [
            'registro' => true, // Si existe el registro, este paso está completo
            'zonas' => $registro->datosPersonales()->exists(),
            'documentos' => $registro->datosBancarios()->exists(),
            'documento-motorizado' => $registro->registroVehiculo()->exists(),
            'entrega-material' => $registro->entregaCalendario()->exists()
        ];
    
        $lastCompletedStep = '/reparto';
        $nextStep = '/reparto/zonas'; // Comenzamos con el primer paso por defecto
    
        // Encontrar el último paso completado y el siguiente paso
        foreach ($stepsOrder as $step) {
            if (isset($steps[$step]) && $steps[$step]) {
                $lastCompletedStep = '/reparto/' . ($step === 'registro' ? '' : $step);
            } else {
                $nextStep = '/reparto/' . $step;
                break;
            }
        }
    
        $isComplete = $lastCompletedStep === '/reparto/' . end($stepsOrder);
    
        return [
            'isComplete' => $isComplete,
            'nextStep' => $nextStep,
            'lastCompletedStep' => $lastCompletedStep
        ];
    }

    public function getRegistrationStatus($id)
    {
        try {
            // Registrar la solicitud para depuración
            Log::info('Solicitud de estado de registro de repartidor por ID', ['id' => $id]);
            
            $registro = RepartoRegistro::findOrFail($id);
            
            // Verificar cada paso del registro
            $steps = [
                'registro' => true, // Si existe el registro, este paso está completo
                'zonas' => $registro->datosPersonales()->exists(),
                'documentos' => $registro->datosBancarios()->exists(),
                'documento-motorizado' => $registro->registroVehiculo()->exists(),
                'entrega-material' => $registro->entregaCalendario()->exists(),
                'confirmacion-entrega' => $registro->aprobado
            ];

            $currentStep = '/reparto/zonas';
            $lastCompletedStep = '/reparto';

            // Determinar el último paso completado y el siguiente paso
            foreach ($steps as $step => $completed) {
                if ($completed) {
                    $lastCompletedStep = '/reparto/' . ($step === 'registro' ? '' : $step);
                } else {
                    $currentStep = '/reparto/' . $step;
                    break;
                }
            }

            return response()->json([
                'current_step' => $currentStep,
                'last_completed_step' => $lastCompletedStep,
                'steps' => $steps
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el estado del registro de repartidor', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al obtener el estado del registro',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updateEmail(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            // Verificar si el correo ya existe en otro registro
            $existingEmail = RepartoRegistro::where('email', $validated['email'])
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingEmail) {
                return response()->json([
                    'message' => 'Este correo electrónico ya está registrado como repartidor.',
                    'error' => 'email_registered'
                ], 422);
            }

            // Verificar si existe en BusinessRegistration
            $existingBusiness = BusinessRegistration::where('email', $validated['email'])->first();
            if ($existingBusiness) {
                return response()->json([
                    'message' => 'Este correo electrónico ya está registrado como socio comercial',
                    'error' => 'duplicate_in_business'
                ], 422);
            }

            // Actualizar el correo
            $registro = RepartoRegistro::findOrFail($id);
            $registro->email = $validated['email'];
            $registro->save();

            return response()->json([
                'message' => 'Correo electrónico actualizado correctamente',
                'registration_id' => $registro->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar correo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Hubo un problema al actualizar el correo electrónico. Por favor, intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getRegistration($id)
{
    try {
        Log::info('Solicitud de datos de registro de repartidor por ID', ['id' => $id]);
        
        $registro = RepartoRegistro::findOrFail($id);
        
        return response()->json([
            'id' => $registro->id,
            'email' => $registro->email,
            'nombres' => $registro->nombres,
            'apellidos' => $registro->apellidos,
            'tipo_documento' => $registro->tipo_documento,
            'nro_documento' => $registro->nro_documento,
            'celular' => $registro->celular,
            'departamento' => $registro->departamento,
            'vehiculo' => $registro->vehiculo,
            'mayor_edad' => $registro->mayor_edad,
            'created_at' => $registro->created_at,
            'updated_at' => $registro->updated_at
        ]);
    } catch (\Exception $e) {
        Log::error('Error al obtener datos del registro de repartidor', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Hubo un problema al obtener la información del registro.',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
