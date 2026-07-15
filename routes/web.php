<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/demo', function () {
    return 'Hello World';
})->name('login');

// Route::get('/debug/terra-check', function () {
//     return response()->json([
//         'connections' => \App\Models\TerraConnection::latest()->take(5)->get(),
//         'activity_data' => \App\Models\TerraActivityData::latest()->take(5)->get(),
//     ]);
// });
