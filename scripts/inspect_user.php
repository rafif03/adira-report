<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? 'ppp@ppp.com';
$user = App\Models\User::withTrashed()->where('email', $email)->first();
if (! $user) {
    echo "NOT FOUND\n";
    exit(0);
}
echo json_encode($user->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
