<?php

use App\Http\Controllers\Prueba;
use Illuminate\Support\Facades\Route;

Route::post('/cursos', [Prueba::class, 'store']);
