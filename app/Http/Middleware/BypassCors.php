<?php

namespace App\Http\Middleware;

class BypassCors {

    public function handle($request, \Closure $next) {
        if ($request->isMethod('OPTIONS')) //Intercept OPTIONS requests
            $response = response('', 200);
        else    // Pass the request to the next middleware
            $response = $next($request);

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, DELETE');

        return $response;
    }

}
