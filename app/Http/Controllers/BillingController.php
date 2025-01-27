<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\BillingDetail;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function index()
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|unique:billings',
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
            'amount' => 'required|numeric',
            'status' => 'required|in:pending,paid,failed',
            'notes' => 'nullable|string',
            'billing_details' => 'required|array',
            'billing_details.*.team_member_id' => 'required|exists:team_members,id',
            'billing_details.*.amount' => 'required|numeric',
            'billing_details.*.tournament_category_id' => 'required|exists:tournament_categories,id',
        ]);

        DB::beginTransaction();

        try {
            // Create the billing record
            $billing = Billing::create($data);

            // Loop through billing details and create them
            foreach ($data['billing_details'] as $detail) {
                $billing->details()->create($detail);
            }

            DB::commit();
            return response()->json($billing->load('details'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $billing = Billing::with('details')->findOrFail($id);
        return response()->json($billing);
    }

    public function update(Request $request, $id)
    {
        $billing = Billing::findOrFail($id);

        $data = $request->validate([
            'invoice_number' => 'string|unique:billings,invoice_number,' . $billing->id,
            'tournament_id' => 'exists:tournaments,id',
            'user_id' => 'exists:users,id',
            'bank_name' => 'string',
            'account_number' => 'string',
            'account_name' => 'string',
            'amount' => 'numeric',
            'status' => 'in:pending,paid,failed',
            'notes' => 'nullable|string',
            'billing_details' => 'array',
            'billing_details.*.team_member_id' => 'required_with:billing_details|exists:team_members,id',
            'billing_details.*.amount' => 'required_with:billing_details|numeric',
            'billing_details.*.tournament_category_id' => 'required_with:billing_details|exists:tournament_categories,id',
        ]);

        DB::beginTransaction();

        try {
            // Update the billing record
            $billing->update($data);

            // Update or recreate billing details if provided
            if (isset($data['billing_details'])) {
                $billing->details()->delete();

                foreach ($data['billing_details'] as $detail) {
                    $billing->details()->create($detail);
                }
            }

            DB::commit();
            return response()->json($billing->load('details'));

        } catch (\Exception $e) {
            DB::rollBack();
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
