<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\TeamMember;
use App\Models\TournamentParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // Gunakan GD Driver


class BillingController extends Controller
{
    /**
     * Display a paginated list of billings for the authenticated user.
     *
     * If the user is an 'Owner', all billings are returned. Otherwise,
     * only the billings associated with the user are shown.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing the billings data or an error message.
     */

    public function index_()
    {
        try {
            $user = auth()->user(); // Mendapatkan user yang sedang login
        
            // Pastikan eager loading untuk menghindari lazy loading
            $user->load('group'); 
        
            if ($user->group && $user->group->name === 'Owner') {
                $billings = Billing::paginate(10); // Default: tidak ada filter
                
            } else {
                $billings = Billing::where('user_id', $user->id)->paginate(10);
            }
        
            return response()->json($billings, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->input('search', '');
            $tournamentId = $request->input('tournament_id');

            $query = Billing::query();

            // Optional search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', '%' . $search . '%')
                    ->orWhere('reference', 'like', '%' . $search . '%');
                });
            }

            // Optional filter by tournament (kalau applicable)
            if ($tournamentId) {
                $query->where('tournament_id', $tournamentId);
            }

            // Role-based filtering
            if ($user->group && $user->group->name === 'Owner') {
                // Lihat semua
            } elseif ($user->group && $user->group->name === 'Event PIC') {
                $query->where('tournament_id', $user->tournament_id)
                    ->orWhere('user_id', $user->id);
            } else {
                $query->where('user_id', $user->id);
            }

            $billings = $query->paginate(10);

            return response()->json($billings, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
            'notes' => 'nullable|string',
            'member_ids' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            // Generate invoice number
            $latestInvoice = Billing::latest('id')->first();
            $nextNumber = $latestInvoice ? ((int)substr($latestInvoice->invoice_number, -4)) + 1 : 1;
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Calculate total amount from members' registration fees
            $totalAmount = 0;

            foreach ($data['member_ids'] as $member_id) {
                $member = TeamMember::with(['matchCategory.tournamentCategories'])
                    ->findOrFail($member_id);

                $totalAmount += $member->matchCategory->tournamentCategories->first()->registration_fee ?? 0;
            }

            // Insert into the Billing table
            $billing = Billing::create([
                'invoice_number' => $invoiceNumber,
                'tournament_id' => $data['tournament_id'],
                'user_id' => $user->id, // Get user ID from authenticated user
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
                'amount' => $totalAmount, // Total calculated amount
                'notes' => $data['notes'] ?? null,
            ]);

            // Insert into Billing Details
            foreach ($data['member_ids'] as $member_id) {
                $member = TeamMember::with(['matchCategory.tournamentCategories'])
                    ->findOrFail($member_id);

                BillingDetail::create([
                    'billing_id' => $billing->id,
                    'team_member_id' => $member_id,
                    'amount' => $member->matchCategory->tournamentCategories->first()->registration_fee ?? 0,
                    'tournament_category_id' => $member->matchCategory->tournamentCategories->first()->id ?? null,
                ]);
            }

            DB::commit();
            return response()->json($billing->load('billingDetails'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function show($id)
    {
        // Retrieve the billing and its details along with the necessary relationships
        $billing = Billing::with(['billingDetails.teamMember.contingent', 'billingDetails.teamMember.championshipCategory', 'billingDetails.teamMember.matchCategory.tournamentCategories'])
            ->findOrFail($id);

        // Map the billing details to include `exists_in_billing_details` and maintain structure
        $billing->billingDetails->transform(function ($detail) {
            $teamMember = $detail->teamMember;

            return array_merge($teamMember->toArray(), [
                'exists_in_billing_details' => true,
                'billing_detail_id' => $detail->id, // Attach the billing detail ID
                'billing_id' => $detail->billing_id, // Include billing ID
                'amount' => $detail->amount, // Include additional billing detail fields
                'tournament_category_id' => $detail->tournament_category_id,
            ]);
        });

        return response()->json($billing);
    }

    public function addMember(Request $request)
    {
        try {
            // Validasi input
            $validatedData = $request->validate([
                'billing_id' => 'required|exists:billings,id',
                'team_member_id' => 'required|exists:team_members,id',
                'tournament_category_id' => 'required|exists:tournament_categories,id',
                'amount' => 'required|numeric',
            ]);

            // Periksa apakah member sudah terdaftar di billing detail
            $existingDetail = BillingDetail::where('billing_id', $validatedData['billing_id'])
                ->where('team_member_id', $validatedData['team_member_id'])
                ->first();

            if ($existingDetail) {
                return response()->json([
                    'message' => 'Member already exists in the billing details.'
                ], 409); // 409 Conflict
            }

            // Tambahkan member ke BillingDetail
            $billingDetail = new BillingDetail();
            $billingDetail->tournament_category_id = $validatedData['tournament_category_id'];
            $billingDetail->billing_id = $validatedData['billing_id'];
            $billingDetail->team_member_id = $validatedData['team_member_id'];
            $billingDetail->amount = $validatedData['amount'];
            $billingDetail->save();

            return response()->json([
                'message' => 'Member added successfully to the billing details.',
                'billing_detail' => $billingDetail,
            ], 201); // 201 Created
        } catch (\Exception $e) {
            // Log error jika ada masalah
            \Log::error("Error adding member to billing: " . $e->getMessage());

            return response()->json([
                'message' => 'Failed to add member to the billing details.',
                'error' => $e->getMessage(),
            ], 500); // 500 Internal Server Error
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $data = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
            'notes' => 'nullable|string',
            'member_ids' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            // Find the existing billing record
            $billing = Billing::findOrFail($id);

            // Recalculate the total amount from the provided member IDs
            $totalAmount = 0;
            foreach ($data['member_ids'] as $member_id) {
                $member = TeamMember::with(['matchCategory.tournamentCategories'])
                    ->findOrFail($member_id);
                $totalAmount += $member->matchCategory->tournamentCategories->first()->registration_fee ?? 0;
            }

            // Update the billing record
            $billing->update([
                'tournament_id' => $data['tournament_id'],
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
                'amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            // Sync the billing details
            // Delete existing records for this billing ID
            BillingDetail::where('billing_id', $billing->id)->delete();

            // Insert new billing details
            foreach ($data['member_ids'] as $member_id) {
                $member = TeamMember::with(['matchCategory.tournamentCategories'])
                    ->findOrFail($member_id);

                BillingDetail::create([
                    'billing_id' => $billing->id,
                    'team_member_id' => $member_id,
                    'amount' => $member->matchCategory->tournamentCategories->first()->registration_fee ?? 0,
                    'tournament_category_id' => $member->matchCategory->tournamentCategories->first()->id ?? null,
                ]);
            }

            DB::commit();
            return response()->json($billing->load('billingDetails'), 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateDocument(Request $request, $paymentId)
    {
        try {
            $billing = Billing::findOrFail($paymentId);

            // Validasi file input
            $validatedData = $request->validate([
                'payment_document' => 'required|image|max:8192', // Maksimal 8MB
            ]);

            // Pastikan direktori penyimpanan ada
            $directory = 'public/uploads/payment_documents';
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Ambil file
            $file = $request->file('payment_document');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $path = "uploads/payment_documents/{$filename}";

            // Resize menggunakan ImageManager v3
            $manager = new ImageManager(new Driver()); // ✅ Gunakan Driver langsung
            $resizedImage = $manager->read($file)->scale(width: 400)->encode(); // ✅ Resize dengan cara baru

            // Simpan ke storage publik
            Storage::disk('public')->put($path, $resizedImage);

            // Generate URL file
            $fileUrl = asset("storage/{$path}");

            // Update data billing
            $billing->status = 'waiting for confirmation';
            $billing->payment_document = $fileUrl;
            $billing->save();

            return response()->json([
                'message' => 'Document updated successfully.',
                'file_url' => $fileUrl
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPayment(Request $request, $paymentId)
    {
        $user = auth()->user();

        // Validate input
        $request->validate([
            'status' => 'required|in:paid,failed',
            'reject_reason' => 'required_if:status,failed|string|max:255', // Wajib diisi jika status 'failed'
        ]);

        try {
            // Find billing by ID
            $billing = Billing::findOrFail($paymentId);

            // Update billing status
            $billing->status = $request->input('status');
            $billing->save();

            // If payment is approved (paid), insert the team member data to the tournament participants
            if ($billing->status === 'paid') {
                // Assuming billing has a relationship with billing_details
                $billingDetails = BillingDetail::where('billing_id', $billing->id)->get();

                // Iterate through billing details to insert the data to tournament participants
                foreach ($billingDetails as $billingDetail) {
                    TournamentParticipant::create([
                        'tournament_id' => $billing->tournament_id,
                        'team_member_id' => $billingDetail->team_member_id
                    ]);
                    /*teamMember = TeamMember::find($billingDetail->team_member_id);

                    if ($teamMember) {
                        // Update team member's status to 'approved'
                        $teamMember->registration_status = 'approved';
                        $teamMember->save();
                    }*/
                }
            }

            return response()->json(['message' => 'Payment status and team members updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $billing = Billing::findOrFail($id);

        DB::beginTransaction();

        try {
            $billing->details()->delete();
            $billing->delete();

            DB::commit();
            return response()->json(['message' => 'Billing and details deleted successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
