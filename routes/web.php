<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RegisterController::class, 'create']);
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::get('/employee/leave', [LeaveRequestController::class, 'employee'])->name('employee.leave');
    Route::post('/employee/leave', [LeaveRequestController::class, 'store'])->name('leave.store');
    Route::get('/admin/leave', [LeaveRequestController::class, 'admin'])->name('admin.leave');
    Route::patch('/admin/leave/{leave}', [LeaveRequestController::class, 'review'])->name('leave.review');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/admin', [AttendanceController::class, 'admin'])->name('admin.dashboard');
    Route::get('/admin/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employee', [AttendanceController::class, 'employee'])->name('employee.dashboard');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/admin/employees/{employee}/account', [EmployeeController::class, 'createAccount'])->name('employees.account');
    Route::post('/employees/{employee}/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/employees/{employee}/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::post('/logout', [AttendanceController::class, 'logout'])->name('logout');
});
