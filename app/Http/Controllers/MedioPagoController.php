<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use Illuminate\Http\Request;

class MedioPagoController extends Controller
{
    public function index()
    {
        $mediosPago = MedioPago::where('estado', 1)
            ->get(['id', 'nombre']);
        return response()->json($mediosPago, 200);
    }
}
