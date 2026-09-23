<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationApiLog extends Model
{
    protected $table = 'integration_api_logs';

    protected $fillable = [
        'direction',
        'channel',
        'method',
        'url',
        'response_code',
        'request_summary',
        'ip',
    ];
}
