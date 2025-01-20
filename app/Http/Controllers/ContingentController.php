<?php

namespace App\Http\Controllers;

use App\Models\Contingent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ContingentController extends Controller
{
    // Fetch all contingents
    public function index()
    {
        try {
            $user = auth()->user(); // Mendapatkan user yang sedang login
        
            // Pastikan eager loading untuk menghindari lazy loading
            $user->load('group'); 
        
            if ($user->group && $user->group->name === 'Owner') {
                $contingents = Contingent::paginate(10); // Default: tidak ada filter
                
            } else {
                $contingents = Contingent::where('owner_id', $user->id)->paginate(10);
            }
        
            return response()->json($contingents, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
    }

    public function fetchAll(){
        try {
            $user = auth()->user(); // Mendapatkan user yang sedang login
        
            // Pastikan eager loading untuk menghindari lazy loading
            $user->load('group'); 
        
            if ($user->group && $user->group->name === 'Owner') {
                $contingents = Contingent::all(); // Default: tidak ada filter
                
            } else {
                $contingents = Contingent::where('owner_id', $user->id)->get();
            }
        
            return response()->json($contingents, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkMyContingentsStatus(){
        try {
            $user = auth()->user(); // Get the logged-in user
        
            // Ensure eager loading to avoid lazy loading issues
            $user->load('group');
        
            // Fetch the relevant contingents based on owner
            $contingents = Contingent::where('owner_id', $user->id)->get();
        
            // Check if the contingents are already registered for a given tournament
            $tournamentId = request()->input('tournament_id');
            if ($tournamentId) {
                $contingents->each(function ($contingent) use ($tournamentId) {
                    // Add is_registered status to each contingent
                    $contingent->is_registered = \App\Models\TournamentContingent::where('tournament_id', $tournamentId)
                        ->where('contingent_id', $contingent->id)
                        ->exists();
                });
            }
        
            return response()->json($contingents, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Fetch a single contingent by ID
    public function show($id)
    {
        try {
            $contingent = Contingent::findOrFail($id);
            return response()->json($contingent, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Contingent not found.'], 404);
        }
    }

    // Store a new contingent
    public function store(Request $request)
    {
        // Retrieve owner_id from the authenticated user
        $ownerId = auth()->id();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'pic_name' => 'required|string|max:255',
            'pic_email' => 'required|email|max:255|unique:contingents,pic_email',
            'pic_phone' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'province_id' => 'required|exists:provinces,id',
            'district_id' => 'required|exists:districts,id',
            'subdistrict_id' => 'required|exists:subdistricts,id',
            'ward_id' => 'required|exists:wards,id',
            'address' => 'required|string|max:255',
            
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['owner_id'] = $ownerId; // Assign the authenticated user ID
            $data['status'] = 'active'; // Set the status to 'active'
            $contingent = Contingent::create($data);

            return response()->json(['data' => $contingent], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // Update an existing contingent
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:contingents,email,' . $id,
            'phone' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string|min:8',
            'pic_name' => 'sometimes|required|string|max:255',
            'pic_email' => 'sometimes|required|email|max:255|unique:contingents,pic_email,' . $id,
            'pic_phone' => 'sometimes|required|string|max:255',
            'province_id' => 'sometimes|required|exists:provinces,id',
            'district_id' => 'sometimes|required|exists:districts,id',
            'subdistrict_id' => 'sometimes|required|exists:subdistricts,id',
            'ward_id' => 'sometimes|required|exists:wards,id',
            'address' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:active,inactive,pending,disqualified',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $contingent = Contingent::findOrFail($id);
            $data = $request->all();

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $contingent->update($data);
            return response()->json(['data' => $contingent], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Delete a contingent
    public function destroy($id)
    {
        try {
            $contingent = Contingent::findOrFail($id);
            $contingent->delete();
            return response()->json(['message' => 'Contingent deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

