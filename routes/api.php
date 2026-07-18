<?php

use App\Http\Controllers\BeneficiaryProfileController;
use App\Http\Controllers\Api\DonorProfileController;
use App\Http\Controllers\Api\VolunteerProfileController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorProfileController as ControllersDonorProfileController;
use App\Http\Controllers\DorationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VolunterProfileController as ControllersVolunterProfileController;

Route::prefix('auth')->group(function () {
    // PUBLIC ROUTES
    Route::post('/register', [UserController::class, 'register']);

    Route::post('/login', [UserController::class, 'login']);
    Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/resend-otp', [UserController::class, 'resendOtp']);
    // PROTECTED ROUTES
    Route::middleware('auth:sanctum')->group(function () {
         Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
        Route::post('/select-role', [UserController::class, 'selectRole']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
        Route::post('/logout', [UserController::class, 'logout']);
    });

});
  Route::middleware('auth:sanctum')->group(function () {
 Route::prefix('beneficiary')->group(function () {
        Route::post('/complete-profile', [BeneficiaryProfileController::class, 'completeProfile']);
        Route::get('/get-profile', [BeneficiaryProfileController::class, 'getProfile']);
        Route::get('/cities', [CityController::class, 'index']);
         Route::get('/TypeNeeds', [TypeController::class, 'index']);

 });
 Route::prefix('donor')->group(function () {
        Route::post('/complete-profile', [ControllersDonorProfileController::class, 'completeProfile']);
        Route::post('/donations', [DonationController::class, 'store']);
        Route::get('/get-profile', [ControllersDonorProfileController::class, 'getProfile']);
        Route::get('/cities', [CityController::class, 'index']);
 });
 Route::prefix('volunteer')->group(function () {
        Route::post('/complete-profile', [ControllersVolunterProfileController::class, 'completeProfile']);
        Route::get('/get-profile', [ControllersVolunterProfileController::class, 'getProfile']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/days',[DayController::class,'index']);
        Route::get('/skills', [SkillController::class, 'index']);
 });
 });
 // routes for dashboard
Route::post('/login', [EmployeeController::class, 'login']);
Route::post('/donations', [DorationController::class, 'store']);
Route::get('/donations', [DorationController::class, 'index']);
Route::get('/donations/{id}', [DorationController::class, 'show']);
Route::delete('/donations/{id}', [DorationController::class, 'destroy']);
Route::get('/users/exportDonations', [DorationController::class, 'export']);


Route::get('/beneficiaries', [BeneficiaryProfileController::class, 'index']);
Route::get('/beneficiaries/{id}', [BeneficiaryProfileController::class, 'show']);
Route::post('/beneficiaries', [BeneficiaryProfileController::class, 'store']);
Route::patch('/beneficiaries/{id}/status', [BeneficiaryProfileController::class, 'updateStatus']);
Route::delete('/beneficiaries/{id}', [BeneficiaryProfileController::class, 'destroy']);
Route::controller(CampaignController::class)->group(function () {
    Route::get('/campaigns/getAll', 'getAll');
    Route::post('/campaigns/store', 'store');
    Route::get('/campaigns/show/{id}', 'showCampaign');
    Route::put('/campaigns/update/{id}', 'update');
    Route::delete('/campaigns/delete/{id}', 'destroy');
});

Route::middleware('auth:sanctum')->group(function () {
Route::post('/logout', [EmployeeController::class, 'logout']);
});
