<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        // Get the user's group_id
        $groupId = $user->group_id;

        // Retrieve the role_name by joining group_role and roles tables
        $roleName = DB::table('group_role')
            ->join('roles', 'group_role.role_id', '=', 'roles.id')
            ->where('group_role.user_group_id', $groupId)
            ->value('roles.name'); // Assuming 'name' is the column in the roles table that holds the role name

        if (!$roleName) {
            return response()->json([
                'message' => 'No role associated with the user\'s group.',
            ], 404);
        }

        // Retrieve menus associated with the role_name using LIKE
        $menus = DB::table('navigation_menus')
            ->where('role_name', 'like', '%' . $roleName . '%')
            ->get();

        return response()->json($menus, 200);
    }


}

