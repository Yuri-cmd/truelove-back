<?php

namespace App\Http\Controllers;

use App\Mail\SendCodeCliente;
use App\Mail\SendAccountDeletionCode;
use App\Mail\CredencialesCliente;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\ClienteDeletionRequest;
use App\Models\EmailLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

            if (Cliente::where('email', $request->email)->exists()) {
                return response()->json([
                    'message' => 'El correo electrónico ya está registrado',
                    'status' => 400,
                ]);
            }

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);

            // Enviar el correo con el código de verificación
            Mail::to($request->email)->send(new SendCodeCliente($request->email, $newVerificationCode, false));

            // Registrar en logs
            EmailLog::logSuccess(
                $request->email,
                'Código de verificación - TRUELOVE',
                'verification_new_user',
                'ClienteController',
                'sendCode'
            );

            // Retornar el código en la respuesta para ser usado en la aplicación
            return response()->json([
                'message' => 'Nuevo código de verificación enviado al correo electrónico',
                'status' => 200,
                'verification_code' => $newVerificationCode,
            ]);
        } catch (\Exception $e) {
            // Registrar error en logs
            EmailLog::logFailure(
                $request->email ?? 'unknown',
                'Código de verificación - TRUELOVE',
                'verification_new_user',
                $e->getMessage(),
                'ClienteController',
                'sendCode'
            );

            // Capturar cualquier error y devolver una respuesta de error
            return response()->json([
                'message' => 'Hubo un problema al reenviar el código de verificación. Por favor, intente nuevamente.',
                'error' => $e->getMessage()
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
        if (Cliente::where('documento', $request->documento)->exists()) {
            return response()->json(['message' => 'El DNI ya se encuentra está registrado'], 400);
        }

        if (!$request->documento) {
            return response()->json(['message' => 'Error al obtener la información.'], 500);
        }

        $token = env('API_TOKEN');
        $url = "https://dniruc.apisperu.com/api/v1/dni/{$request->documento}?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6InN5c3RlbWNyYWZ0LnBlQGdtYWlsLmNvbSJ9.yuNS5hRaC0hCwymX_PjXRoSZJWLNNBeOdlLRSUGlHGA";

        try {
            $response = file_get_contents($url);
            if ($response === false) {
                return response()->json(['message' => 'Error al obtener la información.'], 500);
            }
            $data = json_decode($response, true);
            $data['status'] = 200;

            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Excepción capturada: ' . $e->getMessage()], 500);
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

        // Enviar correo con credenciales al cliente
        if ($profile->email) {
            try {
                Mail::to($profile->email)->send(new CredencialesCliente(
                    $profile->email,
                    $profile->documento
                ));

                // Registrar en logs
                EmailLog::logSuccess(
                    $profile->email,
                    'Credenciales de acceso - TRUELOVE',
                    'credentials',
                    'ClienteController',
                    'actualizarInfoCliente'
                );
            } catch (\Exception $e) {
                Log::error('Error al enviar credenciales al cliente: ' . $e->getMessage());
                
                // Registrar error en logs
                EmailLog::logFailure(
                    $profile->email,
                    'Credenciales de acceso - TRUELOVE',
                    'credentials',
                    $e->getMessage(),
                    'ClienteController',
                    'actualizarInfoCliente'
                );
            }
        }

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
        Log::info('uploadPhotos - Recibida solicitud', [
            'id_cliente' => $request->id_cliente,
            'dni_photo' => $request->hasFile('dni_photo'),
            'selfie_photo' => $request->hasFile('selfie_photo')
        ]);

        Log::info('Contenido de la petición', [
            'headers' => $request->headers->all(),
            'all' => $request->all(),
            'files' => $request->files->all()
        ]);


        if (!$request->hasFile('dni_photo') || !$request->hasFile('selfie_photo')) {
            Log::error('Uno o ambos archivos no fueron recibidos');
            return response()->json(['error' => 'Faltan archivos'], 400);
        }

        try {
            $cliente = Cliente::findOrFail($request->id_cliente);

            $dniPath = $request->file('dni_photo')->store('clientes/dni', 'custom_public');
            $selfiePath = $request->file('selfie_photo')->store('clientes/selfies', 'custom_public');

            Log::info('Archivos almacenados', [
                'dniPath' => $dniPath,
                'selfiePath' => $selfiePath
            ]);

            $cliente->dni_photo = $dniPath;
            $cliente->selfie_photo = $selfiePath;
            $cliente->save();

            return response()->json([
                'message' => 'Fotos subidas correctamente',
                'dni_photo' => $dniPath,
                'selfie_photo' => $selfiePath
            ]);
        } catch (\Exception $e) {
            Log::error('Error al subir fotos: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Método para iniciar sesión como cliente
     */
    public function login(Request $request)
    {
        // 1. Buscar cliente por email
        $cliente = Cliente::where('email', $request->email)->first();

        // 2. Validar contraseña o documento
        if ($cliente) {
            $isPasswordValid = Hash::check($request->password, $cliente->password);
            $isDocumentoMatch = $cliente->documento === $request->password;

            if ($isPasswordValid || $isDocumentoMatch) {
                $direccion = ClienteDireccion::where('id_cliente', $cliente->id)->first();
                if ($direccion) {
                    $coordenadas = json_decode($direccion->coordenadas);
                    $cliente->latitud = $coordenadas->coordinates[0];
                    $cliente->longitud = $coordenadas->coordinates[1];
                    $cliente->direccion = $direccion->direccion;
                } else {
                    $cliente->latitud = null;
                    $cliente->longitud = null;
                    $cliente->direccion = null;
                }
                $cliente->celular = $cliente->celular ? $cliente->celular : '999999999';
                return response()->json([
                    $cliente,
                ], 200);
            }
        }

        // 3. Si no coincide ni con password ni documento
        return response()->json([
            'message' => 'Cliente no encontrado',
        ], 404);
    }

    public function getPerfil($idCliente)
    {
        $profile = Cliente::find($idCliente);
        if ($profile) {
            $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();
            if ($direccion) {
                $coordenadas = json_decode($direccion->coordenadas);
                $profile->latitud = $coordenadas->coordinates[0];
                $profile->longitud = $coordenadas->coordinates[1];
                $profile->direccion = $direccion->direccion;
            } else {
                $profile->latitud = null;
                $profile->longitud = null;
                $profile->direccion = null;
            }

            $profile->foto_perfil = $profile->foto_perfil ? config('app.url') . "/storage/{$profile->foto_perfil}" : '';

            return response()->json([
                'message' => 'Perfil encontrado',
                'data' => $profile,
            ], 200);
        } else {
            return response()->json([
                'message' => 'Perfil no encontrado',
            ], 404);
        }
    }

    // En el controlador
    public function updateProfile(Request $request)
    {
        $request->validate([
            'id_cliente' => 'required|integer',
            'tipo' => 'required|string',
            'valor' => 'required|string',
        ]);

        $cliente = Cliente::find($request->id_cliente);

        if (!$cliente) {
            return response()->json(['success' => false, 'message' => 'Cliente no encontrado']);
        }

        switch ($request->tipo) {
            case 'genero':
                $cliente->genero = $request->valor;
                break;
            case 'email':
                $cliente->email = $request->valor;
                break;
            case 'celular':
                $cliente->celular = $request->valor;
                break;
            default:
                return response()->json(['success' => false, 'message' => 'Tipo no válido'],);
        }

        $cliente->save();

        return response()->json(['success' => true, 'message' => 'Perfil actualizado'], 200);
    }


    public function actualizarDireccion(Request $request)
    {
        $direccion = ClienteDireccion::where('id_cliente', $request->idCliente)->first();
        $direccion->direccion = $request->direccion;
        $direccion->coordenadas = json_encode($request->selectedPosition);
        $direccion->save();

        return response()->json([
            'message' => 'Perfil creado exitosamente',
            'dirreccion' => $direccion,
        ], 200);
    }

    public function actualizarFotoPerfil(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = Cliente::find($request->id_cliente);

        if ($request->hasFile('foto')) {
            $filename = $request->file('foto')->store('clientes/foto_perfil', 'custom_public');
            $user->foto_perfil = $filename;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto actualizada',
                'path' => asset('storage/perfiles/' . $filename)
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Archivo no recibido'], 400);
    }

    public function sendCodeNew(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'email' => 'required|email',
            ]);

            $user = Cliente::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo es incorrecto.',
                    'status' => 500,
                ]);
            }

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);

            // Enviar el correo con el código de verificación (isNewCode = true para recuperación)
            Mail::to($request->email)->send(new SendCodeCliente($request->email, $newVerificationCode, true));

            // Registrar en logs
            EmailLog::logSuccess(
                $request->email,
                'Código de verificación - TRUELOVE',
                'password_reset',
                'ClienteController',
                'sendCodeNew'
            );

            // Retornar el código en la respuesta para ser usado en la aplicación
            return response()->json([
                'success' => true,
                'message' => 'Código de verificación enviado al correo electrónico',
                'status' => 200,
                'verification_code' => $newVerificationCode,
                'id' => $user->id
            ]);
        } catch (\Exception $e) {
            // Registrar error en logs
            EmailLog::logFailure(
                $request->email ?? 'unknown',
                'Código de verificación - TRUELOVE',
                'password_reset',
                $e->getMessage(),
                'ClienteController',
                'sendCodeNew'
            );

            // Capturar cualquier error y devolver una respuesta de error
            return response()->json([
                'message' => 'Hubo un problema al reenviar el código de verificación. Por favor, intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $usuario = Cliente::where('id', $request->id)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function updateToken(Request $request)
    {
        $reparto = Cliente::findOrFail($request->id_reparto);
        $reparto->token_fmc = $request->token_fcm;
        $reparto->save();

        return response()->json([
            'success' => true,
            'message' => 'Token actualizado correctamente',
            'data' => $reparto
        ]);
    }

    /**
     * Buscar cliente por número de documento
     */
    public function buscarPorDocumento(Request $request)
    {
        try {
            $request->validate([
                'documento' => 'required|string',
            ]);

            $cliente = Cliente::where('documento', $request->documento)->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún cliente con ese número de documento',
                ], 404);
            }

            // Solo devolver datos necesarios
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'apellido' => $cliente->apellido,
                    'email' => $cliente->email,
                    'documento' => $cliente->documento,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Solicitar código de eliminación de cuenta
     */
    public function solicitarEliminacionCuenta(Request $request)
    {
        try {
            $request->validate([
                'documento' => 'required|string',
                'motivo' => 'required|string',
            ]);

            $cliente = Cliente::where('documento', $request->documento)->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún cliente con ese número de documento',
                ], 404);
            }

            if (!$cliente->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este cliente no tiene un correo electrónico registrado',
                ], 400);
            }

            // Verificar si ya tiene una solicitud pendiente
            $existingRequest = ClienteDeletionRequest::where('cliente_id', $cliente->id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una solicitud de eliminación pendiente',
                ], 400);
            }

            // Generar código de verificación
            $verificationCode = Str::random(6);

            // Generar ID de solicitud único
            $requestId = '#' . strtoupper(uniqid());

            // Crear la solicitud
            $deletionRequest = ClienteDeletionRequest::create([
                'cliente_id' => $cliente->id,
                'request_id' => $requestId,
                'motivo' => $request->motivo,
                'verification_code' => $verificationCode,
                'requested_at' => now(),
            ]);

            // Enviar correo con el código de verificación para eliminación
            Mail::to($cliente->email)->send(new SendAccountDeletionCode($cliente->nombre . ' ' . $cliente->apellido, $verificationCode));

            // Registrar en logs
            EmailLog::logSuccess(
                $cliente->email,
                'Código de eliminación de cuenta - TRUELOVE',
                'account_deletion',
                'ClienteController',
                'solicitarEliminacionCuenta'
            );

            return response()->json([
                'success' => true,
                'message' => 'Código de verificación enviado al correo electrónico',
                'email' => substr($cliente->email, 0, 3) . '***@***' . substr($cliente->email, strpos($cliente->email, '@')),
                'deletion_request_id' => $deletionRequest->id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al solicitar eliminación de cuenta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar código y crear solicitud pendiente (NO elimina aún)
     */
    public function verificarCodigoEliminacion(Request $request)
    {
        try {
            $request->validate([
                'deletion_request_id' => 'required|integer',
                'codigo' => 'required|string',
            ]);

            $deletionRequest = ClienteDeletionRequest::with('cliente')->find($request->deletion_request_id);

            if (!$deletionRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solicitud no encontrada',
                ], 404);
            }

            // Verificar si ya fue verificada
            if ($deletionRequest->code_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este código ya fue verificado',
                ], 400);
            }

            // Verificar si expiró (15 minutos desde la creación)
            if (now()->greaterThan($deletionRequest->requested_at->addMinutes(15))) {
                return response()->json([
                    'success' => false,
                    'message' => 'El código de verificación ha expirado',
                ], 400);
            }

            // Verificar el código (case-insensitive)
            if (strtoupper($deletionRequest->verification_code) !== strtoupper($request->codigo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código de verificación incorrecto',
                ], 400);
            }

            // Marcar como verificado
            $deletionRequest->code_verified_at = now();
            $deletionRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Código verificado exitosamente',
                'request_id' => $deletionRequest->request_id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al verificar código: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
