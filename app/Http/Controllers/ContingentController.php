<?php

namespace App\Http\Controllers;

use App\Models\Contingent;
use App\Models\TeamStaff;
use App\Models\TournamentContingent;
use App\Models\EventOrganizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; 

class ContingentController extends Controller
{
    // Fetch all contingents
    public function index(Request $request)
    {
        try {
            $perPage      = $request->get('per_page', $request->get('perPage', 10));
            $search       = trim($request->input('search', ''));
            $tournamentId = $request->input('tournament_id');

            // 🔐 Ambil user dari guard (sanctum/passport)
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // Base query
            $query  = Contingent::query();
            $roleId = (int) ($user->role_id ?? 0);

            /**
             * 🎯 Filter berdasarkan role:
             * - role_id == 2 (EO): hanya lihat contingent yang ikut tournament milik EO ini
             * - role lain (Owner/Admin/User): sementara dibiarkan lihat semua (plus filter tournament_id & search)
             */

            if ($roleId === 2) { // 2 = EO
                // Ambil ID EO di tabel event_organizers
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    // Contingent yang terhubung ke tournaments dengan organizer_id = EO ini
                    $query->whereHas('tournaments', function ($q) use ($organizerId, $tournamentId) {
                        $q->where('organizer_id', $organizerId);

                        // Kalau di-request spesifik tournament_id, filter sekalian
                        if ($tournamentId) {
                            $q->where('tournaments.id', $tournamentId);
                        }
                    });
                } else {
                    // EO belum punya profile → kosong aja
                    $query->whereRaw('1 = 0');
                }
            } else {
                // role selain EO
                if ($tournamentId) {
                    // Kalau ada filter tournament_id, pakai pivot
                    $query->whereHas('tournamentContingents', function ($q) use ($tournamentId) {
                        $q->where('tournament_id', $tournamentId);
                    });
                }
            }

            // 🔍 Optional search
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('pic_name', 'like', '%' . $search . '%')
                        ->orWhere('pic_email', 'like', '%' . $search . '%')
                        ->orWhere('pic_phone', 'like', '%' . $search . '%');
                });
            }

            // 📦 Query + relasi + count
            $contingents = $query
                ->with(['tournaments' => function ($q) {
                    $q->select('tournaments.id', 'name');
                }])
                ->withCount('teamMembers')
                ->paginate($perPage);

            // 🔁 Transform response biar clean
            $transformed = $contingents->getCollection()->transform(function ($item) {
                $tournamentNames = $item->tournaments
                    ? $item->tournaments->pluck('name')->filter()->implode(', ')
                    : '';

                return [
                    'id'                 => $item->id,
                    'name'               => $item->name,
                    'pic_name'           => $item->pic_name,
                    'pic_email'          => $item->pic_email,
                    'pic_phone'          => $item->pic_phone,
                    'team_members_count' => $item->team_members_count,
                    'tournament_name'    => $tournamentNames,
                ];
            });

            $contingents->setCollection($transformed);

            return response()->json($contingents, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unable to fetch data',
                'message' => $e->getMessage()
            ], 500);
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
            $contingent = Contingent::with([
                'tournaments:id,name',
                'staff:id,contingent_id,staff_role_id,full_name,phone,email,is_primary,ordering',
            ])->findOrFail($id);

            // Normalizer: pastikan selalu jadi URL absolut
            $toUrl = function ($path) {
                if (empty($path)) return null;

                // Sudah absolut (http/https) atau data URL
                if (Str::startsWith($path, ['http://','https://','data:'])) {
                    return $path;
                }

                // Sudah /storage/... → jadikan absolut pakai URL::to()
                if (Str::startsWith($path, ['/storage/', 'storage/'])) {
                    $p = Str::startsWith($path, '/storage/') ? $path : '/'.$path;
                    return URL::to($p);
                }

                // Path relatif di disk public → ambil /storage/... lalu absolutkan
                return URL::to(Storage::disk('public')->url($path));
            };

            $contingent->logo              = $toUrl($contingent->logo);
            $contingent->jersey_home_image = $toUrl($contingent->jersey_home_image);
            $contingent->jersey_away_image = $toUrl($contingent->jersey_away_image);

            return response()->json(['data' => $contingent], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Contingent not found.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch contingent.','detail' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $ownerId = auth()->id();

        $validator = Validator::make($request->all(), [
            'name'           => ['required','string','max:255'],
            'type'           => ['required', Rule::in(['futsal','minisoccer'])],
            'pic_name'       => ['required','string','max:255'],
            'pic_email'      => ['required','email','max:255','unique:contingents,pic_email'],
            'pic_phone'      => ['required','string','max:255'],
            'country_id'     => ['required','exists:countries,id'],
            'province_id'    => ['required','exists:provinces,id'],
            'district_id'    => ['required','exists:districts,id'],
            'subdistrict_id' => ['required','exists:subdistricts,id'],
            'ward_id'        => ['required','exists:wards,id'],
            'address'        => ['required','string','max:255'],
            'tournament_id'  => ['required','exists:tournaments,id'],

            'logo'               => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'jersey_home_hex'    => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'jersey_away_hex'    => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'jersey_home_image'  => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'jersey_away_image'  => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],

            'staff'                    => ['nullable','array'],
            'staff.*.staff_role_id'    => ['nullable','exists:staff_roles,id'],
            'staff.*.full_name'        => ['nullable','string','max:255'],
            'staff.*.email'            => ['nullable','email','max:255'],
            'staff.*.phone'            => ['nullable','string','max:50'],
            'staff.*.is_primary'       => ['nullable','boolean'],
            'staff.*.ordering'         => ['nullable','integer','min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Pastikan direktori upload ada
        foreach (['uploads/contingent_logos','uploads/contingent_jerseys'] as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }

        DB::beginTransaction();
        try {
            $jerseyHomeHex = $request->input('jersey_home_hex');
            $jerseyAwayHex = $request->input('jersey_away_hex');
            if ($jerseyHomeHex) $jerseyHomeHex = strtoupper($jerseyHomeHex);
            if ($jerseyAwayHex) $jerseyAwayHex = strtoupper($jerseyAwayHex);

            $data = [
                'owner_id'        => $ownerId,
                'status'          => 'active',
                'name'            => $request->string('name'),
                'type'            => $request->input('type', 'futsal'),
                'pic_name'        => $request->string('pic_name'),
                'pic_email'       => $request->string('pic_email'),
                'pic_phone'       => $request->string('pic_phone'),
                'country_id'      => (int) $request->input('country_id'),
                'province_id'     => (int) $request->input('province_id'),
                'district_id'     => (int) $request->input('district_id'),
                'subdistrict_id'  => (int) $request->input('subdistrict_id'),
                'ward_id'         => (int) $request->input('ward_id'),
                'address'         => $request->string('address'),
                'jersey_home_hex' => $jerseyHomeHex,
                'jersey_away_hex' => $jerseyAwayHex,
            ];

            // Uploads → simpan PATH relatif
            $slug = \Illuminate\Support\Str::slug($data['name'] ?? 'team');

            if ($request->hasFile('logo')) {
                $filename = 'logo-'.$slug.'-'.time().'.'.$request->file('logo')->extension();
                $data['logo'] = $request->file('logo')->storeAs('uploads/contingent_logos', $filename, 'public');
            }
            if ($request->hasFile('jersey_home_image')) {
                $filename = 'jersey-home-'.$slug.'-'.time().'.'.$request->file('jersey_home_image')->extension();
                $data['jersey_home_image'] = $request->file('jersey_home_image')->storeAs('uploads/contingent_jerseys', $filename, 'public');
            }
            if ($request->hasFile('jersey_away_image')) {
                $filename = 'jersey-away-'.$slug.'-'.time().'.'.$request->file('jersey_away_image')->extension();
                $data['jersey_away_image'] = $request->file('jersey_away_image')->storeAs('uploads/contingent_jerseys', $filename, 'public');
            }

            // Create contingent
            $contingent = Contingent::create($data);

            // Pivot tournament
            TournamentContingent::firstOrCreate([
                'tournament_id' => (int) $request->tournament_id,
                'contingent_id' => $contingent->id,
            ]);

            // ==== STAFF ====
            $staffPayload = $request->input('staff', []);

            // Kalau FE kirim sebagai JSON string, parse
            if (is_string($staffPayload)) {
                $decoded = json_decode($staffPayload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $staffPayload = $decoded;
                } else {
                    $staffPayload = [];
                }
            }
            if (!is_array($staffPayload)) {
                $staffPayload = [];
            }

            // Log buat debugging (lihat storage/logs/laravel.log)
            Log::info('STORE staff raw payload', ['staff' => $staffPayload]);

            // Insert per baris (lebih mudah trace error)
            foreach ($staffPayload as $row) {
                $roleId    = $row['staff_role_id'] ?? null;
                $fullName  = $row['full_name']     ?? null;

                if (empty($roleId) || empty($fullName)) {
                    continue; // skip baris kosong
                }

                $contingent->staff()->create([
                    'staff_role_id' => (int) $roleId,
                    'full_name'     => $fullName,
                    'phone'         => $row['phone'] ?? null,
                    'email'         => $row['email'] ?? null,
                    'is_primary'    => !empty($row['is_primary']) ? 1 : 0,
                    'ordering'      => isset($row['ordering']) ? (int)$row['ordering'] : 0,
                ]);
            }

            DB::commit();

            // Response: convert path → URL
            $contingent->load(['staff', 'tournaments']);
            foreach (['logo','jersey_home_image','jersey_away_image'] as $f) {
                $p = $contingent->{$f};
                $contingent->{$f} = $p ? Storage::disk('public')->url($p) : null;
            }

            return response()->json(['data' => $contingent], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('STORE contingent failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    // Update an existing contingent
    

    public function update(Request $request, $id)
    {
        // batas upload (opsional)
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');

        try {
            $contingent = Contingent::findOrFail($id);

            // VALIDASI (field yang dipakai FE)
            $validated = $request->validate([
                'name'           => 'sometimes|required|string|max:255',
                'type'           => ['sometimes','required', Rule::in(['futsal','minisoccer'])],

                'pic_name'       => 'sometimes|required|string|max:255',
                'pic_email'      => 'sometimes|required|email|max:255|unique:contingents,pic_email,' . $id,
                'pic_phone'      => 'sometimes|required|string|max:255',

                'country_id'     => 'sometimes|nullable|exists:countries,id',
                'province_id'    => 'sometimes|nullable|exists:provinces,id',
                'district_id'    => 'sometimes|nullable|exists:districts,id',
                'subdistrict_id' => 'sometimes|nullable|exists:subdistricts,id',
                'ward_id'        => 'sometimes|nullable|exists:wards,id',
                'address'        => 'sometimes|nullable|string|max:255',

                'status'         => 'sometimes|in:active,inactive,pending,disqualified',

                // jersey & warna
                'jersey_home_hex'   => 'sometimes|nullable|regex:/^#[0-9A-Fa-f]{6}$/',
                'jersey_away_hex'   => 'sometimes|nullable|regex:/^#[0-9A-Fa-f]{6}$/',
                'jersey_home_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
                'jersey_away_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:20480',

                // logo
                'logo'              => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:20480',

                // multi tournament
                'tournament_ids'    => 'sometimes|array',
                'tournament_ids.*'  => 'exists:tournaments,id',

                // staf (baris kosong/invalid akan difilter manual)
                'staff'                         => 'sometimes|array',
                'staff.*.id'                    => 'nullable|integer',
                'staff.*.staff_role_id'         => 'nullable|exists:staff_roles,id',
                'staff.*.full_name'             => 'nullable|string|max:255',
                'staff.*.phone'                 => 'nullable|string|max:255',
                'staff.*.email'                 => 'nullable|email|max:255',
                'staff.*.is_primary'            => 'nullable|boolean',
                'staff.*.ordering'              => 'nullable|integer|min:0',
            ]);

            DB::beginTransaction();

            // Pastikan folder publik ada
            foreach (['uploads/contingent_logos','uploads/contingent_jerseys'] as $dir) {
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }
            }

            $updates = $validated;
            $slug = Str::slug($request->input('name', $contingent->name) ?: 'team');

            // === FILES ===
            // Logo
            if ($request->hasFile('logo')) {
                if ($contingent->logo && !Str::startsWith($contingent->logo, ['http://','https://','/storage/'])) {
                    Storage::disk('public')->delete($contingent->logo);
                }
                $filename = 'logo-'.$slug.'-'.time().'.'.$request->file('logo')->extension();
                $updates['logo'] = $request->file('logo')->storeAs('uploads/contingent_logos', $filename, 'public');
            }

            // Jersey home
            if ($request->hasFile('jersey_home_image')) {
                if ($contingent->jersey_home_image && !Str::startsWith($contingent->jersey_home_image, ['http://','https://','/storage/'])) {
                    Storage::disk('public')->delete($contingent->jersey_home_image);
                }
                $filename = 'jersey-home-'.$slug.'-'.time().'.'.$request->file('jersey_home_image')->extension();
                $updates['jersey_home_image'] = $request->file('jersey_home_image')->storeAs('uploads/contingent_jerseys', $filename, 'public');
            }

            // Jersey away
            if ($request->hasFile('jersey_away_image')) {
                if ($contingent->jersey_away_image && !Str::startsWith($contingent->jersey_away_image, ['http://','https://','/storage/'])) {
                    Storage::disk('public')->delete($contingent->jersey_away_image);
                }
                $filename = 'jersey-away-'.$slug.'-'.time().'.'.$request->file('jersey_away_image')->extension();
                $updates['jersey_away_image'] = $request->file('jersey_away_image')->storeAs('uploads/contingent_jerseys', $filename, 'public');
            }

            // Buang key non-kolom sebelum update
            unset($updates['tournament_ids'], $updates['staff']);

            // === UPDATE MASTER ===
            $contingent->fill($updates);
            $contingent->save();

            // === SYNC TOURNAMENTS ===
            if ($request->filled('tournament_ids')) {
                $contingent->tournaments()->sync($request->input('tournament_ids', []));
            }

            // === STAFF ===
            if ($request->has('staff')) {
                $payload = collect($request->input('staff', []))
                    ->filter(fn($r) => !empty($r['staff_role_id']) && !empty($r['full_name']))
                    ->values();

                $hasId = $payload->contains(fn($r) => !empty($r['id']));

                if ($hasId) {
                    // UPSERT by id + delete yang tidak dikirim
                    $sentIds = [];
                    foreach ($payload as $row) {
                        $data = [
                            'contingent_id' => $contingent->id,
                            'staff_role_id' => (int) $row['staff_role_id'],
                            'full_name'     => $row['full_name'],
                            'phone'         => $row['phone'] ?? null,
                            'email'         => $row['email'] ?? null,
                            'is_primary'    => !empty($row['is_primary']) ? 1 : 0,
                            'ordering'      => isset($row['ordering']) ? (int) $row['ordering'] : 0,
                        ];

                        if (!empty($row['id'])) {
                            $staff = TeamStaff::where('contingent_id', $contingent->id)
                                ->where('id', (int) $row['id'])
                                ->first();

                            if ($staff) {
                                $staff->update($data);
                                $sentIds[] = $staff->id;
                            } else {
                                $new = TeamStaff::create($data);
                                $sentIds[] = $new->id;
                            }
                        } else {
                            $new = TeamStaff::create($data);
                            $sentIds[] = $new->id;
                        }
                    }

                    if (!empty($sentIds)) {
                        TeamStaff::where('contingent_id', $contingent->id)
                            ->whereNotIn('id', $sentIds)
                            ->delete();
                    }
                } else {
                    // REPLACE-ALL jika FE tidak kirim id
                    TeamStaff::where('contingent_id', $contingent->id)->delete();

                    $rows = $payload->map(function ($r) use ($contingent) {
                        return [
                            'contingent_id' => $contingent->id,
                            'staff_role_id' => (int) $r['staff_role_id'],
                            'full_name'     => $r['full_name'],
                            'phone'         => $r['phone'] ?? null,
                            'email'         => $r['email'] ?? null,
                            'is_primary'    => !empty($r['is_primary']) ? 1 : 0,
                            'ordering'      => isset($r['ordering']) ? (int) $r['ordering'] : 0,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    })->all();

                    if (!empty($rows)) {
                        TeamStaff::insert($rows);
                    }
                }
            }

            DB::commit();

            // Return with URLs (bukan path relatif)
            $contingent->load([
                'tournaments:id,name',
                'staff:id,contingent_id,staff_role_id,full_name,phone,email,is_primary,ordering',
            ]);

            foreach (['logo','jersey_home_image','jersey_away_image'] as $f) {
                $p = $contingent->{$f};
                $contingent->{$f} = $p
                    ? (Str::startsWith($p, ['http://','https://','/storage/']) ? $p : Storage::disk('public')->url($p))
                    : null;
            }

            return response()->json(['data' => $contingent], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Contingent not found'], 404);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update Contingent', 'error' => $e->getMessage()], 500);
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
        ->with([
            'tournaments:id,name',
            'country:id,country_name',
            'province:id,name',
            'district:id,name',
            'teamMembers:id,contingent_id'
        ])
        ->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ✅ Header
    $sheet->fromArray([
        [
            'ID', 'Tournaments', 'Contingent Name', 'PIC Name', 'PIC Phone', 'PIC Email',
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
            optional($contingent->country)->country_name,
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

