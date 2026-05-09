<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);
        $locales = array_keys(config('fonts.map', []));

        if ($segment && strlen($segment) === 2 && in_array($segment, $locales, true)) {
            App::setLocale($segment);
            session(['locale' => $segment]);
        }

        return $next($request);
    }
}
