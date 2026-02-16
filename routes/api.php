<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;


Route::group(
    [
        'middleware' => ['web'],
        'prefix' => 'v1'
    ],
    function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);

        Route::group(['middleware' => 'auth:sanctum'], function () {
            Route::post('/auth/logout', [AuthController::class, 'logout']);
            Route::get('/auth/user', [AuthController::class, 'user']);
        });
    }
);
