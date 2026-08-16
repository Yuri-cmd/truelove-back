<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Promocion;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SocioPromocionController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    private function socioActual(Request $request)
    {
        return $request->user()->businessRegistration;
    }

    public function index(Request $request)
    {
        $socio = $this->socioActual($request);

        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Usuario no es un socio válido'], 403);
        }

        $promociones = Promocion::where('business_registration_id', $socio->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $promociones]);
    }

    public function store(Request $request)
    {
        $socio = $this->socioActual($request);

        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Usuario no es un socio válido'], 403);
        }

        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'subtitulo' => 'required|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'estado' => 'boolean',
        ]);

        $promocion = new Promocion();
        $promocion->business_registration_id = $socio->id;
        $promocion->titulo = $data['titulo'];
        $promocion->subtitulo = $data['subtitulo'];
        $promocion->estado = $data['estado'] ?? 1;

        // Una promo de un local siempre redirige a su propio restaurante al presionarla:
        // no tiene sentido (ni se le da la opción) de mandar clientes a otro negocio o pantalla.
        $promocion->tipo_destino = 'restaurante';
        $promocion->destino_id = $socio->id;

        if ($request->hasFile('imagen')) {
            $promocion->imagen = $request->file('imagen')->store('promociones-img', 'custom_public');
        }

        $promocion->save();

        $nombreLocal = $socio->establecimiento?->nombre_establecimiento;

        // Igual que las promos de plataforma: notificar después de responder, no antes,
        // para no dejar al socio esperando mientras se le manda push a cada cliente.
        dispatch(function () use ($promocion, $nombreLocal) {
            $clientes = Cliente::whereNotNull('token_fmc')->get();
            $titulo = $nombreLocal ? "{$nombreLocal}: " . $promocion->titulo : $promocion->titulo;

            $recipients = $clientes->map(fn ($cliente) => [
                'token' => $cliente->token_fmc,
                'title' => $titulo,
                'body' => $promocion->subtitulo,
                'userId' => $cliente->id,
                'userType' => 'cliente',
            ])->all();

            $this->firebaseService->sendNotificationsBatch($recipients);
        })->afterResponse();

        return response()->json(['success' => true, 'data' => $promocion], 201);
    }

    public function update(Request $request, $id)
    {
        $socio = $this->socioActual($request);

        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Usuario no es un socio válido'], 403);
        }

        $promocion = Promocion::where('business_registration_id', $socio->id)->find($id);

        if (!$promocion) {
            return response()->json(['success' => false, 'message' => 'Promoción no encontrada'], 404);
        }

        $data = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'subtitulo' => 'sometimes|required|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'estado' => 'sometimes|boolean',
        ]);

        $promocion->titulo = $data['titulo'] ?? $promocion->titulo;
        $promocion->subtitulo = $data['subtitulo'] ?? $promocion->subtitulo;
        $promocion->estado = $data['estado'] ?? $promocion->estado;

        if ($request->hasFile('imagen')) {
            if ($promocion->imagen) {
                Storage::disk('custom_public')->delete($promocion->imagen);
            }
            $promocion->imagen = $request->file('imagen')->store('promociones-img', 'custom_public');
        }

        $promocion->save();

        return response()->json(['success' => true, 'data' => $promocion]);
    }

    public function destroy(Request $request, $id)
    {
        $socio = $this->socioActual($request);

        if (!$socio) {
            return response()->json(['success' => false, 'message' => 'Usuario no es un socio válido'], 403);
        }

        $promocion = Promocion::where('business_registration_id', $socio->id)->find($id);

        if (!$promocion) {
            return response()->json(['success' => false, 'message' => 'Promoción no encontrada'], 404);
        }

        if ($promocion->imagen) {
            Storage::disk('custom_public')->delete($promocion->imagen);
        }

        $promocion->delete();

        return response()->json(['success' => true, 'message' => 'Promoción eliminada correctamente']);
    }
}
