<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Exception;

class BaseMobileController extends Controller
{
    public function handle(Request $request)
    {
        
        $slice = json_decode($request->header('X-Slice'), true);
        // return response()->json($slice);        
        
        if (!$slice || $slice==null) {
            return response()->json(['message' => 'Invalid request format'], 400);
        }

        if (is_array($slice)) {
            $controller = isset($slice['controller']) ? $slice['controller'] : null;
            $method = isset($slice['function']) ? $slice['function'] : null;
        } elseif (is_object($slice)) {
            $controller = isset($slice->controller) ? $slice->controller : null;
            $method = isset($slice->function) ? $slice->function : null;
        } else {
            return response()->json(['message' => 'Invalid slice format'], 400);
        }

        if (empty($controller) || empty($method)) {
            return response()->json(['message' => 'Controller or method not specified'], 400);
        }

        $controllerName = ucfirst($controller);
        $controllerClass = "App\\Http\\Controllers\\mobile\\" . ucfirst($controllerName);

        if (!class_exists($controllerClass)) {
            return response()->json(['message' => 'Controller not found'], 404);
        }

        $controller = app($controllerClass);

        if (!method_exists($controller, $method)) {
            return response()->json(['message' => 'Method not found'], 404);
        }

        try {
            $parameters = $request->all();
            
            return app()->call([$controller, $method], $parameters);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
