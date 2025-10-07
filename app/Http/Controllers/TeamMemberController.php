<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use App\Models\BillingDetail;
use App\Models\AgeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; 
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class TeamMemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $fetchAll = filter_var($request->query('fetch_all', false), FILTER_VALIDATE_BOOLEAN);
            $is_payment_confirmation = filter_var($request->query('is_payment_confirmation', false), FILTER_VALIDATE_BOOLEAN);
            $tournamentId = $request->query('tournament_id');

            if ($is_payment_confirmation) {
                $fetchAll = false;
            }

            $user = auth()->user();
            $search = $request->input('search', '');

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Query awal + eager loading
            $query = TeamMember::with([
                'contingent.tournamentContingents.tournament.tournamentCategories',
                'championshipCategory',
                'matchCategory',
                'ageCategory',
                'categoryClass',
                'tournamentParticipants',
            ]);

            // 🔍 Search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhereHas('contingent', function ($qc) use ($search) {
                            $qc->where('name', 'like', "%$search%");
                        })
                        ->orWhereHas('matchCategory', function ($qm) use ($search) {
                            $qm->where('name', 'like', "%$search%");
                        });
                });
            }

            // 🎯 Filter by tournament
            if ($tournamentId) {
                $query->whereHas('contingent.tournamentContingents', function ($q) use ($tournamentId) {
                    $q->where('tournament_id', $tournamentId);
                });
            }

            // 🎯 Filter tambahan
            if ($request->filled('match_category_id')) {
                $query->where('match_category_id', $request->match_category_id);
            }

            if ($request->filled('age_category_id')) {
                $query->where('age_category_id', $request->age_category_id);
            }

            if ($request->filled('category_class_id')) {
                $query->where('category_class_id', $request->category_class_id);
            }

            // 💳 Filter payment_status berdasarkan keikutsertaan peserta
            if ($request->filled('payment_status')) {
                if ($request->payment_status === 'paid') {
                    $query->whereHas('tournamentParticipants');
                } elseif ($request->payment_status === 'unpaid') {
                    $query->whereDoesntHave('tournamentParticipants');
                }
            }


            // 🔐 Filter berdasarkan grup user
            if ($user->group && $user->group->name === 'Owner') {
                if ($is_payment_confirmation) {
                    $query->whereHas('billingDetails');
                }
            } elseif ($user->group && $user->group->name === 'Event PIC') {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('contingent.tournamentContingents', function ($subQ) use ($user) {
                        $subQ->where('tournament_id', $user->tournament_id);
                    })->orWhereHas('contingent', function ($subQ) use ($user) {
                        $subQ->where('owner_id', $user->id);
                    });
                });
            } else {
                $query->whereHas('contingent', function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                });
            }

            // 💰 Billing filter
            if ($is_payment_confirmation) {
                $query->whereHas('billingDetails');
            }

            // 📦 Ambil data
            $members = $fetchAll ? $query->get() : $query->paginate(10);

            // 🔁 Transform untuk inject tournament_name & registration_fee
            $transform = function ($member) {
                $tournamentContingent = $member->contingent?->tournamentContingents?->first();
                $tournament = $tournamentContingent?->tournament;

                $member->tournament_name = $tournament?->name;
                $member->exists_in_billing_details = BillingDetail::where('team_member_id', $member->id)->exists();

                // Inject registration_fee yang sesuai
                $matchedCategory = $tournament?->tournamentCategories
                    ?->firstWhere('match_category_id', $member->match_category_id);
                $member->registration_fee = $matchedCategory?->registration_fee;

                return $member;
            };

            if ($fetchAll) {
                $members = $members->map($transform);
            } else {
                $members->setCollection($members->getCollection()->map($transform));
            }

            return response()->json($members, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }







    public function export(Request $request)
    {
        $search = $request->query('search');
        $tournamentId = $request->query('tournament_id');
        $matchCategoryId = $request->query('match_category_id');
        $ageCategoryId = $request->query('age_category_id');
        $categoryClassId = $request->query('category_class_id');
        $paymentStatus = $request->query('payment_status');

        $query = TeamMember::with([
            'contingent.tournamentContingents.tournament',
            'championshipCategory',
            'matchCategory',
            'tournamentParticipants',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('contingent', function ($qc) use ($search) {
                    $qc->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($tournamentId) {
            $query->whereHas('contingent.tournamentContingents', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            });
        }

        if ($matchCategoryId) {
            $query->where('match_category_id', $matchCategoryId);
        }

        if ($ageCategoryId) {
            $query->where('age_category_id', $ageCategoryId);
        }

        if ($categoryClassId) {
            $query->where('category_class_id', $categoryClassId);
        }

        if ($paymentStatus === 'paid') {
            $query->whereHas('tournamentParticipants');
        } elseif ($paymentStatus === 'unpaid') {
            $query->whereDoesntHave('tournamentParticipants');
        }


        $teamMembers = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ✅ Header
        $sheet->fromArray([
            ['ID', 'Tournaments', 'Contingent', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Jenis Kelamin', 'Tinggi Badan', 'Berat Badan', 'NIK', 'No. KK', 'Alamat']
        ], null, 'A1');

        // ✅ Data
        $row = 2;
        foreach ($teamMembers as $member) {
            // Ambil semua nama turnamen dari relasi tournamentContingents
            $tournamentNames = collect($member->contingent?->tournamentContingents)
                ->pluck('tournament.name')
                ->filter()
                ->unique()
                ->implode(', ');

           $sheet->setCellValue("A{$row}", $member->id);
            $sheet->setCellValue("B{$row}", $tournamentNames);
            $sheet->setCellValue("C{$row}", $member->contingent->name ?? '');
            $sheet->setCellValue("D{$row}", $member->name);
            $sheet->setCellValue("E{$row}", $member->birth_place);
            $sheet->setCellValue("F{$row}", $member->birth_date);
            $sheet->setCellValue("G{$row}", $member->gender);
            $sheet->setCellValue("H{$row}", $member->body_height);
            $sheet->setCellValue("I{$row}", $member->body_weight);

            // ✅ Khusus NIK & KK pakai format teks eksplisit
            $sheet->setCellValueExplicit("J{$row}", (string) $member->nik, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("K{$row}", (string) $member->family_card_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->setCellValue("L{$row}", $member->address);

            $row++;
        }

        // Set NIK dan KK sebagai string (kolom J dan K = kolom ke-10 dan 11)
        $sheet->setCellValueExplicit("J{$row}", (string) $member->nik, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("K{$row}", (string) $member->family_card_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $writer = new Xlsx($spreadsheet);
        $filename = 'team_members_' . date('Ymd_His') . '.xlsx';

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




    

    



    public function fetchTeamMembersBilling(){
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
                $members = TeamMember::with('billing')->paginate(10);
            } else {
                // Fetch members filtered by the user's owner_id
                $members = TeamMember::with('billing')
                    ->whereHas('billing', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
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
        // === VALIDATION (ikuti style teams) ===
        $validator = Validator::make($request->all(), [
            'contingent_id' => ['required','exists:contingents,id'],

            'name'           => ['required','string','max:255'],
            'jersey_name'    => ['nullable','string','max:255'],
            'jersey_number'  => [
                'nullable','integer','between:0,99',
                Rule::unique('team_members','jersey_number')
                    ->where(fn($q) => $q->where('contingent_id', $request->contingent_id))
            ],
            'position'       => ['required','in:gk,fixo,ala,pivot,utility'],
            'dominant_foot'  => ['required','in:right,left,both'],
            'jersey_size'    => ['required','in:XS,S,M,L,XL,2XL'],

            'birth_place'    => ['required','string','max:255'],
            'birth_date'     => ['required','date'],
            'gender'         => ['required','in:male,female'],

            // lokasi
            'country_id'     => ['required','exists:countries,id'],
            'province_id'    => ['required','exists:provinces,id'],
            'district_id'    => ['required','exists:districts,id'],
            'subdistrict_id' => ['required','exists:subdistricts,id'],
            'ward_id'        => ['required','exists:wards,id'],

            'address'        => ['required','string','max:255'],
            'phone'          => ['nullable','string','max:50'],
            'email'          => ['nullable','email','max:255'],
            'nik'            => ['nullable','string','max:255'],
            'body_height'    => ['nullable','numeric'],
            'body_weight'    => ['nullable','numeric'],

            'emergency_contact_name'  => ['nullable','string','max:255'],
            'emergency_contact_phone' => ['nullable','string','max:50'],
            'status'         => ['required','in:active,injured,suspended,inactive'],

            // files
            'photo'               => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],  // 2MB
            'id_document'         => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'], // 5MB
            'medical_certificate' => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
            'consent_form'        => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // === Pastikan direktori upload tersedia di disk public ===
        foreach (['uploads/team_members/photos', 'uploads/team_members/documents'] as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }

        DB::beginTransaction();
        try {
            // === Build data untuk insert (cast angka) ===
            $data = [
                'contingent_id'  => (int) $request->input('contingent_id'),

                'name'           => (string) $request->string('name'),
                'jersey_name'    => $request->input('jersey_name'),
                'jersey_number'  => $request->filled('jersey_number') ? (int) $request->input('jersey_number') : null,
                'position'       => $request->input('position'),
                'dominant_foot'  => $request->input('dominant_foot', 'right'),
                'jersey_size'    => $request->input('jersey_size', 'L'),

                'birth_place'    => (string) $request->string('birth_place'),
                'birth_date'     => $request->input('birth_date'),
                'gender'         => $request->input('gender'),

                'country_id'     => (int) $request->input('country_id'),
                'province_id'    => (int) $request->input('province_id'),
                'district_id'    => (int) $request->input('district_id'),
                'subdistrict_id' => (int) $request->input('subdistrict_id'),
                'ward_id'        => (int) $request->input('ward_id'),

                'address'        => (string) $request->string('address'),
                'phone'          => $request->input('phone'),
                'email'          => $request->input('email'),
                'nik'            => $request->input('nik'),
                'body_height'    => $request->filled('body_height') ? (float) $request->input('body_height') : null,
                'body_weight'    => $request->filled('body_weight') ? (float) $request->input('body_weight') : null,

                'emergency_contact_name'  => $request->input('emergency_contact_name'),
                'emergency_contact_phone' => $request->input('emergency_contact_phone'),
                'status'         => $request->input('status', 'active'),
            ];

            $slug = Str::slug($data['name'] ?: 'member');
            $now  = time();
            $rand = Str::random(6);

            // === Uploads → simpan PATH relatif di kolom *_path ===
            if ($request->hasFile('photo')) {
                $ext = $request->file('photo')->extension();
                $filename = "photo-{$slug}-{$now}-{$rand}.{$ext}";
                $data['photo_path'] = $request->file('photo')
                    ->storeAs('uploads/team_members/photos', $filename, 'public');
            }

            if ($request->hasFile('id_document')) {
                $ext = $request->file('id_document')->extension();
                $filename = "id-{$slug}-{$now}-{$rand}.{$ext}";
                $data['id_document_path'] = $request->file('id_document')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            if ($request->hasFile('medical_certificate')) {
                $ext = $request->file('medical_certificate')->extension();
                $filename = "medical-{$slug}-{$now}-{$rand}.{$ext}";
                $data['medical_certificate_path'] = $request->file('medical_certificate')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            if ($request->hasFile('consent_form')) {
                $ext = $request->file('consent_form')->extension();
                $filename = "consent-{$slug}-{$now}-{$rand}.{$ext}";
                $data['consent_form_path'] = $request->file('consent_form')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            // === Create record ===
            $member = TeamMember::create($data);

            DB::commit();

            // === Response: convert path → url utk FE ===
            $resp = $member->toArray();

            $map = [
                'photo_path'               => 'photo_url',
                'id_document_path'         => 'id_document_url',
                'medical_certificate_path' => 'medical_certificate_url',
                'consent_form_path'        => 'consent_form_url',
            ];
            foreach ($map as $pathKey => $urlKey) {
                $p = $member->{$pathKey} ?? null;
                $resp[$urlKey] = $p ? Storage::disk('public')->url($p) : null;
            }

            return response()->json(['data' => $resp], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            // Cleanup file yang sudah terupload
            foreach (['photo_path','id_document_path','medical_certificate_path','consent_form_path'] as $k) {
                if (!empty($data[$k])) {
                    try { Storage::disk('public')->delete($data[$k]); } catch (\Throwable $ex) {}
                }
            }

            Log::error('STORE team_member failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function show($id)
    {
        // kalau perlu relasi lain, tinggal tambah di with([...])
        $member = TeamMember::with(['contingent'])->findOrFail($id);

        // Buat URL publik dari file yang tersimpan
        $member->photo_url                = $member->photo_path
            ? Storage::disk('public')->url($member->photo_path)
            : null;

        $member->id_document_url          = $member->id_document_path
            ? Storage::disk('public')->url($member->id_document_path)
            : null;

        $member->medical_certificate_url  = $member->medical_certificate_path
            ? Storage::disk('public')->url($member->medical_certificate_path)
            : null;

        $member->consent_form_url         = $member->consent_form_path
            ? Storage::disk('public')->url($member->consent_form_path)
            : null;

        // Sembunyikan kolom internal path & timestamp biar respons rapi
        $member->makeHidden([
            'photo_path',
            'id_document_path',
            'medical_certificate_path',
            'consent_form_path',
            'created_at',
            'updated_at',
        ]);

        return response()->json($member, 200);
    }

    

    public function update(Request $request, $id)
    {
        Log::info('Update TeamMember payload', ['id' => $id, 'payload' => $request->all()]);

        $member = TeamMember::findOrFail($id);

        // === VALIDATION ===
        $validator = Validator::make($request->all(), [
            'contingent_id' => ['required','exists:contingents,id'],

            'name'           => ['required','string','max:255'],
            'jersey_name'    => ['nullable','string','max:255'],
            'jersey_number'  => [
                'nullable','integer','between:0,99',
                Rule::unique('team_members','jersey_number')
                    ->where(fn($q) => $q->where('contingent_id', $request->contingent_id))
                    ->ignore($member->id)
            ],
            'position'       => ['required','in:gk,fixo,ala,pivot,utility'],
            'dominant_foot'  => ['required','in:right,left,both'],
            'jersey_size'    => ['required','in:XS,S,M,L,XL,2XL'],

            'birth_place'    => ['required','string','max:255'],
            'birth_date'     => ['required','date'],
            'gender'         => ['required','in:male,female'],

            // lokasi
            'country_id'     => ['required','exists:countries,id'],
            'province_id'    => ['required','exists:provinces,id'],
            'district_id'    => ['required','exists:districts,id'],
            'subdistrict_id' => ['required','exists:subdistricts,id'],
            'ward_id'        => ['required','exists:wards,id'],

            'address'        => ['required','string','max:255'],
            'phone'          => ['nullable','string','max:50'],
            'email'          => ['nullable','email','max:255'],
            'nik'            => ['nullable','string','max:255'],
            'body_height'    => ['nullable','numeric'],
            'body_weight'    => ['nullable','numeric'],

            'emergency_contact_name'  => ['nullable','string','max:255'],
            'emergency_contact_phone' => ['nullable','string','max:50'],
            'status'         => ['required','in:active,injured,suspended,inactive'],

            // files (optional saat update)
            'photo'               => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'id_document'         => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
            'medical_certificate' => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
            'consent_form'        => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Pastikan direktori upload tersedia
        foreach (['uploads/team_members/photos', 'uploads/team_members/documents'] as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }

        DB::beginTransaction();
        try {
            // Simpan path lama untuk dihapus setelah commit jika diganti
            $oldFilesToDelete = [
                'photo'               => $member->photo_path,
                'id_document'         => $member->id_document_path,
                'medical_certificate' => $member->medical_certificate_path,
                'consent_form'        => $member->consent_form_path,
            ];

            // === Build data update ===
            $update = [
                'contingent_id'  => (int) $request->input('contingent_id'),

                'name'           => (string) $request->string('name'),
                'jersey_name'    => $request->input('jersey_name'),
                'jersey_number'  => $request->filled('jersey_number') ? (int) $request->input('jersey_number') : null,
                'position'       => $request->input('position'),
                'dominant_foot'  => $request->input('dominant_foot', 'right'),
                'jersey_size'    => $request->input('jersey_size', 'L'),

                'birth_place'    => (string) $request->string('birth_place'),
                'birth_date'     => $request->input('birth_date'),
                'gender'         => $request->input('gender'),

                'country_id'     => (int) $request->input('country_id'),
                'province_id'    => (int) $request->input('province_id'),
                'district_id'    => (int) $request->input('district_id'),
                'subdistrict_id' => (int) $request->input('subdistrict_id'),
                'ward_id'        => (int) $request->input('ward_id'),

                'address'        => (string) $request->string('address'),
                'phone'          => $request->input('phone'),
                'email'          => $request->input('email'),
                'nik'            => $request->input('nik'),
                'body_height'    => $request->filled('body_height') ? (float) $request->input('body_height') : null,
                'body_weight'    => $request->filled('body_weight') ? (float) $request->input('body_weight') : null,

                'emergency_contact_name'  => $request->input('emergency_contact_name'),
                'emergency_contact_phone' => $request->input('emergency_contact_phone'),
                'status'         => $request->input('status', 'active'),
            ];

            $slug = Str::slug($update['name'] ?: 'member');
            $now  = time();
            $rand = Str::random(6);

            // === File uploads (replace jika ada file baru) ===
            if ($request->hasFile('photo')) {
                $ext = $request->file('photo')->extension();
                $filename = "photo-{$slug}-{$now}-{$rand}.{$ext}";
                $update['photo_path'] = $request->file('photo')
                    ->storeAs('uploads/team_members/photos', $filename, 'public');
            }

            if ($request->hasFile('id_document')) {
                $ext = $request->file('id_document')->extension();
                $filename = "id-{$slug}-{$now}-{$rand}.{$ext}";
                $update['id_document_path'] = $request->file('id_document')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            if ($request->hasFile('medical_certificate')) {
                $ext = $request->file('medical_certificate')->extension();
                $filename = "medical-{$slug}-{$now}-{$rand}.{$ext}";
                $update['medical_certificate_path'] = $request->file('medical_certificate')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            if ($request->hasFile('consent_form')) {
                $ext = $request->file('consent_form')->extension();
                $filename = "consent-{$slug}-{$now}-{$rand}.{$ext}";
                $update['consent_form_path'] = $request->file('consent_form')
                    ->storeAs('uploads/team_members/documents', $filename, 'public');
            }

            // Update DB
            $member->update($update);

            DB::commit();

            // Hapus file lama yang tergantikan (setelah commit)
            foreach ([
                'photo_path'               => 'photo',
                'id_document_path'         => 'id_document',
                'medical_certificate_path' => 'medical_certificate',
                'consent_form_path'        => 'consent_form',
            ] as $col => $key) {
                if (!empty($update[$col]) && !empty($oldFilesToDelete[$key])) {
                    try { Storage::disk('public')->delete($oldFilesToDelete[$key]); } catch (\Throwable $e) {}
                }
            }

            // Response: kirim juga URL untuk FE
            $member->refresh();
            $resp = $member->toArray();

            $map = [
                'photo_path'               => 'photo_url',
                'id_document_path'         => 'id_document_url',
                'medical_certificate_path' => 'medical_certificate_url',
                'consent_form_path'        => 'consent_form_url',
            ];
            foreach ($map as $pathKey => $urlKey) {
                $p = $member->{$pathKey} ?? null;
                $resp[$urlKey] = $p ? Storage::disk('public')->url($p) : null;
            }

            return response()->json(['data' => $resp], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UPDATE team_member failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    


    public function destroy($id)
    {
        try {
            $teamMember = TeamMember::findOrFail($id);
            $teamMember->delete();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
