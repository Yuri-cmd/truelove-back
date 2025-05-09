<?php
// app/Http/Controllers/HorarioController.php
namespace App\Http\Controllers;

use App\Models\HorarioGrupo;
use App\Models\HorarioAsignacion;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HorarioController extends Controller
{
    // Obtener todos los grupos de horarios con sus motorizados asignados
    public function getAllGrupos()
    {
        try {
            $grupos = HorarioGrupo::with([
                'motorizados' => function ($query) {
                    $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
                }
            ])->get();

            return response()->json([
                'status' => 'success',
                'data' => $grupos
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener grupos de horarios: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los grupos de horarios: ' . $e->getMessage()
            ], 500);
        }
    }

    // Obtener un grupo específico con sus motorizados
    public function getGrupo($id)
    {
        try {
            $grupo = HorarioGrupo::with([
                'motorizados' => function ($query) {
                    $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
                }
            ])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener grupo de horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el grupo de horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Crear un nuevo grupo de horarios
    public function createGrupo(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validar datos
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'rangos' => 'required|array|min:1',
                'rangos.*.dia_semana' => 'required',
                'rangos.*.hora_inicio' => 'required|date_format:H:i',
                'rangos.*.hora_fin' => 'required|date_format:H:i|after:rangos.*.hora_inicio',
                'motorizados' => 'nullable|array',
                'motorizados.*' => 'exists:reparto_registros,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            // validación 
            foreach ($request->rangos as $index => $rango) {
                $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

                // Si es un string, validar que sea uno de los valores permitidos
                if (is_string($rango['dia_semana'])) {
                    if (!in_array($rango['dia_semana'], $diasValidos) && $rango['dia_semana'] !== 'todos') {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Datos inválidos',
                            'errors' => ['rangos.' . $index . '.dia_semana' => ['El día de la semana no es válido']]
                        ], 422);
                    }
                }
                // Si es un array, validar que todos los elementos sean valores permitidos
                else if (is_array($rango['dia_semana'])) {
                    foreach ($rango['dia_semana'] as $dia) {
                        if (!in_array($dia, $diasValidos)) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Datos inválidos',
                                'errors' => ['rangos.' . $index . '.dia_semana' => ['El día de la semana no es válido']]
                            ], 422);
                        }
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Datos inválidos',
                        'errors' => ['rangos.' . $index . '.dia_semana' => ['Formato de día de la semana no válido']]
                    ], 422);
                }
            }

            // Crear grupo
            $grupo = HorarioGrupo::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'rangos' => $request->rangos
            ]);

            // Asignar motorizados si se proporcionaron
            if ($request->has('motorizados') && is_array($request->motorizados)) {
                foreach ($request->motorizados as $motorizadoId) {
                    HorarioAsignacion::create([
                        'grupo_id' => $grupo->id,
                        'motorizado_id' => $motorizadoId
                    ]);
                }
            }

            DB::commit();

            // Cargar el grupo con sus relaciones
            $grupo = HorarioGrupo::with([
                'motorizados' => function ($query) {
                    $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
                }
            ])->find($grupo->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Grupo de horario creado exitosamente',
                'data' => $grupo
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear grupo de horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el grupo de horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Actualizar un grupo de horarios
    public function updateGrupo(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Validar datos
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'rangos' => 'required|array|min:1',
                'rangos.*.dia_semana' => 'required',
                'rangos.*.hora_inicio' => 'required|date_format:H:i',
                'rangos.*.hora_fin' => 'required|date_format:H:i|after:rangos.*.hora_inicio',
                'motorizados' => 'nullable|array',
                'motorizados.*' => 'exists:reparto_registros,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->rangos as $index => $rango) {
                $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

                // Si es un string, validar que sea uno de los valores permitidos
                if (is_string($rango['dia_semana'])) {
                    if (!in_array($rango['dia_semana'], $diasValidos) && $rango['dia_semana'] !== 'todos') {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Datos inválidos',
                            'errors' => ['rangos.' . $index . '.dia_semana' => ['El día de la semana no es válido']]
                        ], 422);
                    }
                }
                // Si es un array, validar que todos los elementos sean valores permitidos
                else if (is_array($rango['dia_semana'])) {
                    foreach ($rango['dia_semana'] as $dia) {
                        if (!in_array($dia, $diasValidos)) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Datos inválidos',
                                'errors' => ['rangos.' . $index . '.dia_semana' => ['El día de la semana no es válido']]
                            ], 422);
                        }
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Datos inválidos',
                        'errors' => ['rangos.' . $index . '.dia_semana' => ['Formato de día de la semana no válido']]
                    ], 422);
                }
            }

            // Buscar el grupo
            $grupo = HorarioGrupo::findOrFail($id);

            // Actualizar datos básicos
            $grupo->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'rangos' => $request->rangos
            ]);

            // Actualizar asignaciones de motorizados
            if ($request->has('motorizados')) {
                // Eliminar asignaciones existentes
                HorarioAsignacion::where('grupo_id', $id)->delete();

                // Crear nuevas asignaciones
                foreach ($request->motorizados as $motorizadoId) {
                    HorarioAsignacion::create([
                        'grupo_id' => $id,
                        'motorizado_id' => $motorizadoId
                    ]);
                }
            }

            DB::commit();

            // Cargar el grupo actualizado con sus relaciones
            $grupo = HorarioGrupo::with([
                'motorizados' => function ($query) {
                    $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
                }
            ])->find($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Grupo de horario actualizado exitosamente',
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar grupo de horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el grupo de horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Eliminar un grupo de horarios
    public function deleteGrupo($id)
    {
        DB::beginTransaction();
        try {
            $grupo = HorarioGrupo::findOrFail($id);

            // Las eliminaciones en cascada se manejarán automáticamente por las restricciones de clave foránea
            $grupo->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grupo de horario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar grupo de horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el grupo de horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Obtener todos los motorizados disponibles
    public function getMotorizadosDisponibles()
    {
        try {
            $motorizados = RepartoRegistro::where('estado', 1)
                ->where('aprobado', 1)
                ->select('id', 'nombres', 'apellidos', 'celular', 'email')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $motorizados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener motorizados disponibles: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener motorizados disponibles: ' . $e->getMessage()
            ], 500);
        }
    }
}