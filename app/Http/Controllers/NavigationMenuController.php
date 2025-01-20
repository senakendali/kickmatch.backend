<?php

namespace App\Http\Controllers;

use App\Models\NavigationMenu;
use Illuminate\Http\Request;

class NavigationMenuController extends Controller
{
    // Get all menus, filtered by type (optional)
    public function index(Request $request)
    {
        // Set the number of items per page to 10
        $menus = NavigationMenu::when($request->has('type'), function ($query) use ($request) {
            return $query->where('type', $request->type);
        })->orderBy('order')->paginate(10); // Paginate 10 items per page

        return response()->json($menus);

    
    }

    public function fetchAllNavigation()
    {
        try {
            // Fetch all menus including parent relationships
            $menus = NavigationMenu::with('parent')->get();

            return response()->json($menus, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch menus.'], 500);
        }
    }


    // Get a specific menu
    public function show($id)
    {
        $menu = NavigationMenu::findOrFail($id);

        return response()->json($menu);
    }

    // Create a new menu
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|string', // The URL field is optional
            'parent_id' => 'nullable|exists:navigation_menus,id',
            'order' => 'nullable|integer',
            'type' => 'required|in:public,admin',
        ]);

        // Default the URL to a slug of the name if not provided
        $data = $request->all();
        $data['url'] = $data['url'] ?? \Illuminate\Support\Str::slug($data['name']);

        $menu = NavigationMenu::create($data);

        return response()->json($menu, 201);
    }

    // Update an existing menu
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|string',
            'parent_id' => 'nullable|exists:navigation_menus,id',
            'order' => 'nullable|integer',
            'type' => 'required|in:public,admin',
        ]);

        $menu = NavigationMenu::findOrFail($id);
        $menu->update($request->all());

        return response()->json($menu);
    }

    // Delete a menu
    public function destroy($id)
    {
        $menu = NavigationMenu::findOrFail($id);
        $menu->delete();

        return response()->json(null, 204);
    }
}

