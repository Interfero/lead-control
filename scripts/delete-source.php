<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 0);
if ($id < 1) {
    fwrite(STDERR, "Usage: php delete-source.php <source_id>\n");
    exit(1);
}

$source = App\Models\Source::query()->find($id);
if (! $source) {
    echo "not found\n";
    exit(0);
}

$controller = app(App\Http\Controllers\Api\PartnerReferenceController::class);
$request = Illuminate\Http\Request::create('/', 'DELETE');
$response = $controller->destroySource($request, $id);
echo $response->getContent().PHP_EOL;
