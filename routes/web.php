<?php

use Illuminate\Support\Facades\Route;

// routes/web.php
Route::get('{any}', function () {
    return view('welcome');  // ou 'app'
})
->where('any', '^(?!api).*$');   // ⬅️ exclut tout ce qui commence par /api
