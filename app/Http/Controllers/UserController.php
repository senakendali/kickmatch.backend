<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function countUsersWithRole()
    {
        $count = User::where('role_id', 3)->count();

        return response()->json([
            'total_users' => $count
        ], 200);
    }
}
