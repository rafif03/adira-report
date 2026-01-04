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

echo "USER:\n";
echo json_encode($user->toArray(), JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

$uid = $user->id;

$carReports = App\Models\CarReport::where('submitted_by', $uid)->orWhere('user_id', $uid)->get();
$motorReports = App\Models\MotorReport::where('submitted_by', $uid)->orWhere('user_id', $uid)->get();

echo "Car reports count: " . $carReports->count() . "\n";
if ($carReports->count()) echo json_encode($carReports->map->toArray(), JSON_PRETTY_PRINT) . "\n\n";

echo "Motor reports count: " . $motorReports->count() . "\n";
if ($motorReports->count()) echo json_encode($motorReports->map->toArray(), JSON_PRETTY_PRINT) . "\n\n";

$carTargets = App\Models\MonthlyCarTarget::where('user_id', $uid)->get();
$motorTargets = App\Models\MonthlyMotorTarget::where('user_id', $uid)->get();

echo "Monthly car targets count: " . $carTargets->count() . "\n";
if ($carTargets->count()) echo json_encode($carTargets->map->toArray(), JSON_PRETTY_PRINT) . "\n\n";

echo "Monthly motor targets count: " . $motorTargets->count() . "\n";
if ($motorTargets->count()) echo json_encode($motorTargets->map->toArray(), JSON_PRETTY_PRINT) . "\n\n";

// Check whether updateRole() exists and show file snippet
$usersManagerPath = __DIR__ . '/../app/Livewire/UsersManager.php';
if (file_exists($usersManagerPath)) {
    echo "\nUsersManager.php (preview):\n";
    $contents = file($usersManagerPath);
    $start = 1; $end = min(count($contents), 400);
    for ($i = $start; $i <= $end; $i++) echo $contents[$i-1];
}
