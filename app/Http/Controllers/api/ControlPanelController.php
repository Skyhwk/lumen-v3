<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ControlPanelController extends Controller
{
    // List service yang diizinkan supaya tidak bisa sembarang eksekusi
    private $allowedServices = ['apache2', 'supervisor', 'node-mqtt', 'mqtt-notification', 'mysql'];

    public function serviceStatus(Request $request)
    {
        $service = $request->service;
        if (!in_array($service, $this->allowedServices)) {
            return response()->json(['success' => false, 'message' => 'Service not allowed'], 403);
        }

        $safeService = escapeshellarg($service);
        $status = trim(shell_exec("sudo systemctl status $safeService 2>&1"));

        return response()->json([
            'success' => true,
            'service' => $service,
            'status'  => $status
        ]);
    }

    public function serviceAction(Request $request)
    {
        $service = $request->input('service');
        $action = $request->input('action'); // start | stop | restart

        if (!in_array($service, $this->allowedServices)) {
            return response()->json(['success' => false, 'message' => 'Service not allowed'], 403);
        }

        if (!in_array($action, ['start', 'stop', 'restart'])) {
            return response()->json(['success' => false, 'message' => 'Invalid action'], 400);
        }

        $safeService = escapeshellarg($service);
        $safeAction  = escapeshellarg($action);

        $output = [];
        $returnCode = 0;

        exec("sudo systemctl $safeAction $safeService 2>&1", $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'service' => $service,
            'action'  => $action,
            'output'  => $output
        ]);
    }
}
