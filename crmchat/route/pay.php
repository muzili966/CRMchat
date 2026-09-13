<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

use app\http\middleware\AllowOriginMiddleware;
use app\http\middleware\InstallMiddleware;
use think\facade\Route;

//支付公开接口：收银台面向未登录的付款人，回调面向支付渠道，
//二者都不走登录态——收银台凭链接签名，回调凭渠道验签
Route::group('api/pay', function () {

    Route::get('cashier', 'Cashier/info')->name('payCashierInfo');//收银台：支付单摘要与可选渠道
    Route::post('cashier/pay', 'Cashier/pay')->name('payCashierPay');//收银台：选定渠道下单
    Route::get('cashier/status', 'Cashier/status')->name('payCashierStatus');//收银台：轮询支付状态

    //到账回调：支付宝是表单 POST，微信是 JSON POST，个别场景会有 GET，统一收下由渠道自己解析
    Route::rule('notify/:channel', 'Notify/handle', 'GET|POST')->name('payNotify');

})->prefix('pay.')->middleware([AllowOriginMiddleware::class, InstallMiddleware::class]);
