<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Fixing test users...\n";

$users = [
    ['email' => 'ivan@admin.local', 'password' => 'password', 'role' => 'owner', 'status' => 'approved'],
    ['email' => 'admin@admin.local', 'password' => 'admin123', 'role' => 'owner', 'status' => 'approved'],
    ['email' => 'elena@frontend.local', 'password' => 'password', 'role' => 'frontend', 'status' => 'approved'],
];

foreach ($users as $data) {
    $user = User::firstOrCreate(
        ['email' => $data['email']],
        [
            'name' => explode('@', $data['email'])[0],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'],
            'email_verified_at' => now(),
        ]
    );
    
    if ($user->wasRecentlyCreated) {
        echo "Created: {$data['email']}\n";
    } else {
        $user->update([
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);
        echo "Updated: {$data['email']}\n";
    }
    
    // Verify password
    $check = Hash::check($data['password'], $user->fresh()->password);
    echo "  Password check: " . ($check ? "OK" : "FAILED") . "\n";
}

echo "Done!\n";

