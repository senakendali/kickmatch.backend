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
use App\Http\Controllers\TournamentMatchController;

use App\Http\Controllers\TournamentSettingController;
use App\Http\Controllers\TournamentActivityController;
use App\Http\Controllers\TournamentMatchCategoryController;
use App\Http\Controllers\TournamentArenaController;
use App\Http\Controllers\TournamentContactPersonController;

use App\Http\Controllers\MatchScheduleController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\SeniMatchController;

use App\Http\Controllers\Api\StaffRoleController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\EoOnboardingController;

use App\Http\Controllers\BlogSettingController;
use App\Http\Controllers\BlogCategoryController;

/*
|--------------------------------------------------------------------------
| Export
|--------------------------------------------------------------------------
*/
Route::get('/team-members/export', [TeamMemberController::class, 'export']);
Route::get('/contingents/export', [ContingentController::class, 'export']);

/*
|--------------------------------------------------------------------------
| Auth (Public)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']); // NOTE: cuma 1x aja
Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Public content
|--------------------------------------------------------------------------
*/
Route::get('countries', [CountryController::class, 'index']);
Route::get('/download-document', [DocumentController::class, 'download']);

Route::get('/blog-post/{slug}', [BlogPostController::class, 'show']);
Route::get('/blog-posts', [BlogPostController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Location master (Public)
|--------------------------------------------------------------------------
| Sesuai project lu: provinces/districts/subdistricts/wards dipakai tanpa auth
*/
Route::apiResource('provinces', ProvinceController::class);
Route::apiResource('districts', DistrictController::class);
Route::apiResource('subdistricts', SubdistrictController::class);
Route::apiResource('wards', WardController::class);

/*
|--------------------------------------------------------------------------
| Tournaments (Mixed)
|--------------------------------------------------------------------------
*/
Route::prefix('tournaments')->group(function () {
    // Index pakai auth (sesuai file lama lu)
    Route::middleware('auth:sanctum')->get('/', [TournamentController::class, 'index']);

    // CRUD selain index
    Route::apiResource('', TournamentController::class)->except(['index']);

    // Custom routes
    Route::middleware('auth:sanctum')->post('register', [TournamentController::class, 'contingentRegistration']);

    Route::get('gallery', [TournamentController::class, 'getTournamentGallery']);
    Route::get('highlight', [TournamentController::class, 'getHighlightedTournament']);
    Route::get('active', [TournamentController::class, 'getActiveTournament']);
    Route::get('all', [TournamentController::class, 'getAllTournament']);
    Route::get('detail/{slug}', [TournamentController::class, 'getTournamentDetail']);

    Route::get('{id}', [TournamentController::class, 'show']);
    Route::get('{tournament_id}/stats', [TournamentController::class, 'getTournamentStats']);
    Route::get('{tournament_id}/contingents', [TournamentController::class, 'getContingentsWithStats']);
    Route::get('{tournament_id}/contingents/summary-by-province', [TournamentController::class, 'summaryByProvince']);
    Route::get('{tournament_id}/participants-by-province', [TournamentController::class, 'getParticipantsByProvince']);
    Route::get('{tournament_id}/participants-by-age-category', [TournamentController::class, 'getParticipantsByAgeCategory']);
    Route::get('{tournament_id}/participants-by-category-class', [TournamentController::class, 'getParticipantsByCategoryClass']);
    Route::get('{tournament_id}/participants-by-district', [TournamentController::class, 'getParticipantsByDistrict']);
    Route::get('{tournament_id}/participants-by-gender', [TournamentController::class, 'getParticipantsByGender']);
    Route::get('{tournament_id}/get-tournament-income', [TournamentController::class, 'getTotalAmountByPaymentStatus']);
    Route::get('{tournament_id}/get-total-income', [TournamentController::class, 'getTotalAmount']);
    Route::get('{tournament_id}/contingents-join-by-date', [TournamentController::class, 'getContingentJoinByDate']);
});

/*
|--------------------------------------------------------------------------
| Protected (Auth Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // user info
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load(['group.roles']);
        $roles = $user->group ? $user->group->roles->pluck('name') : [];

        return [
            'user' => $user,
            'role_names' => $roles,
        ];
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('change-password', [UserController::class, 'changePassword']);

    // Permissions
    Route::get('/user/permissions', [PermissionController::class, 'getUserPermissions']);

    // Menus
    Route::get('/menus', [MenuController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | ✅ EO Onboarding (PENTING: DI DALAM AUTH + DI ATAS OPTIONS catch-all)
    |--------------------------------------------------------------------------
    */
    Route::get('eo/onboarding', [EoOnboardingController::class, 'show']);
    Route::post('eo/onboarding', [EoOnboardingController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Contingents
    |--------------------------------------------------------------------------
    */
    Route::get('contingents/fetch-all', [ContingentController::class, 'fetchAll']);
    Route::get('contingents/my-contingents', [ContingentController::class, 'checkMyContingentsStatus']);
    Route::apiResource('contingents', ContingentController::class);
    Route::post('/contingents/{id}', [ContingentController::class, 'update']); // legacy update (upload)
    Route::get('/contingents/by-tournament/{tournament_id}', [ContingentController::class, 'getByTournament']);

    /*
    |--------------------------------------------------------------------------
    | Team Members
    |--------------------------------------------------------------------------
    */
    Route::apiResource('team-members', TeamMemberController::class);

    /*
    |--------------------------------------------------------------------------
    | Billings
    |--------------------------------------------------------------------------
    */
    Route::post('billings/add-member', [BillingController::class, 'addMember']);
    Route::post('/billings/{paymentId}/update-document', [BillingController::class, 'updateDocument']);
    Route::post('/billings/{paymentId}/confirm-payment', [BillingController::class, 'confirmPayment']);
    Route::apiResource('billings', BillingController::class);

    /*
    |--------------------------------------------------------------------------
    | Master + Tournament Related
    |--------------------------------------------------------------------------
    */
    Route::apiResource('age-categories', AgeCategoryController::class);
    Route::apiResource('championship-categories', ChampionshipCategoryController::class);

    Route::apiResource('category-classes', CategoryClassController::class);
    Route::get('category-classes/fetch-by-age-category/{ageCategoryId}', [CategoryClassController::class, 'fetchByAgeCategory']);
    Route::get('category-classes/fetch-class/{ageCategoryId}', [CategoryClassController::class, 'fetchClass']);
    Route::get('fetch-available-class', [CategoryClassController::class, 'getClassOnTeamMember']);

    Route::apiResource('match-clasifications', MatchClasificationController::class);
    Route::apiResource('match-categories', MatchCategoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Navigation Menus
    |--------------------------------------------------------------------------
    */
    Route::get('navigation-menus/fetch-all', [NavigationMenuController::class, 'fetchAllNavigation']);
    Route::apiResource('navigation-menus', NavigationMenuController::class);

    /*
    |--------------------------------------------------------------------------
    | Drawings / Pools / Brackets
    |--------------------------------------------------------------------------
    */
    Route::get('show-bracket/{tournamentId}/{matchCategoryId}/{ageCategoryId}', [DrawingController::class, 'generateBracket']);
    Route::post('create-pools', [DrawingController::class, 'generatePools']);
    Route::post('/matches/{pool}/create-dummy', [DrawingController::class, 'createDummyOpponent']);

    Route::get('pools', [DrawingController::class, 'getPools']);
    Route::get('pools/{poolId}/match-list', [DrawingController::class, 'getMatchList']);
    Route::get('matches-recap', [DrawingController::class, 'getAllMatchRecap']);
    Route::get('pools/{poolId}', [DrawingController::class, 'detailPool']);
    Route::apiResource('drawings', DrawingController::class);

    /*
    |--------------------------------------------------------------------------
    | Generate Match (Tanding)
    |--------------------------------------------------------------------------
    */
    Route::get('/pools/{poolId}/generate-bracket', [TournamentMatchController::class, 'generateBracket']);
    Route::get('/pools/{poolId}/regenerate-bracket', [TournamentMatchController::class, 'regenerateBracket']);
    Route::post('/pools/next', [TournamentMatchController::class, 'getNextPoolByTournament']);
    Route::get('/pools/{poolId}/matches', [TournamentMatchController::class, 'getMatches']);
    Route::get('/dummy/{poolId}/matches', [TournamentMatchController::class, 'dummy']);
    Route::get('/tournaments/{tournamentId}/matches', [TournamentMatchController::class, 'listMatches']);
    Route::get('/match-schedules/{tournamentId}/matches', [TournamentMatchController::class, 'allMatches']);
    Route::get('/tournaments/{tournamentId}/available-rounds', [TournamentMatchController::class, 'getAvailableRounds']);

    /*
    |--------------------------------------------------------------------------
    | Generate Match (Seni)
    |--------------------------------------------------------------------------
    */
    Route::post('/seni/generate-match', [SeniMatchController::class, 'generate']);
    Route::post('/seni/matches/regenerate', [SeniMatchController::class, 'regenerate']);
    Route::get('/seni/matches', [SeniMatchController::class, 'index']);
    Route::get('/seni/match-list', [SeniMatchController::class, 'matchList']);
    Route::get('/seni/participant-counts/', [SeniMatchController::class, 'getParticipantCounts']);

    /*
    |--------------------------------------------------------------------------
    | Tournament Settings / Activity / Arena / CP
    |--------------------------------------------------------------------------
    */
    Route::get('/tournament-settings', [TournamentSettingController::class, 'index']);
    Route::post('/tournament-settings', [TournamentSettingController::class, 'store']);
    Route::get('/tournament-settings/{tournament_setting}', [TournamentSettingController::class, 'show']);
    Route::post('/tournament-settings/update/{tournament_setting}', [TournamentSettingController::class, 'update']);
    Route::delete('/tournament-settings/{tournament_setting}', [TournamentSettingController::class, 'destroy']);

    Route::apiResource('tournament-activities', TournamentActivityController::class);
    Route::apiResource('tournament-match-categories', TournamentMatchCategoryController::class);

    Route::apiResource('tournament-arenas', TournamentArenaController::class);
    Route::get('/tournaments/{id}/arenas', [TournamentArenaController::class, 'getByTournament']);

    Route::apiResource('tournament-contact-persons', TournamentContactPersonController::class);

    /*
    |--------------------------------------------------------------------------
    | Tournament Schedule
    |--------------------------------------------------------------------------
    */
    Route::get('/tournaments/{slug}/match-schedules/tanding', [MatchScheduleController::class, 'getSchedules']);
    Route::apiResource('match-schedules', MatchScheduleController::class);
    Route::get('/tournaments/{slug}/match-schedules/seni', [SeniMatchController::class, 'getSchedules']);

    /*
    |--------------------------------------------------------------------------
    | Reset / Normalize schedules
    |--------------------------------------------------------------------------
    */
    Route::get('/matches/reset-number/{tournamentId}', [MatchScheduleController::class, 'resetMatchNumber']);
    Route::get('/matches/reset-order/{tournamentId}', [MatchScheduleController::class, 'resetScheduleOrder']);
    Route::get('/matches/regenerate-order/{tournament_id}', [MatchScheduleController::class, 'regenerateMatchNumberAndSave']);
    Route::get('/matches/reorder/{tournament_id}', [MatchScheduleController::class, 'resetScheduleMatchOrder']);

    Route::get('/matches/force-order/{tournament_id}', [MatchScheduleController::class, 'resetMatchOrderBasedOnGetSchedules']);
    Route::get('/matches/reorder-again/{tournament_id}', [MatchScheduleController::class, 'resetScheduleMatchOrderAgain']);

    /*
    |--------------------------------------------------------------------------
    | Export schedules
    |--------------------------------------------------------------------------
    */
    Route::get('/tanding/export-schedule', [MatchScheduleController::class, 'export']);
    Route::get('/seni/export-schedule', [SeniMatchController::class, 'export']);

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */
    Route::get('/sync/matches', [SyncController::class, 'matches']);
    Route::get('/sync/matches/seni', [SyncController::class, 'seniMatches']);
    Route::post('/update-tanding-match-status', [SyncController::class, 'updateTandingMatchStatus']);
    Route::post('/update-seni-match-status', [SyncController::class, 'updateSeniMatchStatus']);
    Route::post('/update-next-match-slot', [SyncController::class, 'updateNextMatchSlot']);

    Route::get('/fetch-match-categories', [MatchCategoryController::class, 'getByTournament']);
    Route::get('/fetch-age-categories', [AgeCategoryController::class, 'getByTournament']);
    Route::get('/fetch-category-classes', [CategoryClassController::class, 'getByTournament']);

    /*
    |--------------------------------------------------------------------------
    | Misc
    |--------------------------------------------------------------------------
    */
    Route::get('/users/count', [UserController::class, 'countUsersWithRole']);

    // Staff Roles
    Route::get('/staff-roles', [StaffRoleController::class, 'index'])->name('staff-roles.index');

    // Blog Settings
    Route::get('/blog-settings', [BlogSettingController::class, 'index']);
    Route::post('/blog-settings', [BlogSettingController::class, 'store']);
    Route::get('/blog-settings/{blog_setting}', [BlogSettingController::class, 'show']);
    Route::post('/blog-settings/update/{blog_setting}', [BlogSettingController::class, 'update']);

    // Blog Category
    Route::apiResource('blog-categories', BlogCategoryController::class);
});

/*
|--------------------------------------------------------------------------
| ✅ Catch-all OPTIONS (WAJIB PALING BAWAH)
|--------------------------------------------------------------------------
*/
Route::options('/{any}', function () {
    return response()->json([], 204);
})->where('any', '.*');
