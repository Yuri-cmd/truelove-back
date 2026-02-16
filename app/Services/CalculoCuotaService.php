<?php

namespace App\Services;

use App\Models\BusinessRegistration;
use App\Models\CuotaSocio;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\PeriodoCuotaSocio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculoCuotaService
{
    /**
     * Calcular la cuota/comisión para un período específico
     * 
     * @param int $periodoId
     * @return PeriodoCuotaSocio
     */
    public function calcularCuotaDelPeriodo($periodoId)
    {
        $periodo = PeriodoCuotaSocio::findOrFail($periodoId);
        $cuota = CuotaSocio::findOrFail($periodo->cuota_socio_id);
        $socio = BusinessRegistration::findOrFail($periodo->socio_id);
        
        // 1. CALCULAR VENTAS Y PEDIDOS DEL PERÍODO
        $resultado = $this->calcularVentasYPedidos($socio->id, $periodo->periodo_inicio, $periodo->periodo_fin);
        
        $totalVentas = $resultado['total_ventas'];
        $cantidadPedidos = $resultado['cantidad_pedidos'];
        
        // 2. CALCULAR MONTO SEGÚN TIPO DE CUOTA
        $montoCalculado = $this->calcularMontoPorTipoCuota(
            $cuota,
            $totalVentas,
            $cantidadPedidos
        );
        
        // 3. ACTUALIZAR PERÍODO CON LOS CÁLCULOS
        $periodo->update([
            'total_ventas' => $totalVentas,
            'cantidad_pedidos' => $cantidadPedidos,
            'monto_calculado' => $montoCalculado,
            'monto_esperado' => $montoCalculado,
            'fecha_calculo' => now(),
            // No cambiar el estado, solo mantener 'pendiente' hasta que se pague
        ]);
        
        Log::info("Cuota calculada para período {$periodoId}", [
            'socio_id' => $socio->id,
            'tipo_cuota' => $cuota->tipo_cuota,
            'total_ventas' => $totalVentas,
            'cantidad_pedidos' => $cantidadPedidos,
            'monto_calculado' => $montoCalculado
        ]);
        
        return $periodo->fresh();
    }
    
    /**
     * Calcular ventas y pedidos de un socio en un rango de fechas
     * Solo cuenta pedidos completados (estado = 8 en tracking)
     * 
     * @param int $socioId
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return array ['total_ventas' => float, 'cantidad_pedidos' => int]
     */
    public function calcularVentasYPedidos($socioId, $fechaInicio, $fechaFin)
    {
        // Buscar pedidos completados del socio en el rango de fechas
        $pedidosCompletados = Pedido::where('id_local', $socioId)
            ->whereBetween('created_at', [
                Carbon::parse($fechaInicio)->startOfDay(),
                Carbon::parse($fechaFin)->endOfDay()
            ])
            ->whereHas('trackings', function($query) {
                $query->where('estado', 8); // Estado 8 = Pedido completado/entregado
            })
            ->get();
        
        $cantidadPedidos = $pedidosCompletados->count();
        
        // Sumar el total de ventas (solo lo que vende el socio, sin delivery)
        $totalVentas = 0;
        foreach ($pedidosCompletados as $pedido) {
            $subtotalPedido = PedidoDetalle::where('pedido_id', $pedido->id)
                ->sum('precio');
            $totalVentas += $subtotalPedido;
        }
        
        return [
            'total_ventas' => round($totalVentas, 2),
            'cantidad_pedidos' => $cantidadPedidos
        ];
    }
    
    /**
     * Calcular monto según el tipo de cuota configurado
     * 
     * @param CuotaSocio $cuota
     * @param float $totalVentas
     * @param int $cantidadPedidos
     * @return float
     */
    private function calcularMontoPorTipoCuota($cuota, $totalVentas, $cantidadPedidos)
    {
        if ($cuota->tipo_cuota === 'monto_fijo') {
            // SISTEMA ACTUAL: Monto fijo predefinido
            return $cuota->monto_cuota;
        }
        
        if ($cuota->tipo_cuota === 'porcentaje') {
            return $this->calcularComisionPorPorcentaje($cuota, $totalVentas, $cantidadPedidos);
        }
        
        // Fallback: monto fijo
        return $cuota->monto_cuota;
    }
    
    /**
     * Calcular comisión por porcentaje con todas las variantes
     * 
     * @param CuotaSocio $cuota
     * @param float $totalVentas
     * @param int $cantidadPedidos
     * @return float
     */
    private function calcularComisionPorPorcentaje($cuota, $totalVentas, $cantidadPedidos)
    {
        // CASO 1: Verificar si debe exonerar por mínimo de pedidos
        if ($cuota->exonerar_si_menos_pedidos && $cuota->minimo_pedidos) {
            if ($cantidadPedidos < $cuota->minimo_pedidos) {
                Log::info("Socio exonerado por no alcanzar mínimo de pedidos", [
                    'cantidad_pedidos' => $cantidadPedidos,
                    'minimo_requerido' => $cuota->minimo_pedidos
                ]);
                return 0.00; // Exonerado, no paga
            }
        }
        
        // CASO 2: Calcular porcentaje sobre ventas
        $porcentajeDecimal = $cuota->porcentaje_comision / 100;
        $montoCalculado = $totalVentas * $porcentajeDecimal;
        
        // CASO 3: Aplicar monto mínimo (tipo mixto)
        if ($cuota->monto_minimo && $montoCalculado < $cuota->monto_minimo) {
            Log::info("Aplicando monto mínimo", [
                'monto_calculado' => $montoCalculado,
                'monto_minimo' => $cuota->monto_minimo
            ]);
            return $cuota->monto_minimo;
        }
        
        return round($montoCalculado, 2);
    }
    
    /**
     * Calcular cuotas para todos los períodos pendientes de un socio
     * 
     * @param int $socioId
     * @return array
     */
    public function calcularTodasLasCuotasPendientes($socioId)
    {
        $periodosPendientes = PeriodoCuotaSocio::where('socio_id', $socioId)
            ->whereIn('estado', ['pendiente', 'calculado'])
            ->get();
        
        $resultados = [];
        
        foreach ($periodosPendientes as $periodo) {
            try {
                $periodoActualizado = $this->calcularCuotaDelPeriodo($periodo->id);
                $resultados[] = [
                    'periodo_id' => $periodo->id,
                    'success' => true,
                    'monto_calculado' => $periodoActualizado->monto_calculado
                ];
            } catch (\Exception $e) {
                $resultados[] = [
                    'periodo_id' => $periodo->id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $resultados;
    }
    
    /**
     * Calcular cuotas para todos los socios de una cuota específica
     * 
     * @param int $cuotaId
     * @return array
     */
    public function calcularCuotasParaTodosLosSocios($cuotaId)
    {
        $socios = BusinessRegistration::where('cuota_socio_id', $cuotaId)
            ->where('aprobado', 1)
            ->where('estado', 1)
            ->get();
        
        $resultados = [];
        
        foreach ($socios as $socio) {
            $resultadosSocio = $this->calcularTodasLasCuotasPendientes($socio->id);
            $resultados[$socio->id] = [
                'socio' => $socio->name . ' ' . $socio->lastName,
                'resultados' => $resultadosSocio
            ];
        }
        
        return $resultados;
    }
}
