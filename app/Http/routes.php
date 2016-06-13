<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|


|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/


Route::group(['middleware' => ['web']], function () {

    Route::group(['middleware' => ['auth']], function () {
        Route::resource('v1/quest', 'Lists\Quest', ['only' => ['index', 'store']]);
        Route::resource('v1/stage', 'Lists\Stage', ['only' => ['index', 'store']]);
        Route::resource('v1/battle', 'Lists\Battle', ['only' => ['show', 'update']]);
        Route::resource('v1/battle/result', 'Lists\Result', ['only' => ['update']]);
        Route::resource('v1/gacha', 'Lists\Gacha', ['only' => ['index', 'store']]);
        Route::resource('v1/menu', 'Lists\Menu', ['only' => ['index']]);
        Route::resource('v1/order', 'Lists\Order', ['only' => ['index', 'update']]);
    });


Route::get('v1/auth/google', 'Auth\AuthController@redirectToProvider');
Route::get('v1/auth/google/{id}', 'Auth\AuthController@handleProviderCallback');
});
