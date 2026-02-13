<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->post('/api/gettoken', 'AuthController@gettoken');
$router->post('/api/cektoken', 'AuthController@checkToken');

$router->group(['prefix' => 'api', 'middleware' => ['auth.token', 'log.request', 'decrypt.slice']], function () use ($router) {
    $router->post('route', 'BaseController@handle');
});

$router->group(['prefix' => 'api', 'middleware' => ['auth.token', 'log.request', 'decrypt.slice']], function () use ($router) {
    $router->post('mobile', 'BaseMobileController@handle');
});

$router->post('/api/cekLHP', 'external\LHPHandleController@cekLHP');
$router->post('/api/newCheckLhp', 'external\LHPHandleController@newCheckLhp');

$router->get('/api/getJadwal', 'external\JadwalHandler@getJadwal');
$router->get('/api/showDetailJadwal', 'external\JadwalHandler@getDetailJadwalApi');

$router->get('/api/absen', 'external\MesinAbsenHandler@index');
$router->get('/api/device_intilab', 'external\MesinAbsenHandler@Sync');
$router->post('/api/multi-device', 'external\MesinAbsenHandler@handleMultiDevice');
$router->get('/api/summaryParameter', 'external\SummaryParameterHandler@index');

$router->get('/api/iot-intilab', 'external\MesinAbsenHandler@IotSync');

//custom untuk testing di produksi
$router->post('/api/custom', 'external\CustomController@handle');
$router->get('/api/total', 'external\CustomController@total');

$router->get('/api/get-lhp-pak-eko', 'external\GetLhpApiPakEko@getLHP');

$router->group(['middleware' => ['cors']], function () use ($router) {
    $router->get('/api/setWebhook', 'external\TelegramController@setWebhook');
    $router->get('/api/reloadWebhook', 'external\TelegramController@reloadWebhook');
    $router->post('/api/setWebhook', 'external\TelegramController@commandHandlerWebHook');
    $router->get('/api/validasi-emisi', 'external\ValidatorHandler@handleEmisi');
    $router->post('/api/validasi-document', 'external\ValidatorHandler@handleDocument');
    $router->post('/api/import', 'external\HandleFileInstrument@handleImport');
    $router->post('/api/sensorData', 'external\FlowMeterController@sensorData');
    $router->post('/api/soundMeterData', 'external\SoundMeterController@sensorData');
});

// $router->post('/api/import-lhp-air', 'external\ImportLhp@indexAir');
// $router->post('/api/import-lhp-udara', 'external\ImportLhp@indexUdara');
// $router->post('/api/import-lhp-kebisingan', 'external\ImportLhp@indexKebisingan');
// $router->post('/api/import-lhp-emisi', 'external\ImportLhp@indexEmisi');

$router->post('/api/import-lhp-udara-ambient', 'external\ImportHasilPengujian@importLhpUdaraAmbient');
$router->post('/api/import-lhp-udara-lingkungan-kerja', 'external\ImportHasilPengujian@importLhpUdaraLingkunganKerja');
$router->post('/api/import-lhp-emisi-tidak-bergerak', 'external\ImportHasilPengujian@importLhpEmisiTidakBergerak');

$router->group(['prefix' => 'director'], function () use ($router) {
    $router->post('/login', 'directorApp\AuthController@login');

    $router->group(['middleware' => 'director.auth.token'], function () use ($router) {
        $router->post('/logout', 'directorApp\AuthController@logout');

        $router->get('/recruitment/candidates', 'directorApp\RecruitmentsController@getCandidates');
        $router->get('/recruitment/candidateFilter', 'directorApp\RecruitmentsController@getCandidateFilterParams');
        $router->post('/recruitment/approveCandidate', 'directorApp\RecruitmentsController@approveCandidate');
        $router->post('/recruitment/rejectCandidate', 'directorApp\RecruitmentsController@rejectCandidate');
        $router->get('/recruitment/salaries', 'directorApp\RecruitmentsController@getOfferingSalaries');
        $router->post('/recruitment/approveSalary', 'directorApp\RecruitmentsController@approveOfferingSalary');
        $router->post('/recruitment/rejectSalary', 'directorApp\RecruitmentsController@rejectOfferingSalary');

        $router->get('/sales/dailyQuotes', 'directorApp\SalesController@getRecapDailyQuotations');
        $router->get('/sales/pointOfSales', 'directorApp\SalesController@getPointOfSales');
        $router->get('/sales/salesIn', 'directorApp\SalesController@getSalesIn');
        $router->get('/sales/salesInFilter', 'directorApp\SalesController@getSalesInFilterParams');
        $router->get('/sales/salesInReports', 'directorApp\SalesController@getSalesInReports');

        $router->get('/employees/employees', 'directorApp\EmployeesController@getEmployees');
        $router->get('/employees/employeeFilter', 'directorApp\EmployeesController@getEmployeeFilterParams');
        $router->get('/employees/accessDoors', 'directorApp\EmployeesController@getAccessDoors');
        $router->get('/employees/accesslogFilter', 'directorApp\EmployeesController@getAccesslogFilterParams');
        $router->get('/employees/gpsView', 'directorApp\EmployeesController@getGpsView');

        $router->get('/profile/me', 'directorApp\ProfileController@myProfile');
        $router->post('/profile/update', 'directorApp\ProfileController@updateProfile');
        $router->post('/profile/updatePassword', 'directorApp\ProfileController@updatePassword');

        $router->get('/dashboard/perMenuCount', 'directorApp\DashboardController@perMenuCount');
    });
});




$router->post('/{any:.*}', ['uses' => 'R404Controller@r404']);
$router->get('/{any:.*}', ['uses' => 'R404Controller@r404']);
