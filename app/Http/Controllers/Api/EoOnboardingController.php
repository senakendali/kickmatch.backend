<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventOrganizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EoOnboardingController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('get')) {
            return $this->show($request);
        }

        if ($request->isMethod('post')) {
            return $this->store($request);
        }

        return response()->json(['message' => 'Method not allowed'], 405);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $eo = EventOrganizer::where('user_id', $user->id)->first();

        return response()->json([
            'data' => $eo ? $this->mapResponse($eo) : null
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ((int)($user->role_id ?? 0) !== 2) {
            return response()->json(['message' => 'Forbidden. Only EO can access this endpoint.'], 403);
        }

        $validated = $request->validate([
            'organizer_name'   => ['required','string','max:255'],
            'brand_name'       => ['nullable','string','max:255'],
            'organizer_type'   => ['required', Rule::in(['individual','community','cv','pt'])],
            'phone_whatsapp'   => ['required','string','max:30'],
            'email'            => ['required','email','max:255'],

            'province_id'      => ['nullable','integer'],
            'district_id'      => ['nullable','integer'],
            'province'         => ['nullable','string','max:255'],
            'city'             => ['nullable','string','max:255'],
            'country'          => ['nullable','string','max:255'],
            'address'          => ['required','string','max:2000'],

            'website'          => ['nullable','string','max:255'],
            'instagram'        => ['nullable','string','max:255'],

            'logo'             => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],

            'pic_name'         => ['required','string','max:255'],
            'pic_position'     => ['required','string','max:255'],
            'pic_phone'        => ['required','string','max:30'],
            'pic_email'        => ['required','email','max:255'],

            'legal_name'          => ['nullable','string','max:255'],
            'legal_document_type' => ['nullable', Rule::in(['ktp','npwp','nib','akta'])],
            'id_number'           => ['nullable','string','max:64'],
            'npwp_number'         => ['nullable','string','max:64'],
            'nib_number'          => ['nullable','string','max:64'],
            'business_address'    => ['nullable','string','max:3000'],

            'bank_name'           => ['nullable','string','max:255'],
            'bank_account_name'   => ['nullable','string','max:255'],
            'bank_account_number' => ['nullable','string','max:64'],
            'billing_email'       => ['nullable','email','max:255'],

            'submit'              => ['nullable','boolean'],
        ]);

        if (($validated['organizer_type'] ?? null) === 'individual'
            && !empty($validated['legal_document_type'])
            && $validated['legal_document_type'] !== 'ktp') {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'legal_document_type' => ['Untuk perorangan, pilih dokumen KTP.']
                ]
            ], 422);
        }

        return DB::transaction(function () use ($request, $user, $validated) {
            $eo = EventOrganizer::firstOrNew(['user_id' => $user->id]);

            if ($request->hasFile('logo')) {
                if ($eo->logo) {
                    $this->deleteStoragePathIfExists($eo->logo);
                }
                $path = $request->file('logo')->store('uploads/eo-logos', 'public');
                $validated['logo'] = $path;
            } else {
                unset($validated['logo']);
            }

            if (empty($validated['country'])) $validated['country'] = 'Indonesia';
            $validated['onboarding_completed'] = true;

            $submit = (bool)($validated['submit'] ?? false);
            unset($validated['submit']);

            if ($submit) {
                $validated['verification_status'] = 'submitted';
                $validated['submitted_at'] = now();
            } else {
                if (!$eo->exists) $validated['verification_status'] = 'draft';
            }

            $eo->fill($validated);
            $eo->save();

            $user->onboarding_completed = true;
            $user->organizer_id = $eo->id;
            $user->save();

            return response()->json([
                'message' => 'Onboarding EO saved',
                'data' => [
                    'organizer_id' => $eo->id,
                    'onboarding_completed' => true,
                    'verification_status' => $eo->verification_status,
                    'logo_url' => $eo->logo ? asset('storage/' . $eo->logo) : null,
                ]
            ], 200);
        });
    }

    private function mapResponse(EventOrganizer $eo): array
    {
        return [
            'id' => $eo->id,
            'user_id' => $eo->user_id,
            'organizer_name' => $eo->organizer_name,
            'brand_name' => $eo->brand_name,
            'organizer_type' => $eo->organizer_type,
            'phone_whatsapp' => $eo->phone_whatsapp,
            'email' => $eo->email,

            'province_id' => $eo->province_id,
            'district_id' => $eo->district_id,
            'province' => $eo->province,
            'city' => $eo->city,
            'country' => $eo->country,
            'address' => $eo->address,

            'website' => $eo->website,
            'instagram' => $eo->instagram,
            'logo' => $eo->logo ? asset('storage/' . $eo->logo) : null,

            'pic_name' => $eo->pic_name,
            'pic_position' => $eo->pic_position,
            'pic_phone' => $eo->pic_phone,
            'pic_email' => $eo->pic_email,

            'legal_name' => $eo->legal_name,
            'legal_document_type' => $eo->legal_document_type,
            'id_number' => $eo->id_number,
            'npwp_number' => $eo->npwp_number,
            'nib_number' => $eo->nib_number,
            'business_address' => $eo->business_address,

            'bank_name' => $eo->bank_name,
            'bank_account_name' => $eo->bank_account_name,
            'bank_account_number' => $eo->bank_account_number,
            'billing_email' => $eo->billing_email,

            'onboarding_completed' => (bool)$eo->onboarding_completed,
            'verification_status' => $eo->verification_status,
            'submitted_at' => $eo->submitted_at,
            'verified_at' => $eo->verified_at,
        ];
    }

    private function deleteStoragePathIfExists(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
