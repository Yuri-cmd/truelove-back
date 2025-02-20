<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationStatusController extends Controller
{
    public function checkStatus(Request $request)
    {
        $documentNumber = $request->input('documentNumber');
        $email = $request->input('email');

        // Buscar registro existente
        $registration = BusinessRegistration::where(function($query) use ($documentNumber, $email) {
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
        // Verificar cada paso del registro
        $steps = [
            'email' => !is_null($registration->email_verified_at),
            'acercaNegocio' => $registration->negocios()->exists(),
            'ubicar-local' => $registration->establecimientos()->exists(),
            'datosClaves' => $registration->datos_clave_negocio()->exists(),
            'datosBancarios' => $registration->datos_bancarios()->exists(),
            'cuenta-bancaria' => $registration->socios_cuentas_bancarias()->exists(),
        ];

        $lastCompletedStep = '/';
        $isComplete = true;
        $nextStep = '/email';

        foreach ($steps as $step => $completed) {
            if (!$completed) {
                $isComplete = false;
                $nextStep = '/' . $step;
                break;
            }
            $lastCompletedStep = '/' . $step;
        }

        return [
            'isComplete' => $isComplete,
            'nextStep' => $nextStep,
            'lastCompletedStep' => $lastCompletedStep
        ];
    }
}

