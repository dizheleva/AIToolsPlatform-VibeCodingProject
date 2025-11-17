<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Hello from Laravel!';
});

Route::get('/test', function () {
    return 'Test route works!';
});

Route::get('/api/status', function () {
    return response()->json(['status' => 'ok', 'message' => 'API is working']);
});

