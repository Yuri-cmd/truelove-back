<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;

class SocioController extends Controller
{
    public function all()
    {
        return response()->json(BusinessRegistration::all());
    }

    public function changeState($id)
    {
        $socio = BusinessRegistration::find($id);
        $socio->estado =  $socio->estado == 1 ? 0 : 1;
        $socio->save();
        return response()->json(201);
    }
}
