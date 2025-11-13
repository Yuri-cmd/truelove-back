<?php
namespace App\Http\Controllers;

use App\Models\HorarioGrupo;
use App\Models\HorarioBloque;
use App\Models\HorarioAsignacion;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HorarioController extends Controller
{
    // Obtener todos los horarios (grupales e individuales)
    public function getAllHorarios()
    {
        try {
            $horarios = HorarioGrupo::with([
                'bloques' => function($query) {
                    $query->orderBy('orden');
                },
                'motorizados' => function ($query) {
                    $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
                },
                'motorizadoIndividual' => function($query) {
                    $query->select('id', 'nombres', 'apellidos', 'celular', 'email');
                }
            ])->get();

            return response()->json([
                'status' => 'success',
                'data' => $horarios
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los horarios: ' . $e->getMessage()
            ], 500);
        }
    }

    // Crear horario (grupal o individual)
    public function createHorario(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'tipo' => 'required|in:grupal,individual',
                'motorizado_individual_id' => 'required_if:tipo,individual|exists:reparto_registros,id',
                'bloques' => 'required|array|min:1',
                'bloques.*.dia_semana' => 'required',
                'bloques.*.hora_inicio' => 'required|date_format:H:i',
                'bloques.*.hora_fin' => 'required|date_format:H:i',
                'bloques.*.tipo' => 'required|in:trabajo,descanso,almuerzo',
                'bloques.*.descripcion' => 'nullable|string',
                'bloques.*.color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'motorizados' => 'required_if:tipo,grupal|array',
                'motorizados.*' => 'exists:reparto_registros,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validar que no haya solapamiento de horarios para el mismo día
            $this->validarSolapamientoBloques($request->bloques);

            // Crear horario
            $horario = HorarioGrupo::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'motorizado_individual_id' => $request->tipo === 'individual' ? $request->motorizado_individual_id : null,
               
            ]);

            // Crear bloques de horario
            foreach ($request->bloques as $index => $bloque) {
                HorarioBloque::create([
                    'grupo_id' => $horario->id,
                    'dia_semana' => $bloque['dia_semana'],
                    'hora_inicio' => $bloque['hora_inicio'],
                    'hora_fin' => $bloque['hora_fin'],
                    'tipo' => $bloque['tipo'],
                    'descripcion' => $bloque['descripcion'] ?? null,
                    'color' => $bloque['color'] ?? $this->getDefaultColor($bloque['tipo']),
                    'orden' => $index
                ]);
            }

            // Asignar motorizados (solo para horarios grupales)
            if ($request->tipo === 'grupal' && $request->has('motorizados')) {
                foreach ($request->motorizados as $motorizadoId) {
                    HorarioAsignacion::create([
                        'grupo_id' => $horario->id,
                        'motorizado_id' => $motorizadoId
                    ]);
                }
            }

            DB::commit();

            // Cargar horario completo
            $horario = $this->getHorarioCompleto($horario->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Horario creado exitosamente',
                'data' => $horario
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Actualizar horario
    public function updateHorario(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'bloques' => 'required|array|min:1',
                'bloques.*.dia_semana' => 'required',
                'bloques.*.hora_inicio' => 'required|date_format:H:i',
                'bloques.*.hora_fin' => 'required|date_format:H:i',
                'bloques.*.tipo' => 'required|in:trabajo,descanso,almuerzo',
                'bloques.*.descripcion' => 'nullable|string',
                'bloques.*.color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
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

            $horario = HorarioGrupo::findOrFail($id);

            // Validar solapamiento
            $this->validarSolapamientoBloques($request->bloques);

            // Actualizar datos básicos
            $horario->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion
            ]);

            // Eliminar bloques existentes y crear nuevos
            HorarioBloque::where('grupo_id', $id)->delete();
            
            foreach ($request->bloques as $index => $bloque) {
                HorarioBloque::create([
                    'grupo_id' => $id,
                    'dia_semana' => $bloque['dia_semana'],
                    'hora_inicio' => $bloque['hora_inicio'],
                    'hora_fin' => $bloque['hora_fin'],
                    'tipo' => $bloque['tipo'],
                    'descripcion' => $bloque['descripcion'] ?? null,
                    'color' => $bloque['color'] ?? $this->getDefaultColor($bloque['tipo']),
                    'orden' => $index
                ]);
            }

            // Actualizar asignaciones (solo para grupales)
            if ($horario->tipo === 'grupal' && $request->has('motorizados')) {
                HorarioAsignacion::where('grupo_id', $id)->delete();
                
                foreach ($request->motorizados as $motorizadoId) {
                    HorarioAsignacion::create([
                        'grupo_id' => $id,
                        'motorizado_id' => $motorizadoId
                    ]);
                }
            }

            DB::commit();

            $horario = $this->getHorarioCompleto($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Horario actualizado exitosamente',
                'data' => $horario
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // Obtener horarios grupales
    public function getHorariosGrupales()
    {
        try {
            $horarios = HorarioGrupo::where('tipo', 'grupal')
                ->with(['bloques', 'motorizados'])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $horarios
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios grupales: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener horarios grupales: ' . $e->getMessage()
            ], 500);
        }
    }

    // Obtener horarios individuales
    public function getHorariosIndividuales()
    {
        try {
            $horarios = HorarioGrupo::where('tipo', 'individual')
                ->with(['bloques', 'motorizadoIndividual'])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $horarios
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios individuales: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener horarios individuales: ' . $e->getMessage()
            ], 500);
        }
    }

    // Obtener horario de un motorizado específico
    public function getHorarioMotorizado($motorizadoId)
    {
        try {
            // Buscar horario individual
            $horarioIndividual = HorarioGrupo::where('tipo', 'individual')
                ->where('motorizado_individual_id', $motorizadoId)
                ->with('bloques')
                ->first();

            if ($horarioIndividual) {
                return response()->json([
                    'status' => 'success',
                    'data' => $horarioIndividual,
                    'tipo' => 'individual'
                ]);
            }

            // Buscar en horarios grupales
            $horarioGrupal = HorarioGrupo::whereHas('motorizados', function($query) use ($motorizadoId) {
                $query->where('reparto_registros.id', $motorizadoId);
            })->with('bloques')->first();

            if ($horarioGrupal) {
                return response()->json([
                    'status' => 'success',
                    'data' => $horarioGrupal,
                    'tipo' => 'grupal'
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'El motorizado no tiene horario asignado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener horario del motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener horario del motorizado: ' . $e->getMessage()
            ], 500);
        }
    }

    // NUEVO: Obtener todos los motorizados activos (para edición de horarios)
    public function getTodosMotorizados()
    {
        try {
            $motorizados = RepartoRegistro::where('estado', 1)
                ->where('aprobado', 1)
                ->select('id', 'nombres', 'apellidos', 'celular', 'email')
                ->orderBy('nombres')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $motorizados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener todos los motorizados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener todos los motorizados: ' . $e->getMessage()
            ], 500);
        }
    }

    // Función auxiliar para verificar si un horario cruza medianoche
    private function cruzaMedianoche($horaInicio, $horaFin)
    {
        $inicioMinutos = $this->horaAMinutos($horaInicio);
        $finMinutos = $this->horaAMinutos($horaFin);
        return $finMinutos < $inicioMinutos;
    }

    // Función auxiliar para calcular duración considerando cruce de medianoche
    private function calcularDuracion($horaInicio, $horaFin)
    {
        $inicioMinutos = $this->horaAMinutos($horaInicio);
        $finMinutos = $this->horaAMinutos($horaFin);
        
        if ($this->cruzaMedianoche($horaInicio, $horaFin)) {
            // Si cruza medianoche: (24:00 - inicio) + fin
            return (24 * 60 - $inicioMinutos) + $finMinutos;
        }
        
        return $finMinutos - $inicioMinutos;
    }

    // Función auxiliar para verificar solapamiento considerando cruce de medianoche
    private function verificarSolapamiento($bloque1, $bloque2)
    {
        $inicio1 = $this->horaAMinutos($bloque1['inicio']);
        $fin1 = $this->horaAMinutos($bloque1['fin']);
        $inicio2 = $this->horaAMinutos($bloque2['inicio']);
        $fin2 = $this->horaAMinutos($bloque2['fin']);

        $cruza1 = $this->cruzaMedianoche($bloque1['inicio'], $bloque1['fin']);
        $cruza2 = $this->cruzaMedianoche($bloque2['inicio'], $bloque2['fin']);

        if (!$cruza1 && !$cruza2) {
            // Ninguno cruza medianoche - validación normal
            return $fin1 > $inicio2 && $fin2 > $inicio1;
        } elseif ($cruza1 && !$cruza2) {
            // Solo el primero cruza medianoche
            return ($fin1 > $inicio2) || ($inicio1 <= $fin2);
        } elseif (!$cruza1 && $cruza2) {
            // Solo el segundo cruza medianoche
            return ($fin2 > $inicio1) || ($inicio2 <= $fin1);
        } else {
            // Ambos cruzan medianoche - siempre se solapan
            return true;
        }
    }

    // Métodos auxiliares
    private function validarSolapamientoBloques($bloques)
    {
        $bloquesPorDia = [];
        
        foreach ($bloques as $bloque) {
            $dias = is_array($bloque['dia_semana']) ? $bloque['dia_semana'] : [$bloque['dia_semana']];
            
            // Validar duración mínima y máxima
            $duracion = $this->calcularDuracion($bloque['hora_inicio'], $bloque['hora_fin']);

            if ($duracion < 15) {
                throw new \Exception("La duración del bloque es demasiado corta: {$duracion} minutos (mínimo 15 minutos)");
            }

            if ($duracion > 24 * 60) {
                throw new \Exception("La duración del bloque es demasiado larga: " . floor($duracion / 60) . "h " . ($duracion % 60) . "min (máximo 24 horas)");
            }
            
            foreach ($dias as $dia) {
                if (!isset($bloquesPorDia[$dia])) {
                    $bloquesPorDia[$dia] = [];
                }
                $bloquesPorDia[$dia][] = [
                    'inicio' => $bloque['hora_inicio'],
                    'fin' => $bloque['hora_fin'],
                    'tipo' => $bloque['tipo'] ?? 'trabajo'
                ];
            }
        }

        // Verificar solapamientos por día
        foreach ($bloquesPorDia as $dia => $bloquesDelDia) {
            for ($i = 0; $i < count($bloquesDelDia) - 1; $i++) {
                for ($j = $i + 1; $j < count($bloquesDelDia); $j++) {
                    if ($this->verificarSolapamiento($bloquesDelDia[$i], $bloquesDelDia[$j])) {
                        $duracion1 = $this->calcularDuracion($bloquesDelDia[$i]['inicio'], $bloquesDelDia[$i]['fin']);
                        $duracion2 = $this->calcularDuracion($bloquesDelDia[$j]['inicio'], $bloquesDelDia[$j]['fin']);
                        
                        throw new \Exception("Hay solapamiento de horarios en el día $dia: " .
                            "Bloque {$bloquesDelDia[$i]['tipo']} ({$bloquesDelDia[$i]['inicio']}-{$bloquesDelDia[$i]['fin']}, " .
                            floor($duracion1 / 60) . "h " . ($duracion1 % 60) . "min) se superpone con " .
                            "Bloque {$bloquesDelDia[$j]['tipo']} ({$bloquesDelDia[$j]['inicio']}-{$bloquesDelDia[$j]['fin']}, " .
                            floor($duracion2 / 60) . "h " . ($duracion2 % 60) . "min)");
                    }
                }
            }
        }
    }

    private function horaAMinutos($hora) 
    {
        $partes = explode(':', $hora);
        return (int)$partes[0] * 60 + (int)$partes[1];
    }

    private function getDefaultColor($tipo)
    {
        switch ($tipo) {
            case 'trabajo': return '#3B82F6'; // Azul
            case 'descanso': return '#F59E0B'; // Amarillo
            case 'almuerzo': return '#10B981'; // Verde
            default: return '#6B7280'; // Gris
        }
    }

    private function getHorarioCompleto($id)
    {
        return HorarioGrupo::with([
            'bloques' => function($query) {
                $query->orderBy('orden');
            },
            'motorizados' => function ($query) {
                $query->select('reparto_registros.id', 'nombres', 'apellidos', 'celular', 'email');
            },
            'motorizadoIndividual' => function($query) {
                $query->select('id', 'nombres', 'apellidos', 'celular', 'email');
            }
        ])->find($id);
    }

    // Eliminar horario
    public function deleteHorario($id)
    {
        DB::beginTransaction();
        try {
            $horario = HorarioGrupo::findOrFail($id);
            $horario->delete(); // Las eliminaciones en cascada se manejan automáticamente

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Horario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el horario: ' . $e->getMessage()
            ], 500);
        }
    }

    // MEJORADO: Obtener motorizados disponibles (sin horario asignado) + los ya asignados al horario actual
    public function getMotorizadosDisponibles(Request $request)
    {
        try {
            $horarioId = $request->query('horario_id'); // Parámetro opcional
            
            $query = RepartoRegistro::where('estado', 1)
                ->where('aprobado', 1);

            if ($horarioId) {
                // Si estamos editando, incluir motorizados ya asignados a este horario + los disponibles
                $query->where(function($q) use ($horarioId) {
                    // Motorizados disponibles (sin horario)
                    $q->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                              ->from('horario_asignaciones')
                              ->whereRaw('horario_asignaciones.motorizado_id = reparto_registros.id');
                    })
                    ->whereNotExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                              ->from('horario_grupos')
                              ->whereRaw('horario_grupos.motorizado_individual_id = reparto_registros.id')
                              ->where('horario_grupos.tipo', 'individual');
                    })
                    // O motorizados ya asignados a este horario específico
                    ->orWhereExists(function($subQuery) use ($horarioId) {
                        $subQuery->select(DB::raw(1))
                              ->from('horario_asignaciones')
                              ->whereRaw('horario_asignaciones.motorizado_id = reparto_registros.id')
                              ->where('horario_asignaciones.grupo_id', $horarioId);
                    })
                    ->orWhere(function($subQuery) use ($horarioId) {
                        $subQuery->whereExists(function($q) use ($horarioId) {
                            $q->select(DB::raw(1))
                              ->from('horario_grupos')
                              ->whereRaw('horario_grupos.motorizado_individual_id = reparto_registros.id')
                              ->where('horario_grupos.tipo', 'individual')
                              ->where('horario_grupos.id', $horarioId);
                        });
                    });
                });
            } else {
                // Si estamos creando, solo motorizados disponibles
                $query->whereNotExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                          ->from('horario_asignaciones')
                          ->whereRaw('horario_asignaciones.motorizado_id = reparto_registros.id');
                })
                ->whereNotExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                          ->from('horario_grupos')
                          ->whereRaw('horario_grupos.motorizado_individual_id = reparto_registros.id')
                          ->where('horario_grupos.tipo', 'individual');
                });
            }
            
            $motorizados = $query->select('id', 'nombres', 'apellidos', 'celular', 'email')
                ->orderBy('nombres')
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

    // Obtener un horario específico por ID
    public function getHorario($id)
    {
        try {
            $horario = $this->getHorarioCompleto($id);

            if (!$horario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Horario no encontrado'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $horario
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener horario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el horario: ' . $e->getMessage()
            ], 500);
        }
    }
}