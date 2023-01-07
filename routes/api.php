<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PetController;
use App\Models\Category;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/pet', [PetController::class, 'addPet']);
Route::put('/pet', [PetController::class, 'updatePet']);
Route::get('/pet/findByStatus', [PetController::class, 'petsByStatus']);
Route::get('/pet/{id}', [PetController::class, 'singlePet']);
Route::post('/pet/{id}', [PetController::class, 'editPet']);
Route::delete('/pet/{id}', [PetController::class, 'deletePet']);
Route::get('/categories', function () {
    $categories = Category::all()->pluck('id', 'name');
    return response()->json($categories);
});