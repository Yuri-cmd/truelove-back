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

        // Verificar si existe un registro incompleto
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

        // Verificar duplicados en BusinessRegistration para evitar que un repartidor se registre como socio comercial
        $existingBusiness = BusinessRegistration::where('documentNumber', $request->nro_documento)
            ->orWhere('email', $request->email) // Verificar si el correo ya está registrado
            ->first(); // Si existe, es un duplicado

        if ($existingBusiness) { 
            $message = $existingBusiness->documentNumber === $request->nro_documento  // Verificar si el duplicado es por número de documento
                ? 'Este número de documento ya está registrado como socio comercial'
                : 'Este correo electrónico ya está registrado como socio comercial';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }

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

        return response()->json(['message' => 'Registro creado exitosamente', 'data' => $registro], 201);
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
            'entrega-material',
            'confirmacion-entrega'
        ];
        
        // Verificar cada paso del registro
        $steps = [
            'registro' => true, // Si existe el registro, este paso está completo
            'zonas' => $registro->datosPersonales()->exists(),
            'documentos' => $registro->registroVehiculo()->exists(),
            'entrega-material' => $registro->datosBancarios()->exists(),
            'confirmacion-entrega' => $registro->aprobado
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
                'documentos' => $registro->registroVehiculo()->exists(),
                'entrega-material' => $registro->datosBancarios()->exists(),
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
    
    public function abandonRegistration($id)
    {
        try {
            $registro = RepartoRegistro::findOrFail($id);
            
            // Aquí puedes implementar la lógica para marcar el registro como abandonado
            // Por ejemplo, podrías tener un campo 'abandonado' en la tabla
            // $registro->update(['abandonado' => true]);
            
            // O simplemente registrar el evento
            Log::info('Registro de repartidor marcado como abandonado', ['id' => $id]);
            
            return response()->json(['message' => 'Registro abandonado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al abandonar el registro', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Error al abandonar el registro',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

