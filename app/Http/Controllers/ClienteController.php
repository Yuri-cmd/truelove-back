<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
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
            'email' => 'required|email',
            'genero' => 'required|string|in:Femenino,Masculino,No Binario',
            'genero' => 'required|string|in:Femenino,Masculino,No Binario',
            'documento' => 'required|string|max:255',
            'nacionalidad' => 'required|string|max:255',
        ]);

        $profile = new Cliente();
        $profile->nombre = $validatedData['nombre'];
        $profile->apellido = $validatedData['apellido'];
        $profile->fecha_nacimiento = $validatedData['fecha_nacimiento'];
        $profile->genero = $validatedData['genero'];
        $profile->email = $validatedData['email'];
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
        $direccion->coordenadas = $request->coordenadas;
        $direccion->save();
    }
}
