<?php
declare(strict_types=1);

// 内部运营后台（admin 应用）路由
use app\common\middleware\AdminAuth;
use think\facade\Route;

// ---------- 免登录 ----------
Route::post('auth/login', 'Auth/login');

// ---------- 需登录 ----------
Route::group('auth', function () {
    Route::get('me', 'Auth/me');
    Route::post('logout', 'Auth/logout');
})->middleware(AdminAuth::class);

Route::group('staff', function () {
    Route::get('list', 'Staff/list');
    Route::post('create', 'Staff/create');
    Route::post('update', 'Staff/update');
    Route::post('status', 'Staff/status');
    Route::post('resetpwd', 'Staff/resetpwd');
    Route::post('delete', 'Staff/delete');
})->middleware(AdminAuth::class);

Route::group('role', function () {
    Route::get('list', 'RoleController/list');
    Route::get('all', 'RoleController/all');
    Route::get('menus', 'RoleController/menus');
    Route::post('create', 'RoleController/create');
    Route::post('update', 'RoleController/update');
    Route::post('status', 'RoleController/status');
    Route::post('delete', 'RoleController/delete');
})->middleware(AdminAuth::class);

Route::group('product', function () {
    Route::get('list', 'Sku/list');
    Route::get('stats', 'Sku/stats');
    Route::get('customers', 'Sku/customers');
    Route::post('create', 'Sku/create');
    Route::post('update', 'Sku/update');
    Route::post('status', 'Sku/status');
    Route::post('delete', 'Sku/delete');
    Route::post('print-labels', 'Sku/printLabels');
})->middleware(AdminAuth::class);

Route::group('warehouse', function () {
    Route::get('list', 'Warehouse/list');
    Route::get('stats', 'Warehouse/stats');
    Route::post('create', 'Warehouse/create');
    Route::post('update', 'Warehouse/update');
    Route::post('status', 'Warehouse/status');
    Route::post('delete', 'Warehouse/delete');
})->middleware(AdminAuth::class);

Route::group('inbound', function () {
    Route::get('list', 'Inbound/list');
    Route::get('stats', 'Inbound/stats');
    Route::get('detail', 'Inbound/detail');
    Route::post('approve', 'Inbound/approve');
})->middleware(AdminAuth::class);

Route::group('upload', function () {
    Route::post('image', 'Upload/image');
})->middleware(AdminAuth::class);

Route::group('menu', function () {
    Route::get('tree', 'MenuController/tree');
    Route::post('create', 'MenuController/create');
    Route::post('update', 'MenuController/update');
    Route::post('status', 'MenuController/status');
    Route::post('delete', 'MenuController/delete');
})->middleware(AdminAuth::class);
