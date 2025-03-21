<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\SubdistrictController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NavigationMenuController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ContingentController; 
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\AgeCategoryController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryClassController;
use App\Http\Controllers\MatchClasificationController;
use App\Http\Controllers\MatchCategoryController;
use App\Http\Controllers\ChampionshipCategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\DrawingController;
use App\Http\Controllers\UserController;
use App\Models\TeamMember;

Route::get('/team-members/export', function () {
    // Ambil data dari database
    $teamMembers = TeamMember::with('contingent')->get();

    // Membuat file Excel (CSV format) sebagai output
    $filename = 'team_members.xlsx';

    // Set headers untuk file Excel
    return response()->stream(function () use ($teamMembers) {
        // Buka output stream untuk menulis ke browser
        $file = fopen('php://output', 'w');

        // Menambahkan header kolom
        fputcsv($file, ['ID', 'Contingent', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Jenis Kelamin', 'Tinggi Badan', 'Berat Badan', 'NIK', 'Nomor Kartu Keluarga', 'Alamat']);

        // Menambahkan data ke dalam sheet
        foreach ($teamMembers as $teamMember) {
            fputcsv($file, [
                $teamMember->id,
                $teamMember->contingent->name,
                $teamMember->name,
                $teamMember->birth_place,
                $teamMember->birth_date,
                $teamMember->gender,
                $teamMember->body_height,
                $teamMember->body_weight,
                $teamMember->nik,
                $teamMember->family_card_number,
                $teamMember->address
            ]);
        }

        // Menutup file setelah selesai
        fclose($file);
    }, 200, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ]);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    // Eager-load group and roles
    $user->load(['group.roles']);

    // Add role names to the response
    $roles = $user->group ? $user->group->roles->pluck('name') : [];

    return [
        'user' => $user,
        'role_names' => $roles, // Returns a list of role names
    ];
});

// Permissions
Route::middleware(['auth:sanctum'])->get('/user/permissions', [PermissionController::class, 'getUserPermissions']);

// Menus
Route::middleware('auth:sanctum')->get('/menus', [MenuController::class, 'index']);

//Tournaments

//Route::apiResource('tournaments', TournamentController::class);

Route::prefix('tournaments')->group(function () {
    // Resource route for CRUD operations
    Route::apiResource('', TournamentController::class);

    // Custom route
    Route::middleware('auth:sanctum')->post('register', [TournamentController::class, 'contingentRegistration']);
    Route::get('gallery', [TournamentController::class, 'getTournamentGallery']);
    Route::get('highlight', [TournamentController::class, 'getHighlightedTournament']);
    Route::get('active', [TournamentController::class, 'getActiveTournament']);
    Route::get('detail/{slug}', [TournamentController::class, 'getTournamentDetail']); 
    Route::get('{id}', [TournamentController::class, 'show']);
    Route::get('{tournament_id}/stats', [TournamentController::class, 'getTournamentStats']);
    Route::get('{tournament_id}/contingents', [TournamentController::class, 'getContingentsWithStats']);
    Route::get('{tournament_id}/contingents/summary-by-province', [TournamentController::class, 'summaryByProvince']);
    Route::get('{tournament_id}/participants-by-province', [TournamentController::class, 'getParticipantsByProvince']);
    Route::get('{tournament_id}/participants-by-age-category', [TournamentController::class, 'getParticipantsByAgeCategory']);
    Route::get('{tournament_id}/participants-by-category-class', [TournamentController::class, 'getParticipantsByCategoryClass']);
    Route::get('{tournament_id}/participants-by-district', [TournamentController::class, 'getParticipantsByDistrict']);

    
    
});

//Provinces
Route::apiResource('provinces', ProvinceController::class);
//Districts
Route::apiResource('districts', DistrictController::class);
//Subdistricts
Route::apiResource('subdistricts', SubdistrictController::class);
//Wards
Route::apiResource('wards', WardController::class);
//Age Categories
Route::apiResource('age-categories', AgeCategoryController::class);

//Championship Categories
Route::apiResource('championship-categories', ChampionshipCategoryController::class);

//Navigation Menus
Route::get('navigation-menus/fetch-all', [NavigationMenuController::class, 'fetchAllNavigation']);
Route::apiResource('navigation-menus', NavigationMenuController::class);

//Contingents
Route::middleware('auth:sanctum')->get('contingents/fetch-all', [ContingentController::class, 'fetchAll']);
Route::middleware('auth:sanctum')->get('contingents/my-contingents', [ContingentController::class, 'checkMyContingentsStatus']);
Route::middleware('auth:sanctum')->apiResource('contingents', ContingentController::class);


//Team Members
/*Route::get('/team-members/export', function () {
    return Excel::download(new TeamMembersExport, 'team_members.xlsx');
});*/
Route::middleware('auth:sanctum')->apiResource('team-members', TeamMemberController::class);

//Documents
Route::get('/download-document/{filename}', [DocumentController::class, 'download']);

//Billings
Route::middleware('auth:sanctum')->post('billings/add-member', [BillingController::class, 'addMember']);
Route::middleware('auth:sanctum')->put('/billings/{paymentId}/update-document', [BillingController::class, 'updateDocument']);
Route::middleware('auth:sanctum')->put('/billings/{paymentId}/confirm-payment', [BillingController::class, 'confirmPayment']);
Route::middleware('auth:sanctum')->apiResource('billings', BillingController::class);

//countries
Route::get('countries', [CountryController::class, 'index']);

//Category Classes
Route::apiResource('category-classes', CategoryClassController::class);
Route::get('category-classes/fetch-by-age-category/{ageCategoryId}', [CategoryClassController::class, 'fetchByAgeCategory']);
Route::get('category-classes/fetch-class/{ageCategoryId}', [CategoryClassController::class, 'fetchClass']);
Route::get('fetch-available-class', [CategoryClassController::class, 'getClassOnTeamMember']);

//Match Clasifications
Route::apiResource('match-clasifications', MatchClasificationController::class);

//Match Categories
Route::apiResource('match-categories', MatchCategoryController::class);

//Drawings
Route::get('show-bracket/{tournamentId}/{matchCategoryId}/{ageCategoryId}', [DrawingController::class, 'generateBracket']);
Route::apiResource('drawings', DrawingController::class);

//Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::get('/users/count', [UserController::class, 'countUsersWithRole']);

//Export



