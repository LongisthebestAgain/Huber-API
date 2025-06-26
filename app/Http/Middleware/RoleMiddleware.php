<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!$request->user() || $request->user()->user_role !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
                'error' => 'You do not have permission to access this resource'
            ], 403);
        }

        return $next($request);
    }
} 