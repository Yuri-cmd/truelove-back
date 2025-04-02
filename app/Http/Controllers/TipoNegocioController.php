<?php

namespace App\Http\Controllers;

use App\Models\TipoNegocio;
use Illuminate\Http\Request;

class TipoNegocioController extends Controller
{
    public function getAll()
    {
        return response()->json(TipoNegocio::where('activo', 1)->take(6)->get());
    }
}
