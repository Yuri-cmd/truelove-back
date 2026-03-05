<?php

namespace App\Services;

use App\Models\BusinessRegistration;
use App\Models\HorarioNegocio;
use App\Models\PerfilNegocio;
use Carbon\Carbon;

class NegocioService
{
    /**
     * Verifica si un local se encuentra abierto actualmente en base a sus horarios.
     * Retorna true si está abierto, false en caso contrario.
     *
     * @param int $idLocal ID del BusinessRegistration
     * @return bool
     */
    public function localEstaAbierto($idLocal)
    {
        $now = Carbon::now();
        $diaSemana = strtolower($now->format('l'));

        $diasMap = [
            'monday' => 'lunes',
            'tuesday' => 'martes',
            'wednesday' => 'miercoles',
            'thursday' => 'jueves',
            'friday' => 'viernes',
            'saturday' => 'sabado',
            'sunday' => 'domingo'
        ];

        $businessRegistration = BusinessRegistration::find($idLocal);
        
        if (!$businessRegistration || $businessRegistration->activo != 1) {
            return false;
        }

        $negocio = PerfilNegocio::where('business_registration_id', $idLocal)->first();
        
        if (!$negocio) {
            return true;
        }

        $horarios = HorarioNegocio::where('perfil_negocio_id', $negocio->id)
            ->where('activo', 1)
            ->get();

        if ($horarios->isEmpty()) {
            return true;
        }

        $estaAbierto = false; 
        $horaActual = $now->format('H:i:s');
        $columnaDia = $diasMap[$diaSemana];

        foreach ($horarios as $horario) {
            if ($horario->$columnaDia) {
                $inicio = $horario->hora_apertura;
                $fin = $horario->hora_cierre;

                if ($fin < $inicio) {
                    if ($horaActual >= $inicio || $horaActual <= $fin) {
                        $estaAbierto = true;
                        break;
                    }
                } else {
                    if ($horaActual >= $inicio && $horaActual <= $fin) {
                        $estaAbierto = true;
                        break;
                    }
                }
            }
        }

        return $estaAbierto;
    }
}
