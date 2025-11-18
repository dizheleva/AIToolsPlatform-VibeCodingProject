<?php

/**
 * Скрипт за актуализация на паролите на всички потребители
 * Това е необходимо ако паролите са били двойно хеширани
 * 
 * Използване:
 * docker compose exec php_fpm php reset-user-passwords.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🔧 Актуализация на паролите на тестовите потребители...\n\n";

// Тестови потребители
$testUsers = [
    ['email' => 'ivan@admin.local', 'password' => 'password', 'status' => 'approved'],
    ['email' => 'admin@admin.local', 'password' => 'admin123', 'status' => 'approved'],
    ['email' => 'elena@frontend.local', 'password' => 'password', 'status' => 'approved'],
];

$updated = 0;

foreach ($testUsers as $userData) {
    $user = User::where('email', $userData['email'])->first();
    
    if ($user) {
        // Актуализираме паролата - моделът ще я хешира автоматично
        $user->password = $userData['password']; // Setter ще хешира автоматично
        $user->status = $userData['status'];
        $user->save();
        
        // Проверка дали паролата работи
        $user->refresh();
        $check = Hash::check($userData['password'], $user->password);
        
        echo ($check ? "✅" : "❌") . " {$userData['email']} - Password: " . ($check ? "OK" : "FAILED") . "\n";
        $updated++;
    } else {
        echo "⚠️  Потребителят не съществува: {$userData['email']}\n";
    }
}

echo "\n✅ Актуализирани: {$updated} потребителя\n";
echo "\n📋 Тестови потребители за вход:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($testUsers as $userData) {
    echo "Email: {$userData['email']}\n";
    echo "Password: {$userData['password']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
}

echo "\n✅ Готово! Сега можеш да се логнеш с тези данни.\n";

