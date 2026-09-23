<?php

if (! function_exists('hub_login_url')) {
    function hub_login_url(): string
    {
        return rtrim((string) config('services.hub.public_url', 'https://lead-control.space'), '/').'/login';
    }
}

if (! function_exists('hub_url')) {
    function hub_url(string $path = '/'): string
    {
        return rtrim((string) config('services.hub.public_url', 'https://lead-control.space'), '/').'/'.ltrim($path, '/');
    }
}
