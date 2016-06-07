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

    Route::resource('v1/quest', 'Lists\Quest', ['only' => ['index', 'store']]);
    Route::resource('v1/stage', 'Lists\Stage', ['only' => ['index', 'store']]);
    Route::resource('v1/battle', 'Lists\Battle', ['only' => ['show', 'update']]);
    Route::resource('v1/battle/result', 'Lists\Result', ['only' => ['update']]);

    Route::resource('v1/gacha', 'Lists\Gacha', ['only' => ['index', 'store']]);

    Route::resource('v1/menu', 'Lists\Menu', ['only' => ['index']]);

    Route::resource('v1/order', 'Lists\Order', ['only' => ['index', 'update']]);

    Route::resource('v1/auth/google/{id}', 'Lists\Auth', ['only' => ['index']]);
});

/*//認証のルート定義
Route::get('auth/login', 'Auth\AuthController@getLogin');
Route::post('auth/login', 'Auth\AuthController@postLogin');
Route::get('auth/logout', 'Auth\AuthController@getLogout');

//登録のルート定義
Route::get('auth/legister', 'Auth\AuthController@getRegister');
Route::post('auth/register', 'Auth\AuthController@postRegister');*/
#Route::group(['middleware' => 'auth'], function (){


#});