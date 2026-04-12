<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ErrorLog;
use App\Mail\ErrorLoggedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ErrorLogController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'app_name' => 'required|string',
                'error_message' => 'required|string',
                'stack_trace' => 'nullable|string',
                'user_id' => 'nullable|integer',
                'device_info' => 'nullable|array',
                'url' => 'nullable|string',
                'method' => 'nullable|string',
                'request_data' => 'nullable|array',
            ]);

            $errorLog = ErrorLog::create($validated);

            // Enviar correo
            // Puedes cambiar el correo de destino según necesites
            $adminEmail = env('ERROR_NOTIFICATION_EMAIL', 'yurim16@hotmail.com');
            
            Mail::to($adminEmail)->send(new ErrorLoggedMail($errorLog));

            return response()->json([
                'success' => true,
                'message' => 'Error logged successfully',
                'id' => $errorLog->id
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to log error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to log error'
            ], 500);
        }
    }
}
