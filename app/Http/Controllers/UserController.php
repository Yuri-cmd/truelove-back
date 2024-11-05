<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function all()
    {
        return response()->json(User::all());
    }

    public function changeState($id)
    {
        $user = User::find($id);
        $user->estado =  $user->estado == 1 ? 0 : 1;
        $user->save();
        return response()->json(201);
    }

    public function store(Request $request)
    {
        $item = User::create($request->all());

        return response()->json($item);
    }
}
