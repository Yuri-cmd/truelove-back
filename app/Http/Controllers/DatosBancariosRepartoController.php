<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\CuentaBancariaReparto;
use App\Models\TipoCuentaBancaria;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatosBancariosRepartoController extends Controller
{
    public function obtenerBancos()
    {
        $bancos = Banco::all();
        return response()->json($bancos);
    }

    public function obtenerTiposCuenta()
    {
        $tiposCuenta = TipoCuentaBancaria::all();
        return response()->json($tiposCuenta);
    }

    public function show($repartoRegistroId)
    {
        try {
            Log::info('Obteniendo datos bancarios para el registro ID: ' . $repartoRegistroId);
            
            // Verificar que el registro existe
            $repartoRegistro = RepartoRegistro::findOrFail($repartoRegistroId);
            
            // Obtener los datos bancarios asociados
            $cuentaBancaria = CuentaBancariaReparto::where('reparto_registro_id', $repartoRegistroId)->first();
            
            if (!$cuentaBancaria) {
                return response()->json([
                    'message' => 'No se encontraron datos bancarios para este registro',
                    'datos_basicos' => [
                        'nombres' => $repartoRegistro->nombres,
                        'apellidos' => $repartoRegistro->apellidos,
                        'email' => $repartoRegistro->email,
                        'celular' => $repartoRegistro->celular,
                        'tipo_documento' => $repartoRegistro->tipo_documento,
                        'nro_documento' => $repartoRegistro->nro_documento,
                    ]
                ], 200);
            }
            
            // Obtener información del banco y tipo de cuenta
            $banco = Banco::find($cuentaBancaria->banco_id);
            $tipoCuenta = TipoCuentaBancaria::find($cuentaBancaria->tipo_cuenta_id);
            
            return response()->json([
                'cuenta_bancaria' => [
                    'id' => $cuentaBancaria->id,
                    'titular' => $cuentaBancaria->titular,
                    'dni' => $cuentaBancaria->dni,
                    'banco_id' => $cuentaBancaria->banco_id,
                    'banco_nombre' => $banco ? $banco->nombre : null,
                    'tipo_cuenta_id' => $cuentaBancaria->tipo_cuenta_id,
                    'tipo_cuenta_nombre' => $tipoCuenta ? $tipoCuenta->nombre : null,
                    'numero_cuenta' => $cuentaBancaria->numero_cuenta,
                    'url_imagen_cuenta' => $cuentaBancaria->url_imagen_cuenta,
                ],
                'datos_basicos' => [
                    'nombres' => $repartoRegistro->nombres,
                    'apellidos' => $repartoRegistro->apellidos,
                    'email' => $repartoRegistro->email,
                    'celular' => $repartoRegistro->celular,
                    'tipo_documento' => $repartoRegistro->tipo_documento,
                    'nro_documento' => $repartoRegistro->nro_documento,
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener datos bancarios: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los datos bancarios',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info('Actualizando datos bancarios ID: ' . $id, $request->all());
        
        $validator = Validator::make($request->all(), [
            'titular' => 'required|string|max:255',
            'dni' => 'required|digits:8',
            'banco_id' => 'required|exists:bancos,id',
            'tipo_cuenta_id' => 'required|exists:tipos_cuenta_bancaria,id',
            'numero_cuenta' => 'required|string|max:50',
            'imagen_cuenta' => 'nullable|file|mimes:jpeg,png,pdf|max:4096',
        ]);
        
        if ($validator->fails()) {
            Log::error('Validación fallida en actualización:', $validator->errors()->toArray());
            return response()->json(['errores' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        
        try {
            // Buscar el registro de cuenta bancaria
            $cuentaBancaria = CuentaBancariaReparto::findOrFail($id);
            
            // Datos a actualizar
            $datosActualizar = [
                'titular' => $request->titular,
                'dni' => $request->dni,
                'banco_id' => $request->banco_id,
                'tipo_cuenta_id' => $request->tipo_cuenta_id,
                'numero_cuenta' => $request->numero_cuenta,
            ];
            
            // Si se envió una nueva imagen, actualizarla
            if ($request->hasFile('imagen_cuenta')) {
                // Generar un nombre único para la imagen
                $nombreImagen = time() . '_' . $request->file('imagen_cuenta')->getClientOriginalName();
                
                // Guardar la imagen directamente en la carpeta public
                $rutaImagen = $request->file('imagen_cuenta')->move(public_path('storage/cuentas_bancarias'), $nombreImagen);
        
                // Generar la URL pública de la imagen
                $urlImagen = asset('storage/cuentas_bancarias/' . $nombreImagen);
                
                // Eliminar la imagen anterior si existe
                if ($cuentaBancaria->url_imagen_cuenta) {
                    // Extraer el nombre del archivo de la URL
                    $rutaAnterior = public_path('storage/cuentas_bancarias/' . basename($cuentaBancaria->url_imagen_cuenta));
                    if (file_exists($rutaAnterior)) {
                        unlink($rutaAnterior);
                    }
                }
                
                $datosActualizar['url_imagen_cuenta'] = $urlImagen;
            }
            
            // Actualizar los datos
            $cuentaBancaria->update($datosActualizar);
            
            DB::commit();
            
            Log::info('Datos bancarios actualizados:', $cuentaBancaria->toArray());
            
            return response()->json([
                'mensaje' => 'Datos bancarios actualizados correctamente',
                'cuenta_bancaria' => $cuentaBancaria
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Si algo falla, eliminamos la imagen si se subió
            if (isset($rutaImagen) && file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
            
            Log::error('Error al actualizar datos bancarios: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar los datos bancarios',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function guardarCuentaBancaria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reparto_registro_id' => 'required|exists:reparto_registros,id',
            'titular' => 'required|string|max:255',
            'dni' => 'required|digits:8',
            'banco_id' => 'required|exists:bancos,id',
            'tipo_cuenta_id' => 'required|exists:tipos_cuenta_bancaria,id',
            'numero_cuenta' => 'required|string|max:50',
            'imagen_cuenta' => 'required|file|mimes:jpeg,png,pdf|max:4096',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }
    
        DB::beginTransaction();
        
        try {
            // Verificar si ya existe una cuenta bancaria para este repartidor
            $existingData = CuentaBancariaReparto::where('reparto_registro_id', $request->reparto_registro_id)->first();
            
            if ($existingData) {
                // Si ya existe, actualizar en lugar de crear
                return $this->update($request, $existingData->id);
            }
            
            // Generar un nombre único para la imagen
            $nombreImagen = time() . '_' . $request->file('imagen_cuenta')->getClientOriginalName();
            
            // Guardar la imagen directamente en la carpeta public
            $rutaImagen = $request->file('imagen_cuenta')->move(public_path('storage/cuentas_bancarias'), $nombreImagen);
    
            // Generar la URL pública de la imagen
            $urlImagen = asset('storage/cuentas_bancarias/' . $nombreImagen);
    
            // Crear y guardar la cuenta bancaria
            $cuentaBancaria = CuentaBancariaReparto::create([
                'reparto_registro_id' => $request->reparto_registro_id,
                'titular' => $request->titular,
                'dni' => $request->dni,
                'banco_id' => $request->banco_id,
                'tipo_cuenta_id' => $request->tipo_cuenta_id,
                'numero_cuenta' => $request->numero_cuenta,
                'url_imagen_cuenta' => $urlImagen // Guardamos la URL pública
            ]);
            
            DB::commit();
    
            return response()->json([
                'mensaje' => 'Cuenta bancaria guardada con éxito',
                'cuenta_bancaria' => $cuentaBancaria
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Si algo falla, eliminamos la imagen si se subió
            if (isset($rutaImagen) && file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
    
            return response()->json([
                'mensaje' => 'Error al guardar la cuenta bancaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

