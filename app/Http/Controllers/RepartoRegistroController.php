<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RepartoRegistroController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->except(['documento_imagen_frente', 'documento_imagen_reverso']), [
            'departamento' => 'required|string',
            'vehiculo' => 'required|string',
            'tipo_documento' => 'required|string',
            'nro_documento' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'nullable|string',
            'celular' => 'required|string',
            'email' => 'required|email',
            'mayor_edad' => 'required|boolean',
            'acepta_politica' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verificar duplicados en RepartoRegistro
        $existingReparto = RepartoRegistro::where('nro_documento', $request->nro_documento)
            ->orWhere('email', $request->email)
            ->first();

        if ($existingReparto) {
            $message = $existingReparto->nro_documento === $request->nro_documento 
                ? 'Este número de documento ya está registrado como repartidor'
                : 'Este correo electrónico ya está registrado como repartidor';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }

        // Verificar duplicados en BusinessRegistration para evitar que un repartidor se registre como socio comercial
        $existingBusiness = BusinessRegistration::where('documentNumber', $request->nro_documento)
            ->orWhere('email', $request->email) // Verificar si el correo ya está registrado
            ->first(); // Si existe, es un duplicado

        if ($existingBusiness) { 
            $message = $existingBusiness->documentNumber === $request->nro_documento  // Verificar si el duplicado es por número de documento
                ? 'Este número de documento ya está registrado como socio comercial'
                : 'Este correo electrónico ya está registrado como socio comercial';
            return response()->json(['errors' => ['duplicate' => [$message]]], 422);
        }

        $registro = RepartoRegistro::create($validator->validated());

        $imagenes = ['frente', 'reverso'];
        foreach ($imagenes as $lado) {
            if ($request->has("documento_imagen_$lado")) {
                $imagen = base64_decode($request->{"documento_imagen_$lado"});
                $fileName = "documento_motorizado_{$lado}_" . uniqid() . '.jpg';

                $tempFile = tempnam(sys_get_temp_dir(), 'doc');
                file_put_contents($tempFile, $imagen);

                $uploadedFile = new UploadedFile($tempFile, $fileName);

                $imgPath = Storage::disk('custom_public')->putFileAs('documento-motorizado', $uploadedFile, $fileName);
                $registro->update(["documento_imagen_$lado" => $imgPath]);
                unlink($tempFile);
            }
        }

        return response()->json(['message' => 'Registro creado exitosamente', 'data' => $registro], 201);
    }
}

