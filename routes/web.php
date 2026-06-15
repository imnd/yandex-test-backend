<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Yandex Maps Reviews Parser API is running.']);
});
