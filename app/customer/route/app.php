<?php
declare(strict_types=1);

// 货主门户（customer 应用）路由
use app\common\middleware\AuthToken;
use think\facade\Route;

// ---------- 免登录 ----------
Route::post('auth/register', 'Auth/register');
Route::post('auth/login', 'Auth/login');
Route::get('auth/check-email', 'Auth/checkEmail');

// ---------- 需登录 ----------
Route::group('auth', function () {
    Route::get('info', 'Auth/info');
    Route::post('logout', 'Auth/logout');
})->middleware(AuthToken::class);

// 客户资料
Route::group('profile', function () {
    Route::get('/', 'Profile/read');
    Route::post('/', 'Profile/save');
})->middleware(AuthToken::class);

// SKU 产品
Route::group('product', function () {
    Route::get('list', 'Product/list');
    Route::get('stats', 'Product/stats');
    Route::get('detail', 'Product/detail');
    Route::post('create', 'Product/create');
    Route::post('update', 'Product/update');
    Route::post('status', 'Product/status');
    Route::post('delete', 'Product/delete');
    Route::post('print-labels', 'Product/printLabels');
})->middleware(AuthToken::class);

// 入库单
Route::group('inbound', function () {
    Route::get('warehouses', 'Inbound/warehouses');
    Route::get('boxTypes', 'Inbound/boxTypes');
    Route::get('list', 'Inbound/list');
    Route::get('stats', 'Inbound/stats');
    Route::get('detail', 'Inbound/detail');
    Route::get('boxes', 'Inbound/boxes');
    Route::post('create', 'Inbound/create');
    Route::post('update', 'Inbound/update');
    Route::post('submit', 'Inbound/submit');
    Route::post('cancel', 'Inbound/cancel');
    Route::post('delete', 'Inbound/delete');
})->middleware(AuthToken::class);

// 文件上传
Route::group('upload', function () {
    Route::post('image', 'Upload/image');
    Route::post('attachment', 'Upload/attachment');
})->middleware(AuthToken::class);
