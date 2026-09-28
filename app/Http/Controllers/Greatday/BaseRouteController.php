<?php

namespace App\Http\Controllers\Greatday;

use Exception;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class BaseRouteController extends Controller
{
    public function handle(Request $request)
    {
        $slice = json_decode($request->header('X-Slice'), true);

        if (!$slice) {
            return response()->json(['message' => 'Invalid request format'], 400);
        }

        $controller = is_array($slice)
            ? ($slice['controller'] ?? null)
            : ($slice->controller ?? null);
        $method = is_array($slice)
            ? ($slice['function'] ?? null)
            : ($slice->function ?? null);

        if (empty($controller) || empty($method)) {
            return response()->json(['message' => 'Controller or method not specified'], 400);
        }

        $controllerClass = 'App\\Http\\Controllers\\Greatday\\' . ucfirst($controller);

        if (!class_exists($controllerClass)) {
            return response()->json(['message' => 'Controller not found'], 404);
        }

        $instance = app($controllerClass);

        if (!method_exists($instance, $method)) {
            return response()->json(['message' => 'Method not found'], 404);
        }

        try {
            return app()->call([$instance, $method], ['request' => $request]);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
