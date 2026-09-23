<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

/**
 * CSRF через meta/form; без JS-readable XSRF-TOKEN cookie (ТЗ FR-PRIV-01).
 */
class VerifyCsrfToken extends Middleware
{
    protected $addHttpCookie = false;

    public function shouldAddXsrfTokenCookie()
    {
        return false;
    }
}
