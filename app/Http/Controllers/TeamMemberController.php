<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use App\Models\BillingDetail;
use App\Models\AgeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TeamMemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $fetchAll = $request->query('fetch_all', false);
            $is_payment_confirmation = $request->query('is_payment_confirmation', false);
            $tournamentId = $request->query('tournament_id');

            $user = auth()->user();
            $search = $request->input('search', '');

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // 🔗 Query awal + eager loading
            $query = TeamMember::with([
                'contingent.tournamentContingents.tournament', // untuk tournament_name
                'championshipCategory',
                'matchCategory.tournamentCategories'
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

            // 🔐 Filter berdasarkan grup user
            if ($user->group && $user->group->name === 'Owner') {
                // lihat semua
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

            // 💰 Filter billing confirmation
            if ($is_payment_confirmation) {
                $query->whereHas('billingDetails');
            }

            // 📦 Ambil data
            $members = $fetchAll ? $query->get() : $query->paginate(10);

            // 🔁 Transform untuk inject tournament_name & billing flag
            $transform = function ($member) {
                $tournamentName = optional(
                    $member->contingent?->tournamentContingents->first()?->tournament
                )->name;

                $member->tournament_name = $tournamentName;
                $member->exists_in_billing_details = BillingDetail::where('team_member_id', $member->id)->exists();

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

        $query = TeamMember::with('contingent');

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

        $teamMembers = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->fromArray([
            ['ID', 'Contingent', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Jenis Kelamin', 'Tinggi Badan', 'Berat Badan', 'NIK', 'No. KK', 'Alamat']
        ], null, 'A1');

        // Data
        $row = 2;
        foreach ($teamMembers as $member) {
            $sheet->fromArray([
                $member->id,
                $member->contingent->name ?? '',
                $member->name,
                $member->birth_place,
                $member->birth_date,
                $member->gender,
                $member->body_height,
                $member->body_weight,
                $member->nik,
                $member->family_card_number,
                $member->address,
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'team_members_' . date('Ymd') . '.xlsx';

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



    

    public function index_(Request $request)
    {
        try {
            // Check for a query parameter to decide the mode
            $fetchAll = $request->query('fetch_all', false);
            $is_payment_confirmation = $request->query('is_payment_confirmation', false);

            // Get the authenticated user
            $user = auth()->user();
            $search = $request->input('search', ''); // Mendapatkan parameter search dari request

            // Ensure the user is authenticated
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Determine if the user is an owner
            if ($user->group->name === 'Owner') {
                // Fetch all members without filtering by owner_id
                if ($fetchAll) {
                    if ($is_payment_confirmation) {
                        // Ambil data member yang ada di BillingDetail saat payment confirmation
                        $members = TeamMember::with(['contingent', 'championshipCategory', 'matchCategory.tournamentCategories'])
                            ->whereHas('billingDetails')  // Pastikan hanya mengambil anggota yang ada di BillingDetail
                            ->when($search, function ($query, $search) {
                                // Apply search on both name and contingent name
                                $query->where('name', 'like', '%' . $search . '%')
                                    ->orWhereHas('contingent', function ($contingentQuery) use ($search) {
                                        $contingentQuery->where('name', 'like', '%' . $search . '%');
                                    })
                                    ->orWhereHas('matchCategory', function ($matchCategoryQuery) use ($search) {
                                        $matchCategoryQuery->where('name', 'like', '%' . $search . '%');
                                    });
                            })
                            ->get();
                    } else {
                        // Ambil semua member tanpa filter
                        $members = TeamMember::with(['contingent', 'championshipCategory', 'matchCategory.tournamentCategories'])
                            ->when($search, function ($query, $search) {
                                // Apply search on both name and contingent name
                                $query->where('name', 'like', '%' . $search . '%')
                                    ->orWhereHas('contingent', function ($contingentQuery) use ($search) {
                                        $contingentQuery->where('name', 'like', '%' . $search . '%');
                                    })
                                    ->orWhereHas('matchCategory', function ($matchCategoryQuery) use ($search) {
                                        $matchCategoryQuery->where('name', 'like', '%' . $search . '%');
                                    });
                            })
                            ->get();
                    }
                } else {
                    $members = TeamMember::with(['contingent', 'championshipCategory', 'matchCategory.tournamentCategories'])
                        ->when($search, function ($query, $search) {
                            // Apply search on both name and contingent name
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('contingent', function ($contingentQuery) use ($search) {
                                    $contingentQuery->where('name', 'like', '%' . $search . '%');
                                })
                                ->orWhereHas('matchCategory', function ($matchCategoryQuery) use ($search) {
                                    $matchCategoryQuery->where('name', 'like', '%' . $search . '%');
                                });
                        })
                        ->paginate(10);
                }
            } else {
                if ($fetchAll) {
                    // Fetch all members without pagination
                    $members = TeamMember::with(['contingent', 'championshipCategory', 'matchCategory.tournamentCategories'])
                        ->whereHas('contingent', function ($query) use ($user) {
                            $query->where('owner_id', $user->id);
                        })
                        ->when($search, function ($query, $search) {
                            // Apply search on both name and contingent name
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('contingent', function ($contingentQuery) use ($search) {
                                    $contingentQuery->where('name', 'like', '%' . $search . '%');
                                })
                                ->orWhereHas('matchCategory', function ($matchCategoryQuery) use ($search) {
                                    $matchCategoryQuery->where('name', 'like', '%' . $search . '%');
                                });
                        })
                        ->get();
                } else {
                    // Fetch members filtered by the user's owner_id
                    $members = TeamMember::with(['contingent', 'championshipCategory', 'matchCategory.tournamentCategories'])
                        ->whereHas('contingent', function ($query) use ($user) {
                            $query->where('owner_id', $user->id);
                        })
                        ->when($search, function ($query, $search) {
                            // Apply search on both name and contingent name
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('contingent', function ($contingentQuery) use ($search) {
                                    $contingentQuery->where('name', 'like', '%' . $search . '%');
                                })
                                ->orWhereHas('matchCategory', function ($matchCategoryQuery) use ($search) {
                                    $matchCategoryQuery->where('name', 'like', '%' . $search . '%');
                                });
                        })
                        ->paginate(10);
                }
            }

            // Check if each member exists in BillingDetail
            $members->transform(function ($member) {
                $member->exists_in_billing_details = BillingDetail::where('team_member_id', $member->id)->exists();
                return $member;
            });

            return response()->json($members, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            'championship_category_id' => 'required|exists:championship_categories,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'age_category_id' => 'required|exists:age_categories,id',
            'category_class_id' => 'required|exists:category_classes,id',
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
            'gender' => 'required|in:male,female',
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
            'championship_category_id' => 'required|exists:championship_categories,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'age_category_id' => 'required|exists:age_categories,id',
            'category_class_id' => 'required|exists:category_classes,id',
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
            'championship_category_id' => $data['championship_category_id'],
            'match_category_id' => $data['match_category_id'],
            'age_category_id' => $data['age_category_id'],
            'category_class_id' => $data['category_class_id'],
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
        try {
            $teamMember = TeamMember::findOrFail($id);
            $teamMember->delete();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
