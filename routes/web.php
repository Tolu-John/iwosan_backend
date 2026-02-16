<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommTemplateWebController;
use App\Http\Controllers\CommStatusWebController;


Route::get('/', function () {

	return view('welcome');

});

Route::middleware(['auth'])->group(function () {
    Route::get('/admin/comm/templates', [CommTemplateWebController::class, 'index']);
    Route::post('/admin/comm/templates', [CommTemplateWebController::class, 'store']);
    Route::patch('/admin/comm/templates/{id}', [CommTemplateWebController::class, 'update']);
    Route::post('/admin/comm/templates/{id}/deactivate', [CommTemplateWebController::class, 'deactivate']);
    Route::get('/admin/comm/status', [CommStatusWebController::class, 'index']);
});
