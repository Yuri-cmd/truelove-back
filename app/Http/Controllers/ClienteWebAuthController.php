<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Http\Request;
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
