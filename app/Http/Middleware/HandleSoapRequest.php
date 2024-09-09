<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleSoapRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentType = $request->header('Content-Type');
        if (str_contains($contentType, 'text/xml')) {
            $content = $request->getContent();
                    
            $xml = simplexml_load_string($content, null, 0, 'soapenv', true);
            $response = $xml->xpath('//soapenv:Body')[0];
            $request->merge(['payload' => $response]);
        }
        return $next($request);
    }
}
