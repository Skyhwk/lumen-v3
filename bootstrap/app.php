<?php
require_once __DIR__ . '/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

date_default_timezone_set('Asia/Jakarta');
/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

$app->withFacades();

$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
|
| Now we will register the "app" configuration file. If the file exists in
| your configuration directory it will be loaded; otherwise, we'll load
| the default version. You may register other files below as needed.
|
*/

$app->configure('app');
$app->configure('cache');
$app->configure('logging');
$app->configure('database');
$app->configure('queue');
$app->configure('harga_kategori');
$app->configure('kategori');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

$app->middleware([
    App\Http\Middleware\CorsMiddleware::class,
    App\Http\Middleware\Utf8Sanitizer::class
]);

$app->routeMiddleware([
    'auth.token' => App\Http\Middleware\CheckToken::class,
    'log.request' => App\Http\Middleware\LogRequest::class,
    'decrypt.slice' => \App\Http\Middleware\DecryptSliceMiddleware::class,
    'cors' => \App\Http\Middleware\CorsMiddleware::class,
    'auth.customer.token' => App\Http\Middleware\CheckCustomerToken::class,
    'director.auth.token' => App\Http\Middleware\directorApp\ApiTokenAuth::class,
]);

// $app->middleware([
//     App\Http\Middleware\ExampleMiddleware::class
// ]);

// $app->routeMiddleware([
//     'auth' => App\Http\Middleware\Authenticate::class,
// ]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/
$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\ModelObserverServiceProvider::class);

$app->register(Maatwebsite\Excel\ExcelServiceProvider::class);
$app->register(Yajra\DataTables\DataTablesServiceProvider::class);
$app->register(Skyhwk\Repository\RepositoryServiceProvider::class);

$app->register(SimpleSoftwareIO\QrCode\QrCodeServiceProvider::class);
$app->register(Telegram\Bot\Laravel\TelegramServiceProvider::class);

$app->register(Illuminate\Cache\CacheServiceProvider::class);
$app->register(Illuminate\Redis\RedisServiceProvider::class);

// Register facades
$app->withFacades(true, [
    Illuminate\Support\Facades\Cache::class => 'Cache'
]);

if (!class_exists('DataTables')) {
    class_alias('Yajra\DataTables\Facades\DataTables', 'DataTables');
}

if (!class_exists('Repository')) {
    class_alias(Skyhwk\Repository\RepositoryFacade::class, 'Repository');
}

if (!class_exists('Telegram')) {
    class_alias('Telegram\Bot\Laravel\Facades\Telegram', 'Telegram');
}
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__ . '/../routes/web.php';
});

return $app;
