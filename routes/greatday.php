<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Greatday (attendance app) — migrasi dari Intilab-Internal
| Prefix: /api/greatday
|--------------------------------------------------------------------------
*/

$router->group(['prefix' => 'api/greatday', 'namespace' => 'Greatday'], function () use ($router) {
    $router->post('login', 'AuthController@login');
    $router->post('logout', ['middleware' => 'greatday.auth', 'uses' => 'AuthController@logout']);
    $router->post('forgot-password', 'AuthController@forgotPassword');
    $router->post('register', 'AuthController@register');

    $router->group(['middleware' => 'greatday.auth'], function () use ($router) {
        $router->get('profile/me', 'AuthController@me');
        $router->post('cektoken', 'AuthController@checkToken');
    });

    $router->get('foto-karyawan/{image}', 'HomePageController@getFoto');
    $router->get('foto-absen/{image}', 'HomePageController@getFotoAbsen');

    $router->group([
        'middleware' => ['greatday.auth', 'log.request', 'decrypt.slice'],
    ], function () use ($router) {
        $router->post('route', 'BaseRouteController@handle');
    });
});
