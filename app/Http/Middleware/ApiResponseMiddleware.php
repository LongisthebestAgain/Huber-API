<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiResponseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            
            $wrapped = [
                'status' => $response->status(),
                'success' => $response->status() >= 200 && $response->status() < 300,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ];

            $response->setData($wrapped);
        }

        return $response;
    }
} 