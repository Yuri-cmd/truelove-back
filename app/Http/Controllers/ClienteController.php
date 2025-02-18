<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\TwilioService;

class ClienteController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    public function sendCode(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'email' => 'required|email',
            ]);

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);

            // Enviar el correo con el código de verificación
            Mail::to($request->email)->send(new SendCode($request->email, $newVerificationCode));

            // Retornar el código en la respuesta para ser usado en la aplicación
            return response()->json([
                'message' => 'Nuevo código de verificación enviado al correo electrónico',
                'status' => 200,
                'verification_code' => $newVerificationCode, // Devolver el código
            ]);
        } catch (\Exception $e) {
            // Capturar cualquier error y devolver una respuesta de error
            return response()->json([
                'message' => 'Hubo un problema al reenviar el código de verificación. Por favor, intente nuevamente.',
                'error' => $e->getMessage() // Detalle del error para depuración
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Validar los datos del request
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|string|in:Femenino,Masculino,No Binario',
            'documento' => 'required|string|max:255',
            'nacionalidad' => 'required|string|max:255',
            'type' => 'required|string|in:celular,email', // Validación adicional
            'content' => 'required|string',
        ]);

        $profile = new Cliente();
        $profile->nombre = $validatedData['nombre'];
        $profile->apellido = $validatedData['apellido'];
        $profile->fecha_nacimiento = $validatedData['fecha_nacimiento'];
        $profile->genero = $validatedData['genero'];
        $profile->{$validatedData['type']} = $validatedData['content']; // Asignación dinámica
        $profile->documento = $validatedData['documento'];
        $profile->nacionalidad = $validatedData['nacionalidad'];
        $profile->save();

        return response()->json([
            'message' => 'Perfil creado exitosamente',
            'data' => $profile,
        ], 200);
    }

    public function getDni(Request $request)
    {

        if (!$request->documento) {
            return response()->json(['error' => 'Error al obtener la información.'], 500);
        }

        $token = env('API_TOKEN');
        $url = "https://dniruc.apisperu.com/api/v1/dni/{$request->documento}?token={$token}";

        try {
            $response = file_get_contents($url);
            if ($response === false) {
                return response()->json(['error' => 'Error al obtener la información.'], 500);
            }
            return response()->json(json_decode($response, true));
        } catch (Exception $e) {
            return response()->json(['error' => 'Excepción capturada: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarInfoCliente(Request $request)
    {
        $profile = Cliente::find($request->idCliente);
        $profile->celular = $request->celular;
        $profile->save();

        $direccion = new ClienteDireccion();
        $direccion->id_cliente = $request->idCliente;
        $direccion->direccion = $request->direccion;
        $direccion->departamento = $request->departamento;
        $direccion->referencia = $request->referencia;
        $direccion->alias = $request->alias;
        $direccion->coordenadas = json_encode($request->selectedPosition);
        $direccion->save();

        return response()->json([
            'message' => 'Perfil creado exitosamente',
            'data' => $profile,
            'dirreccion' => $direccion,
        ], 200);
    }

    public function sendCodePhone(Request $request)
    {
        try {
            // Validar que el teléfono es requerido
            $request->validate([
                'phone' => 'required|regex:/^\+?[0-9]{9,15}$/', // Número internacional con 9-15 dígitos
            ]);

            $phone = $request->phone;

            // Agregar el prefijo +51 si no está presente
            if (!str_starts_with($phone, '+51')) {
                $phone = '+51' . ltrim($phone, '0'); // Elimina ceros iniciales en caso de que existan
            }

            // Generar código de verificación (6 dígitos numéricos)
            $newVerificationCode = random_int(100000, 999999);

            $message = 'Su código de verificación es: ' . $newVerificationCode;

            // Enviar el SMS
            $result = $this->twilioService->sendSms($phone, $message);

            if (!$result) {
                return response()->json(['error' => 'Error al enviar SMS'], 500);
            }

            // Retornar el código (solo en desarrollo, no en producción)
            return response()->json([
                'message' => 'SMS enviado correctamente',
                'status' => 200,
                'verification_code' => (string) $newVerificationCode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Hubo un problema al reenviar el código de verificación. Por favor, intente nuevamente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadPhotos(Request $request)
    {
        $cliente = Cliente::findOrFail($request->id_cliente);

        $dniPath = $request->file('dni_photo')->store('clientes/dni', 'public');
        $selfiePath = $request->file('selfie_photo')->store('clientes/selfies', 'public');

        $cliente->update([
            'dni_photo' => $dniPath,
            'selfie_photo' => $selfiePath,
        ]);

        return response()->json(['message' => 'Fotos subidas correctamente', 'dni_photo' => $dniPath, 'selfie_photo' => $selfiePath]);
    }
}
