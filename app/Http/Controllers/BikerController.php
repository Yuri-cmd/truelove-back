<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PedidoService;

class BikerController extends Controller
{
    public function login(Request $request)
    {
        // Buscar el reparto_registro
        $reparto = RepartoRegistro::where('email', $request->email)
            ->where('estado', 1) // Estado debe ser 1
            ->where('aprobado', 1) // Aprobación debe ser 1
            ->first();

        if (!$reparto) {
            return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado o no aprobado'], 404);
        }

        // Buscar el usuario relacionado
        $user = User::find($reparto->user_id);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales incorrectas'], 401);
        }

        // Si todo está bien, devolver el usuario y token
        // Asumiendo que usas Sanctum o Passport para autenticación con tokens
        return response()->json([
            'status' => 'success',
            'message' => 'Inicio de sesión exitoso',
            'user' => $user,
            'token' => $user->createToken('your-app-name')->plainTextToken,
        ]);
    }

    public function getPedidos($idMotorizado)
    {
        $pedidoService = new PedidoService();
        $pedidos = $pedidoService->obtenerPedidosConTiempoEstimado($idMotorizado);
        return response()->json($pedidos);
    }
}
