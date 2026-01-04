<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$users = User::all();
if ($users->isEmpty()) {
    echo "No users found\n";
    exit;
}

foreach ($users as $u) {
    $status = $u->deleted_at ? 'DELETED' : 'ACTIVE';
    echo "{$u->id}\t{$u->email}\t{$status}\n";
}
