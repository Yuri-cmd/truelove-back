<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function all()
    {
        return response()->json(User::all());
    }

    public function changeState($id)
    {
        $user = User::find($id);
        $user->estado = !$user->estado;
        $user->save();
        return response()->json(201);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'usuario' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'usuario' => $request->usuario,
            'password' => Hash::make($request->password),
            'estado' => true,
            'role_id' => $request->role_id,
        ]);

        return response()->json($user, 201);
    }

    public function delete($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
    }
}

