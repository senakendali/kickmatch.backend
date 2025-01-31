<?php

namespace App\Http\Controllers;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function getUserPermissions(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                throw new \Exception("User not authenticated.");
            }

            if (!$user->role_id) {
                throw new \Exception("No role assigned to user.");
            }

            // Fetch role using Eloquent instead of `belongsTo`
            $role = Role::with('permissions')->find($user->role_id);

            if (!$role) {
                throw new \Exception("Role ID {$user->role_id} not found in roles table.");
            }

            // Get permissions
            $permissions = $role->permissions->pluck('name');

            return response()->json($permissions);
        } catch (\Exception $e) {
            \Log::error('Error fetching user permissions: ' . $e->getMessage());

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }




}

