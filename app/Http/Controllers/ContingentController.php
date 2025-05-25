<?php

namespace App\Http\Controllers;

use App\Models\Contingent;
use App\Models\TournamentContingent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ContingentController extends Controller
{
    // Fetch all contingents
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->input('search', '');
            $tournamentId = $request->input('tournament_id');

            $query = Contingent::query();

            // Search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('pic_name', 'like', '%' . $search . '%')
                    ->orWhere('pic_email', 'like', '%' . $search . '%')
                    ->orWhere('pic_phone', 'like', '%' . $search . '%');
                });
            }

            // Filter by tournament_id
            if ($tournamentId) {
                $query->whereHas('tournamentContingents', function ($q) use ($tournamentId) {
                    $q->where('tournament_id', $tournamentId);
                });
            }

            // Filter berdasarkan role user
            if ($user->group && $user->group->name === 'Owner') {
                // Lihat semua
            } elseif ($user->group && $user->group->name === 'Event PIC') {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('tournamentContingents', function ($subQ) use ($user) {
                        $subQ->where('tournament_id', $user->tournament_id);
                    })->orWhere('owner_id', $user->id);
                });
            } else {
                $query->where('owner_id', $user->id);
            }

            $contingents = $query
            ->with(['tournaments' => function ($q) {
                $q->select('tournaments.id', 'name');
            }])
            ->withCount('teamMembers')
            ->paginate(10);


            // Transform response untuk tambah tournament_name
            $transformed = $contingents->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pic_name' => $item->pic_name,
                    'pic_email' => $item->pic_email,
                    'pic_phone' => $item->pic_phone,
                    'team_members_count' => $item->team_members_count,
                    'tournament_name' => $item->tournaments->first()?->name ?? null,
                ];
            });

            // Replace collection
            $contingents->setCollection($transformed);

            return response()->json($contingents);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function index_(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->input('search', ''); // Mendapatkan parameter search dari request

            $query = Contingent::query();

            // Jika ada query pencarian, filter berdasarkan nama atau field lain
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('pic_name', 'like', '%' . $search . '%')
                    ->orWhere('pic_email', 'like', '%' . $search . '%')
                    ->orWhere('pic_phone', 'like', '%' . $search . '%');
                    
            }

            // Jika user bukan owner, filter berdasarkan pemilik kontingen
            if ($user->group && $user->group->name !== 'Owner') {
                $query->where('owner_id', $user->id);
            }

            $contingents = $query->withCount('teamMembers') // Menambahkan jumlah anggota tim
                                ->paginate(10);

            return response()->json($contingents);
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

    public function checkMyContingentsStatus_(){
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

    public function checkMyContingentsStatus()
    {
        try {
            $user = auth()->user(); // Get the logged-in user

            // Eager load group relationship
            $user->load('group');

            // Ambil semua contingents kalau Owner, kalau bukan filter berdasarkan owner_id
            if ($user->group && $user->group->name === 'Owner') {
                $contingents = Contingent::all();
            } else {
                $contingents = Contingent::where('owner_id', $user->id)->get();
            }

            // Tambahkan status is_registered jika ada tournament_id
            $tournamentId = request()->input('tournament_id');
            if ($tournamentId) {
                $contingents->each(function ($contingent) use ($tournamentId) {
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
            $contingent = Contingent::with('tournaments')->findOrFail($id);
            return response()->json(['data' => $contingent], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Contingent not found.'], 404);
        }
    }


    // Store a new contingent
    public function store_(Request $request)
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

    public function store(Request $request)
    {
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
            'tournament_id' => 'required|exists:tournaments,id', // ✅ validasi tournament
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['owner_id'] = $ownerId;
            $data['status'] = 'active';

            // 🔸 Buat kontingen
            $contingent = Contingent::create($data);

            // 🔸 Join ke turnamen via pivot table
            \App\Models\TournamentContingent::create([
                'tournament_id' => $request->tournament_id,
                'contingent_id' => $contingent->id,
            ]);

            return response()->json(['data' => $contingent], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    // Update an existing contingent
    public function update_(Request $request, $id)
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
            'tournament_ids' => 'sometimes|array', // ✅ validasi relasi baru
            'tournament_ids.*' => 'exists:tournaments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $contingent = Contingent::findOrFail($id);
            $data = $request->except('tournament_ids');

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $contingent->update($data);

            // ✅ Sync tournament_ids jika ada
            if ($request->has('tournament_ids')) {
                $contingent->tournaments()->sync($request->tournament_ids);
            }

            return response()->json(['data' => $contingent->load('tournaments')], 200);
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

     public function getByTournament($tournament_id)
    {
        $contingents = Contingent::whereIn('id', function ($query) use ($tournament_id) {
                $query->select('contingent_id')
                      ->from('tournament_contingents')
                      ->where('tournament_id', $tournament_id);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($contingents);
    }

    public function export(Request $request)
    {
        $search = $request->query('search');
        $tournamentId = $request->query('tournament_id');

        $query = Contingent::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($tournamentId) {
            $query->whereHas('tournamentContingents', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            });
        }

        $contingents = $query
            ->with(['tournaments', 'country', 'province', 'district', 'teamMembers'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ✅ Header
        $sheet->fromArray([
            [
                'ID', 'Tournament', 'Contingent Name', 'PIC Name', 'PIC Phone', 'PIC Email',
                'Address', 'Country', 'Province', 'City', 'Total Team Members'
            ]
        ], null, 'A1');

        // ✅ Data
        $row = 2;
        foreach ($contingents as $contingent) {
            $tournamentNames = $contingent->tournaments->pluck('name')->implode(', ');

            $sheet->fromArray([
                $contingent->id,
                $tournamentNames,
                $contingent->name,
                $contingent->pic_name,
                $contingent->pic_phone,
                $contingent->pic_email,
                $contingent->address,
                optional($contingent->country)->name,
                optional($contingent->province)->name,
                optional($contingent->district)->name,
                $contingent->teamMembers->count(),
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'contingents_' . date('Ymd') . '.xlsx';

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}

