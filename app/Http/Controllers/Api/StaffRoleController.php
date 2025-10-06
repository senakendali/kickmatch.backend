<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffRole;
use Illuminate\Http\Request;

class StaffRoleController extends Controller
{
    public function index(Request $request)
    {
        // Optional filter ?q=xxx
        $q = $request->query('q');

        $roles = StaffRole::query()
            ->when($q, fn($w) => $w->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get(['id','name','slug']);

        // Frontend kamu handle response.data?.data || response.data
        // Jadi kirim dalam key "data" biar aman:
        return response()->json([
            'data' => $roles,
        ]);
    }
}
