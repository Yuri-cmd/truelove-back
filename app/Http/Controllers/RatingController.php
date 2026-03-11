<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Rating;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        try {
            $rating = Rating::create([
                'id_pedido' => $request->id_pedido,
                'restaurant_rating' => $request->restaurant_rating,
                'restaurant_comment' => $request->restaurant_comment,
                'motorcycle_rating' => $request->motorcycle_rating,
                'motorcycle_comment' => $request->motorcycle_comment,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Calificación guardada con éxito',
                'rating' => $rating
            ], 201);
        } catch (\Exception $e) {
            \Log::error("Error guardando calificación: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar calificación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRatings($id_pedido)
    {
        $ratings = Rating::where('id_pedido', $id_pedido)->get();
        return response()->json($ratings);
    }

    public function getRatingsBiker($id)
    {
        // Obtener los pedidos del motorizado
        $pedidos = Pedido::where('id_motorizado', $id)->get();
        // Obtener los ratings con los datos del cliente
        $data = $pedidos->map(function ($pedido) {
            $rating = Rating::where('id_pedido', $pedido->id)->first(); // Obtener el rating del pedido
            $cliente = Cliente::find($pedido->id_cliente); // Obtener el cliente
            if ($cliente) {
                return [
                    'id_pedido' => $pedido->id,
                    'cliente' => [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre ?? '-',
                        'telefono' => $cliente->telefono ?? 'Sin teléfono',
                    ],
                    'rating' => $rating ? [
                        'motorcycle_rating' => $rating->motorcycle_rating ?? 0.0,
                        'motorcycle_comment' => $rating->motorcycle_comment ?? 'Sin comentarios',
                    ] : null, // Si no hay rating, devolver null
                ];
            }
            return null; // Retornar null explícitamente cuando no hay cliente
        })->filter()->values(); // Filtrar valores null y reindexar el array

        return response()->json($data);
    }

    public function getReviewData($idLocal)
    {
        $pedidos = Pedido::where('id_local', $idLocal)->get();
        $ratings = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];
        $ratingsByDate = [];
        $totalRatings = 0;

        foreach ($pedidos as $pedido) {
            $rating = Rating::where('id_pedido', $pedido->id)->first();

            if ($rating) {
                $ratings[$rating->restaurant_rating]++;

                // Contar ratings por fecha
                $date = Carbon::parse($pedido->created_at)->format('Y-m-d');
                if (!isset($ratingsByDate[$date])) {
                    $ratingsByDate[$date] = 0;
                }
                $ratingsByDate[$date]++;

                $totalRatings++;
            }
        }

        // Formato para gráficos
        $ratingsArray = [];
        foreach ($ratings as $star => $count) {
            $ratingsArray[] = ['star' => $star, 'count' => $count];
        }

        $ratingsByDateArray = [];
        foreach ($ratingsByDate as $date => $count) {
            $ratingsByDateArray[] = ['date' => $date, 'count' => $count];
        }

        return response()->json([
            'id' => $idLocal,
            'ratingCounts' => $ratingsArray,
            'ratingsByDate' => $ratingsByDateArray,
            'totalRatings' => $totalRatings
        ]);
    }

    public function heatmapData()
    {
        $data = DB::select("SELECT 
                HOUR(pedidos.created_at) AS hour,
                DAYNAME(pedidos.created_at) AS day,
                COUNT(*) AS orders,
                COALESCE(AVG(ratings.restaurant_rating), 0) AS rating
            FROM pedidos
            LEFT JOIN ratings ON ratings.id_pedido = pedidos.id
            WHERE pedidos.id_local = 7
            GROUP BY hour, day
            ORDER BY day, hour;");

        return response()->json(['data' => $data]);
    }

    public function getRatingEvolution(Request $request)
    {
        // Recibir el tipo de agrupación (daily, weekly, monthly)
        $groupBy = $request->query('group_by', 'daily'); // 'daily' por defecto

        // Determinar el formato de agrupación según el tipo
        if ($groupBy === 'daily') {
            $dateFormat = "DATE(pedidos.created_at)";
        } elseif ($groupBy === 'weekly') {
            $dateFormat = "DATE_FORMAT(pedidos.created_at, '%Y-%u')"; // Año-Semana
        } elseif ($groupBy === 'monthly') {
            $dateFormat = "DATE_FORMAT(pedidos.created_at, '%Y-%m')"; // Año-Mes
        } else {
            return response()->json(['error' => 'Invalid group_by parameter'], 400);
        }

        // Ejecutar la consulta con el formato correcto
        $query = DB::table('pedidos')
            ->leftJoin('ratings', 'ratings.id_pedido', '=', 'pedidos.id')
            ->selectRaw("$dateFormat as date_group, AVG(ratings.restaurant_rating) as average_rating")
            ->groupBy('date_group')
            ->orderBy('date_group', 'ASC')
            ->get();

        // Formatear la respuesta
        $data = $query->map(function ($item) {
            return [
                'date' => $item->date_group,
                'rating' => round($item->average_rating, 2) // Redondear a 2 decimales
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // Añadir al RatingController
    public function getTopClients()
    {
        try {
            // Obtener los clientes con más pedidos
            $topClients = DB::table('pedidos')
                ->join('clientes', 'pedidos.id_cliente', '=', 'clientes.id')
                ->select(
                    'clientes.id',
                    DB::raw('CONCAT(clientes.nombre, " ", clientes.apellido) as nombre'),
                    DB::raw('COUNT(pedidos.id) as total_pedidos')
                )
                ->groupBy('clientes.id', 'clientes.nombre', 'clientes.apellido')
                ->orderBy('total_pedidos', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $topClients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los clientes top: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTopStores()
    {
        try {
            // Obtener los locales con más pedidos
            $topStores = DB::table('pedidos')
                ->join('establecimientos', 'pedidos.id_local', '=', 'establecimientos.business_registration_id')
                ->select(
                    'establecimientos.id',
                    'establecimientos.nombre_establecimiento as nombre',
                    DB::raw('COUNT(pedidos.id) as total_pedidos'),
                    DB::raw('AVG(ratings.restaurant_rating) as puntuacion')
                )
                ->leftJoin('ratings', function ($join) {
                    $join->on('ratings.id_pedido', '=', 'pedidos.id');
                })
                ->groupBy('establecimientos.id', 'establecimientos.nombre_establecimiento')
                ->orderBy('total_pedidos', 'desc')
                ->limit(10)
                ->get();

            // Ahora añadimos el logo a cada local
            foreach ($topStores as $store) {
                // Buscar el perfil del negocio para obtener el logo
                $business = BusinessRegistration::with('perfil')
                    ->find($store->id);

                $logo = null;
                if ($business && isset($business->perfil) && $business->perfil->ruta_logo) {
                    $logo = '/' . ltrim($business->perfil->ruta_logo, '/');
                }

                $store->logo = $logo;
            }

            return response()->json([
                'status' => 'success',
                'data' => $topStores
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los locales top: ' . $e->getMessage()
            ], 500);
        }
    }
}
