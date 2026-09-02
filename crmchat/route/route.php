<?php

use app\http\middleware\InstallMiddleware;
use think\facade\Route;

Route::get('install/index', 'InstallController/index');//安装程序
Route::post('install/index', 'InstallController/index');//安装程序
Route::get('upgrade/index', 'UpgradeController/index');
Route::get('upgrade/upgrade', 'UpgradeController/upgrade');

Route::get('/', function () {
    //定价直接由服务端注入：首屏即有内容，无需前端二次请求，对搜索引擎也友好。
    //取不到时给空数组，官网其余部分照常展示，不因套餐读取失败而整页失败。
    try {
        /** @var \app\services\TenantPlanServices $planServices */
        $planServices = app()->make(\app\services\TenantPlanServices::class);
        $plans = $planServices->getPublicPricing();
    } catch (\Throwable $e) {
        \think\facade\Log::error('官网定价读取失败：' . $e->getMessage());
        $plans = [];
    }
    return view('website/index', ['plans' => $plans]);
})->middleware(InstallMiddleware::class);

Route::group('/', function () {
    Route::miss(function () {
        return view(app()->getRootPath() . 'public' . DS . 'admin' . DS . 'index.html');
    });
})->middleware(InstallMiddleware::class);
