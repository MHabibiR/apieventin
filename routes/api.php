<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\AdminWebController;
use App\Http\Controllers\Api\OrganizerWebController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\EventController;
use App\Http\Middleware\RoleMiddleware;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/events', [EventController::class, 'index']);
Route::get('/event-detail/{id}', [EventController::class, 'show']);
Route::get('/events/{id}/seats', [EventController::class, 'getSeats']);


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register-organizer', [AuthController::class, 'registerOrganizer']);
Route::post('/login-organizer', [AuthController::class, 'loginOrganizer']);


Route::get('/verify-certificate/{cert_id}', [TransactionController::class, 'verifyCertificate']);

Route::middleware([RoleMiddleware::class . ':user,organizer,main_admin'])->group(function () {

    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::get('/my-tickets', [TransactionController::class, 'getMyTickets']);

    Route::put('/profile/edit-profile', [ProfileController::class, 'updateProfile']);
    Route::delete('/profile/delete', [ProfileController::class, 'deleteAccount']);

    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/refresh', [AuthController::class, 'refreshToken']);

    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::get('/ticket-qr/{kode_transaksi}', [TransactionController::class, 'getTicketQr']);
    Route::get('/detail-ticket/{kode_transaksi}', [TransactionController::class, 'showDetailTicket']);

    Route::get('/my-certificates', [TransactionController::class, 'getMyCertificates']);
    Route::get('/download-certificate/{kode_transaksi}', [TransactionController::class, 'downloadCertificate']);

    Route::post('/checkout-manual', [TransactionController::class, 'checkoutManual']);
});


Route::middleware([RoleMiddleware::class . ':main_admin'])->group(function () {
    
    Route::get('/admin/dashboard', [AdminWebController::class, 'getDashboardStats']);
    Route::get('/admin/organizers', [AdminWebController::class, 'getAllOrganizers']);
    Route::post('/admin/organizers/{id}/status', [AdminWebController::class, 'updateOrganizerStatus']);
    Route::get('/admin/proposals', [AdminWebController::class, 'getAllProposals']);
    Route::post('/admin/proposals/{id}/status', [AdminWebController::class, 'updateProposalStatus']);
    Route::get('/admin/events', [AdminWebController::class, 'getAllEvents']);
    Route::post('/admin/events', [AdminWebController::class, 'storeEvent']);
    Route::post('/admin/events/{id}/status', [AdminWebController::class, 'updateEventStatus']);
    Route::delete('/admin/events/{id}', [AdminWebController::class, 'deleteEvent']);
});

Route::middleware([RoleMiddleware::class . ':organizer'])->group(function () {
    Route::get('/organizer/dashboard', [OrganizerWebController::class, 'getDashboardStats']);
    Route::get('/organizer/events', [OrganizerWebController::class, 'getMyEvents']);
    Route::post('/organizer/events', [OrganizerWebController::class, 'storeMyEvent']);
    Route::get('/organizer/participants', [OrganizerWebController::class, 'getParticipants']);
    Route::get('/organizer/participants/export', [OrganizerWebController::class, 'exportCsv']);
    Route::post('/organizer/participants/manual', [OrganizerWebController::class, 'storeManualParticipant']);
    Route::post('/organizer/participants/{id}/mark-paid', [OrganizerWebController::class, 'markAsPaid']);
    Route::delete('/organizer/participants/{id}', [OrganizerWebController::class, 'deleteParticipant']);
    Route::get('/organizer/checkin-history', [OrganizerWebController::class, 'getCheckinHistory']);
    Route::get('/organizer/transactions/pending', [OrganizerWebController::class, 'getPendingTransactions']);
    Route::post('/organizer/transactions/{id}/validate', [OrganizerWebController::class, 'validateTransaction']);

    Route::post('/organizer/checkin/verify', [OrganizerWebController::class, 'verifyCheckin']);
    Route::get('/organizer/seating/{id}', [OrganizerWebController::class, 'getSeating']);
    Route::post('/organizer/seating/update', [OrganizerWebController::class, 'updateSeating']);
    Route::get('/organizer/lucky-draw/{id}', [OrganizerWebController::class, 'getLuckyDrawData']);
    Route::post('/organizer/lucky-draw/winner', [OrganizerWebController::class, 'storeLuckyDrawWinner']);
    Route::get('/organizer/certificates/{id}', [OrganizerWebController::class, 'getCertificates']);
    Route::post('/organizer/certificates/publish', [OrganizerWebController::class, 'publishCertificate']);
});