<?php

/**
 * Скрипт за актуализация на паролите на тестовите потребители
 * 
 * Използване:
 * docker compose exec php_fpm php reset-test-passwords.php
 * или
 * php reset-test-passwords.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🔧 Актуализация на паролите на тестовите потребители...\n\n";

// Тестови потребители
$testUsers = [
    [
        'email' => 'ivan@admin.local',
        'password' => 'password',
        'name' => 'Иван Иванов',
        'role' => 'owner',
        'status' => 'approved',
    ],
    [
        'email' => 'admin@admin.local',
        'password' => 'admin123',
        'name' => 'Админ Потребител',
        'role' => 'owner',
        'status' => 'approved',
    ],
    [
        'email' => 'elena@frontend.local',
        'password' => 'password',
        'name' => 'Елена Петрова',
        'role' => 'frontend',
        'status' => 'pending',
    ],
    [
        'email' => 'petar@backend.local',
        'password' => 'password',
        'name' => 'Петър Георгиев',
        'role' => 'backend',
        'status' => 'pending',
    ],
];

$updated = 0;
$created = 0;

foreach ($testUsers as $userData) {
    $user = User::where('email', $userData['email'])->first();
    
    if ($user) {
        echo "✅ Актуализиране на: {$userData['email']}\n";
        $user->update([
            'name' => $userData['name'],
            'password' => Hash::make($userData['password']),
            'role' => $userData['role'],
            'status' => $userData['status'],
            'email_verified_at' => now(),
        ]);
        $updated++;
    } else {
        echo "➕ Създаване на: {$userData['email']}\n";
        User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'role' => $userData['role'],
            'status' => $userData['status'],
            'email_verified_at' => now(),
        ]);
        $created++;
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 Тестови потребители:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($testUsers as $userData) {
    echo "Email: {$userData['email']}\n";
    echo "Password: {$userData['password']}\n";
    echo "Role: {$userData['role']}\n";
    echo "Status: {$userData['status']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
}

echo "\n✅ Актуализирани: {$updated} потребителя\n";
echo "➕ Създадени: {$created} потребителя\n";
echo "\n✅ Готово! Сега можеш да се логнеш с тези данни.\n";

// Проверка дали паролите работят
echo "\n🔍 Проверка на паролите...\n";
foreach ($testUsers as $userData) {
    $user = User::where('email', $userData['email'])->first();
    if ($user) {
        $passwordCheck = Hash::check($userData['password'], $user->password);
        echo ($passwordCheck ? "✅" : "❌") . " {$userData['email']}: " . ($passwordCheck ? "OK" : "FAILED") . "\n";
    }
}

