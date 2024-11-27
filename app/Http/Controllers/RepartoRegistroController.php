<?php

namespace App\Http\Controllers;


use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RepartoRegistroController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->except(['documento_imagen']), [
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

        $registro = RepartoRegistro::create($validator->validated());

        if ($request->has('documento_imagen')) {
            $imagen = base64_decode($request->documento_imagen);
            $fileName = 'documento_motorizado_' . uniqid() . '.jpg';

            $tempFile = tempnam(sys_get_temp_dir(), 'doc');
            file_put_contents($tempFile, $imagen);

            $uploadedFile = new UploadedFile($tempFile, $fileName);

            $imgPath = Storage::disk('custom_public')->putFileAs('documento-motorizado', $uploadedFile, $fileName);
            $registro->update(['documento_imagen' => $imgPath]);
            unlink($tempFile);
        }

        return response()->json(['message' => 'Registro creado exitosamente', 'data' => $registro], 201);
    }
}