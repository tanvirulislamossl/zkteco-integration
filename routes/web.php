<?php

use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ZktecoRequestController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post', 'put', 'head'], '/', [ZktecoRequestController::class, 'root'])->name('punches');

Route::match(['get', 'post', 'put'], 'iclock/{path?}', [ZktecoRequestController::class, 'capture'])
    ->where('path', '.*');

Route::get('/punches/{log}/edit', [AttendanceLogController::class, 'edit'])->name('punches.edit');
Route::put('/punches/{log}', [AttendanceLogController::class, 'update'])->name('punches.update');
Route::post('/punches/{log}/restore', [AttendanceLogController::class, 'restore'])->name('punches.restore');

Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::post('/students', [StudentController::class, 'store'])->name('students.store');
Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
Route::get('/students/import/template', [StudentController::class, 'importTemplate'])->name('students.import-template');
Route::post('/students/sync', [StudentController::class, 'syncAll'])->name('students.sync-all');
Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
Route::post('/students/{student}/sync', [StudentController::class, 'sync'])->name('students.sync');
