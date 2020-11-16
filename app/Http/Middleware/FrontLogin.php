<?php

namespace App\Http\Middleware;

use Closure;

class frontLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(empty(session()->has('frontSession'))){
            return redirect('/login-register');
        }
        return $next($request);
    }
}
