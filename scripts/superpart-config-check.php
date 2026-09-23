<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;
use App\Models\User;

$authorId = (int) config('services.superpart.order_author_user_id');
$defaultSourceId = (int) config('services.superpart.default_source_id');

echo "author_id={$authorId}\n";
echo "default_source_id={$defaultSourceId}\n";
echo 'author_exists='.(User::query()->where('user_id', $authorId)->exists() ? 'yes' : 'no')."\n";
echo 'default_source_exists='.(Source::query()->where('source_id', $defaultSourceId)->where('is_active', true)->exists() ? 'yes' : 'no')."\n";

if ($authorId < 1) {
    $fallbackAuthor = User::query()->orderBy('user_id')->value('user_id');
    echo "suggest_author_id={$fallbackAuthor}\n";
}

if ($defaultSourceId < 1 || ! Source::query()->where('source_id', $defaultSourceId)->where('is_active', true)->exists()) {
    $fallbackSource = Source::query()
        ->where('is_active', true)
        ->where('available_for_superpart', true)
        ->orderBy('source_id')
        ->value('source_id');
    echo "suggest_default_source_id={$fallbackSource}\n";
}

echo "users_sample=";
echo User::query()->orderBy('user_id')->limit(5)->pluck('user_id')->implode(',');
echo "\n";

echo "sources_sample=";
echo Source::query()
    ->where('is_active', true)
    ->where('available_for_superpart', true)
    ->orderBy('source_id')
    ->limit(5)
    ->pluck('source_id')
    ->implode(',');
echo "\n";
