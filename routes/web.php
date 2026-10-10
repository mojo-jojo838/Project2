<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MemberAccountController;
use App\Http\Controllers\MemberContactController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\AppealController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('guest');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit')->middleware('guest');
Route::get('/appeal', [AppealController::class, 'create'])->name('appeals.create')->middleware('guest');
Route::post('/appeal', [AppealController::class, 'store'])->name('appeals.store')->middleware(['guest', 'throttle:5,1']);
Route::get('/reset-password', [AppealController::class, 'resetForm'])->name('password.reset.form')->middleware('guest');
Route::post('/reset-password', [AppealController::class, 'resetPassword'])->name('password.reset.submit')->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/admin/dashboard', [AuthController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::post('/admin/members/{user}/approve', [AuthController::class, 'approveMember'])->name('admin.members.approve');
    Route::post('/admin/members/{user}/reject', [AuthController::class, 'rejectMember'])->name('admin.members.reject');
    Route::get('/admin/members', [MemberAccountController::class, 'index'])->name('admin.members.index');
    Route::get('/admin/members/{user}/edit', [MemberAccountController::class, 'edit'])->name('admin.members.edit');
    Route::put('/admin/members/{user}', [MemberAccountController::class, 'update'])->name('admin.members.update');
    Route::delete('/admin/members/{user}', [MemberAccountController::class, 'destroy'])->name('admin.members.destroy');
    Route::post('/admin/members/{user}/restore', [MemberAccountController::class, 'restore'])->name('admin.members.restore');
    Route::get('/admin/memberships', [MembershipController::class, 'index'])->name('admin.memberships.index');
    Route::post('/admin/memberships', [MembershipController::class, 'storeMembership'])->name('admin.memberships.store');
    Route::delete('/admin/memberships/{membership}', [MembershipController::class, 'destroyExpired'])->name('admin.memberships.destroy');
    Route::get('/admin/attendance', [AttendanceController::class, 'index'])->name('admin.attendance.index');
    Route::get('/admin/attendance/scanner', [AttendanceController::class, 'scanner'])->name('admin.attendance.scanner');
    Route::post('/admin/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('admin.attendance.check-in');
    Route::post('/admin/attendance/walk-in', [AttendanceController::class, 'walkIn'])->name('admin.attendance.walk-in');
    Route::get('/admin/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
    Route::get('/admin/reminders', [ReminderController::class, 'index'])->name('admin.reminders.index');
    Route::post('/admin/reminders/queue', [ReminderController::class, 'queueNow'])->name('admin.reminders.queue');
    Route::get('/admin/appeals', [AppealController::class, 'index'])->name('admin.appeals.index');
    Route::patch('/admin/appeals/{appeal}', [AppealController::class, 'update'])->name('admin.appeals.update');
    Route::get('/member/dashboard', [AuthController::class, 'memberDashboard'])->name('member.dashboard');
    Route::get('/member/qr-code', [AttendanceController::class, 'qr'])->name('member.qr');
    Route::get('/member/id-card', [AttendanceController::class, 'downloadId'])->name('member.id.download');
    Route::put('/member/contact', [MemberContactController::class, 'update'])->name('member.contact.update');
});
