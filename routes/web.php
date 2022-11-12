<?php

use App\Scenarios;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group.
|
*/

Route::view('/', 'schedule', [
    'scenarios' => Scenarios::all(),
    'zones' => DateTimeZone::listIdentifiers(),
]);
