<?php

use app\http\middleware\InstallMiddleware;
use think\facade\Route;

Route::get('install/index', 'InstallController/index');//安装程序
Route::post('install/index', 'InstallController/index');//安装程序
Route::get('upgrade/index', 'UpgradeController/index');
Route::get('upgrade/upgrade', 'UpgradeController/upgrade');

Route::get('/', 'WebsiteController/index')->middleware(InstallMiddleware::class);

//官网公开接口：面向未登录访客，仅合作意向提交
Route::post('website/lead', 'WebsiteController/lead')->middleware(InstallMiddleware::class);

Route::group('/', function () {
    Route::miss(function () {
        return view(app()->getRootPath() . 'public' . DS . 'admin' . DS . 'index.html');
    });
})->middleware(InstallMiddleware::class);
