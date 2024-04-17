<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('user')->group(function(){
    // Регистрация
    Route::post('registration', [UserController::class,'registration']);
    // Аунтифицироваться
    Route::post('authorization', [UserController::class,'authorization']);
    // выйти
    Route::get('logout', [UserController::class,'logout']);
    // is Admin
    Route::get('isAdmin',[UserController::class,'isAdmin']);
    // is Seller
    Route::get('isSeller',[UserController::class,'isSeller']);
    // Получить все покупки пользователя
    Route::get('getAllPurchases',[UserController::class, 'getAllPurchases']);
    // Получить всех пользователей
    Route::get('getAllUsers',[UserController::class,'getAllUsers']);
    // Админ удаляет покупку пользователя
    Route::post('deletePurchase', [UserController::class,'deletePurchase']);

    
});

Route::prefix('products')->group(function(){
    // Создание карточки продукта
    Route::post('createProduct',[UserController::class,'createProduct']);
    // получение всех карточек
    Route::get('getAllProduct',[UserController::class,'getAllProduct']);
    Route::get('getAllCategories',[UserController::class,'getAllCategories']);
    // Получение опредделенной карточки продукта по id
    Route::get('{product_id}/details',[UserController::class,'getProductDetails']);
    // Оставить коментарий
    Route::post('{product_id}/comment',[UserController::class,'postProductComment']);
    // Оставить jwtyre
    Route::post('{product_id}/raiting',[UserController::class,'postProductRaiting']);
    // Купить продукт
    Route::post('{product_id}/buy',[UserController::class,'makePurchase']);
});