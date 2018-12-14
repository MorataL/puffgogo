<?php
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use \App\Http\Controllers\ApiController;

$api_domain = config('web.domain');

$http_modules = config('web.http_modules');

foreach ($http_modules as $module) {
    Route::group(['domain' => $api_domain, 'namespace' => $module,'prefix' => strtolower($module)], function() use($module) {
        Route::any('/{controller}/{action}', function ($controller,$action) use($module){
            $controller = 'App\Http\Controllers\Web\\'.$module.'\\'.ucfirst($controller).'Controller';
            if(!class_exists($controller)) return (new ApiController())->appError('控制器不存在');
            $c = new $controller();
            if (!method_exists($controller, $action)) return $c->appError('方法不存在');
            $r = $c->initialize($controller, $action);

            if(empty($r['status'])&& !empty($r['msg'])) return $c->appError($r['msg'],$r['code'],$r['data']);
            $r = $c->$action();
            unset($c);
            return $r;
        });
        Route::any('/{controller}', function ($controller) use($module) {
            $controller = 'App\Http\Controllers\Web\\'.$module.'\\'.ucfirst($controller).'Controller';
            if(!class_exists($controller)) return (new ApiController())->appError('控制器不存在');
            $action = 'index';
            $c = new $controller();
            if (!method_exists($controller, $action)) return $c->appError('方法不存在');
            $r = $c->initialize($controller,$action);
            if(empty($r['status'])&& !empty($r['msg'])) return $c->appError($r['msg'],$r['code'],$r['data']);
            $r = $c->$action();
            unset($c);
            return $r;
        });


    });

}
