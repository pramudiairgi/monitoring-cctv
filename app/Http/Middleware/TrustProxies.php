<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies;

    protected $headers = [
        Request::HEADER_FORWARDED => 'X-Forwarded-For',
        Request::HEADER_X_FORWARDED_FOR => 'X-Forwarded-For',
        Request::HEADER_X_FORWARDED_HOST => 'X-Forwarded-Host',
        Request::HEADER_X_FORWARDED_PORT => 'X-Forwarded-Port',
        Request::HEADER_X_FORWARDED_PROTO => 'X-Forwarded-Proto',
        Request::HEADER_X_FORWARDED_AWS_ELB => 'X-Amz-Meta-Destination',
    ];

    public function shouldBlock(Request $request): bool
    {
        return false;
    }
}
