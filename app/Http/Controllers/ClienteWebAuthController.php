<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de clientes para la web (truelove-front), separada del
 * login usado por la app móvil (ClienteController::login), que no emite
 * token de sesión. Estas rutas sí emiten tokens Sanctum.
 */
class ClienteWebAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $cliente = Cliente::where('email', $request->email)->first();

        $isValid = $cliente && (
            Hash::check($request->password, $cliente->password)
            || $cliente->documento === $request->password
        );

        if (!$isValid) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $token = $cliente->createToken('truelove-web')->plainTextToken;

        return response()->json([
            'message' => 'Sesión iniciada correctamente',
            'token' => $token,
            'cliente' => $this->withDireccion($cliente),
        ]);
    }

    /**
     * Inicio de sesión con Google (Firebase Auth). Solo permite entrar si ya
     * existe un cliente con ese correo; si no, responde 404 con
     * code=not_registered para que la web le pida registrarse.
     */
    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $claims = $this->verifyFirebaseIdToken($request->id_token);

        if (!$claims || empty($claims->email) || empty($claims->email_verified)) {
            return response()->json([
                'message' => 'No se pudo validar tu cuenta de Google.',
            ], 401);
        }

        $cliente = Cliente::where('email', $claims->email)->first();

        if (!$cliente) {
            return response()->json([
                'code' => 'not_registered',
                'message' => 'No encontramos una cuenta con este correo de Google. Regístrate para continuar.',
                'email' => $claims->email,
            ], 404);
        }

        $token = $cliente->createToken('truelove-web')->plainTextToken;

        return response()->json([
            'message' => 'Sesión iniciada correctamente',
            'token' => $token,
            'cliente' => $this->withDireccion($cliente),
        ]);
    }

    private function verifyFirebaseIdToken(string $idToken): ?object
    {
        $projectId = config('services.firebase_web.project_id');
        if (!$projectId) {
            return null;
        }

        try {
            $certs = Cache::remember('firebase_securetoken_certs', 3600, function () {
                return Http::get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com')
                    ->throw()
                    ->json();
            });

            $keys = [];
            foreach ($certs as $kid => $cert) {
                $keys[$kid] = new Key($cert, 'RS256');
            }

            $claims = JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            return null;
        }

        if (($claims->aud ?? null) !== $projectId
            || ($claims->iss ?? null) !== "https://securetoken.google.com/{$projectId}") {
            return null;
        }

        return $claims;
    }

    /**
     * Crea la cuenta del cliente igual que el paso "Cuéntanos más de ti" de
     * la app móvil (ClienteController::store vía POST /profile): sin
     * contraseña. El acceso posterior se hace con el documento como clave,
     * igual que en la app, salvo que el cliente configure una contraseña
     * más adelante desde "Olvidé mi contraseña".
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date|before_or_equal:' . now()->subYears(18)->toDateString(),
            'genero' => 'required|string|in:Femenino,Masculino,No Binario',
            'documento' => 'required|string|max:255',
            'nacionalidad' => 'required|string|max:255',
            'email' => 'required|email|unique:clientes,email',
            'celular' => 'nullable|string',
            'celular_whatsapp' => 'nullable|string',
        ], [
            'fecha_nacimiento.before_or_equal' => 'Debes ser mayor de edad para registrarte.',
        ]);

        if (Cliente::where('documento', $validated['documento'])->exists()) {
            throw ValidationException::withMessages([
                'documento' => ['El DNI ya se encuentra registrado.'],
            ]);
        }

        $cliente = new Cliente();
        $cliente->nombre = $validated['nombre'];
        $cliente->apellido = $validated['apellido'];
        $cliente->fecha_nacimiento = $validated['fecha_nacimiento'];
        $cliente->genero = $validated['genero'];
        $cliente->documento = $validated['documento'];
        $cliente->nacionalidad = $validated['nacionalidad'];
        $cliente->email = $validated['email'];
        $cliente->celular = $validated['celular'] ?: null;
        $cliente->celular_whatsapp = $validated['celular_whatsapp'] ?: $cliente->celular;
        $cliente->save();

        $token = $cliente->createToken('truelove-web')->plainTextToken;

        return response()->json([
            'message' => 'Cuenta creada correctamente',
            'token' => $token,
            'cliente' => $cliente,
        ], 201);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'password' => 'required|string|min:6',
        ]);

        $cliente = Cliente::find($request->id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $cliente->password = Hash::make($request->password);
        $cliente->save();

        // Invalidar sesiones anteriores y entregar una nueva
        $cliente->tokens()->delete();
        $token = $cliente->createToken('truelove-web')->plainTextToken;

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
            'token' => $token,
            'cliente' => $this->withDireccion($cliente),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'cliente' => $this->withDireccion($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    private function withDireccion(Cliente $cliente)
    {
        $direccion = ClienteDireccion::where('id_cliente', $cliente->id)->first();

        if ($direccion) {
            $cliente->direccion = $direccion->direccion;
        }

        return $cliente;
    }
}
