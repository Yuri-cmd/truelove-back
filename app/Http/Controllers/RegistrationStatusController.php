<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationStatusController extends Controller
{
    public function checkStatus(Request $request)
    {
        // Registrar la solicitud para depuración
        Log::info('Solicitud de verificación de estado recibida', [
            'documentNumber' => $request->input('documentNumber'),
            'email' => $request->input('email')
        ]);

        $documentNumber = $request->input('documentNumber');
        $email = $request->input('email');

        // Buscar registro existente
        $registration = BusinessRegistration::where(function ($query) use ($documentNumber, $email) {
            if ($documentNumber) {
                $query->where('documentNumber', $documentNumber);
            }
            if ($email) {
                $query->orWhere('email', $email);
            }
        })->latest()->first();

        if (!$registration) {
            return response()->json(['status' => 'new']);
        }

        // Verificar el estado del registro
        $completionStatus = $this->checkCompletionStatus($registration);

        if ($completionStatus['isComplete']) {
            return response()->json(['status' => 'complete']);
        }

        return response()->json([
            'status' => 'incomplete',
            'registration_id' => $registration->id,
            'current_step' => $completionStatus['nextStep'],
            'last_completed_step' => $completionStatus['lastCompletedStep']
        ]);
    }

    private function checkCompletionStatus($registration)
    {
        // Define el orden de los pasos
        $stepsOrder = [
            'email',
            'acercaNegocio',
            'ubicar-local',
            'datosClaves',
            'datosBancarios',
            'cuenta-bancaria',
            'firmar-contrato',
            'verificacion-documentos'
        ];
        
        // Verificar cada paso del registro - Corregido para usar los nombres correctos de las relaciones
        $steps = [
            'email' => !is_null($registration->email_verified_at),
            'acercaNegocio' => $registration->negocio()->exists(),
            'ubicar-local' => $registration->establecimiento()->exists(),
            'datosClaves' => $registration->datosClaveNegocio()->exists(),
            'datosBancarios' => $registration->datosBancarios()->exists(),
            'cuenta-bancaria' => $registration->cuentaBancaria()->exists(),
        ];
    
        $lastCompletedStep = '/';
        $nextStep = '/email'; // Comenzamos con el primer paso por defecto
    
        // Encontrar el último paso completado y el siguiente paso
        foreach ($stepsOrder as $step) {
            if (isset($steps[$step]) && $steps[$step]) {
                $lastCompletedStep = '/' . $step;
            } else {
                $nextStep = '/' . $step;
                break;
            }
        }
    
        $isComplete = $lastCompletedStep === '/' . end($stepsOrder);
    
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
            Log::info('Solicitud de estado de registro por ID', ['id' => $id]);
            
            $registration = BusinessRegistration::findOrFail($id);
            
            // Verificar cada paso del registro - Corregido para usar los nombres correctos de las relaciones
            $steps = [
                'email' => !is_null($registration->email_verified_at),
                'acercaNegocio' => $registration->negocio()->exists(),
                'ubicar-local' => $registration->establecimiento()->exists(),
                'datosClaves' => $registration->datosClaveNegocio()->exists(),
                'datosBancarios' => $registration->datosBancarios()->exists(),
                'cuenta-bancaria' => $registration->cuentaBancaria()->exists(),
            ];

            $currentStep = '/email';
            $lastCompletedStep = '/';

            // Determinar el último paso completado y el siguiente paso
            foreach ($steps as $step => $completed) {
                if ($completed) {
                    $lastCompletedStep = '/' . $step;
                } else {
                    $currentStep = '/' . $step;
                    break;
                }
            }

            return response()->json([
                'current_step' => $currentStep,
                'last_completed_step' => $lastCompletedStep,
                'steps' => $steps
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el estado del registro', [
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
}

