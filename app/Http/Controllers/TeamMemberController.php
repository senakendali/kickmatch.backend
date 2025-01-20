<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TeamMemberController extends Controller
{
    public function index()
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            // Ensure the user is authenticated
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Determine if the user is an owner
            if ($user->group->name === 'Owner') {
                // Fetch all members without filtering by owner_id
                $members = TeamMember::with('contingent')->paginate(10);
            } else {
                // Fetch members filtered by the user's owner_id
                $members = TeamMember::with('contingent')
                    ->whereHas('contingent', function ($query) use ($user) {
                        $query->where('owner_id', $user->id);
                    })
                    ->paginate(10);
            }

            return response()->json($members, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function store(Request $request)
    {
       
        $data = $request->validate([
            'contingent_id' => 'required|exists:contingents,id',
            'name' => 'required|string',
            'birth_place' => 'required|string',
            'birth_date' => 'required|date',
            'gender' => 'required|string',
            'body_weight' => 'nullable|numeric',
            'body_height' => 'nullable|numeric',
            'blood_type' => 'nullable|string',
            'nik' => 'required|string',
            'family_card_number' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'province_id' => 'required|exists:provinces,id',
            'district_id' => 'required|exists:districts,id',
            'subdistrict_id' => 'required|exists:subdistricts,id',
            'ward_id' => 'required|exists:wards,id',
            'address' => 'required|string',
            'category' => 'required|in:Tanding,Seni,Olahraga',
            'documents' => 'required|string',
        ]);

        // Create team member
        $teamMember = TeamMember::create($data);

        return response()->json($teamMember, 201);
    }


    public function show($id)
    {
        return TeamMember::findOrFail($id);
    }

    

    public function update(Request $request, $id)
    {
        // Log the request data
       
        Log::info('Update request data:', ['data' => $request->all()]);

        // Validate the request data
        $data = $request->validate([
            'contingent_id' => 'required|exists:contingents,id',
            'name' => 'required|string',
            'birth_place' => 'required|string',
            'birth_date' => 'required|date',
            'gender' => 'required|in:M,F',
            'body_weight' => 'nullable|numeric',
            'body_height' => 'nullable|numeric',
            'nik' => 'required|string|max:16',
            'family_card_number' => 'required|string|max:16',
            'country_id' => 'required|exists:countries,id',
            'province_id' => 'required|exists:provinces,id',
            'district_id' => 'required|exists:districts,id',
            'subdistrict_id' => 'required|exists:subdistricts,id',
            'ward_id' => 'required|exists:wards,id',
            'address' => 'required|string',
            'category' => 'required|in:Tanding,Seni',
            'documents' => 'required|string',
        ]);

        // Find the existing member
        $member = TeamMember::findOrFail($id);

        // Update base attributes
        $member->update([
            'contingent_id' => $data['contingent_id'],
            'name' => $data['name'],
            'birth_place' => $data['birth_place'],
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'body_weight' => $data['body_weight'],
            'body_height' => $data['body_height'],
            'nik' => $data['nik'],
            'family_card_number' => $data['family_card_number'],
            'country_id' => $data['country_id'],
            'province_id' => $data['province_id'],
            'district_id' => $data['district_id'],
            'subdistrict_id' => $data['subdistrict_id'],
            'ward_id' => $data['ward_id'],
            'address' => $data['address'],
            'category' => $data['category'],
            'documents' => $data['documents'],
        ]); 

        // Return success response
        return response()->json([
            'message' => 'Member updated successfully!',
            'data' => $member,
        ], 200);
    }

    


    public function destroy($id)
    {
        $teamMember = TeamMember::findOrFail($id);
        $teamMember->delete();

        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
