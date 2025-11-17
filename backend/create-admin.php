<?php

/**
 * Скрипт за създаване/актуализация на админ потребител
 * 
 * Използване:
 * docker compose exec php_fpm php create-admin.php
 * или
 * php create-admin.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🔧 Създаване/актуализация на админ потребител...\n\n";

// Админ данни
$adminData = [
    'name' => 'Админ Потребител',
    'email' => 'admin@admin.local',
    'password' => 'admin123',
    'role' => 'owner',
    'status' => 'approved',
];

// Проверка дали потребителят вече съществува
$user = User::where('email', $adminData['email'])->first();

if ($user) {
    echo "✅ Потребителят вече съществува. Актуализиране...\n";
    $user->update([
        'name' => $adminData['name'],
        'password' => Hash::make($adminData['password']),
        'role' => $adminData['role'],
        'status' => $adminData['status'],
        'email_verified_at' => now(),
    ]);
    echo "✅ Потребителят беше актуализиран!\n\n";
} else {
    echo "➕ Създаване на нов админ потребител...\n";
    $user = User::create([
        'name' => $adminData['name'],
        'email' => $adminData['email'],
        'password' => Hash::make($adminData['password']),
        'role' => $adminData['role'],
        'status' => $adminData['status'],
        'email_verified_at' => now(),
    ]);
    echo "✅ Потребителят беше създаден!\n\n";
}

echo "📋 Данни за вход:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Email: {$adminData['email']}\n";
echo "Password: {$adminData['password']}\n";
echo "Role: {$adminData['role']}\n";
echo "Status: {$adminData['status']}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Също така актуализираме и оригиналния админ акаунт от seeder
$originalAdmin = User::where('email', 'ivan@admin.local')->first();
if ($originalAdmin) {
    $originalAdmin->update([
        'password' => Hash::make('password'),
        'status' => 'approved',
        'role' => 'owner',
    ]);
    echo "✅ Оригиналният админ акаунт (ivan@admin.local) също беше актуализиран!\n";
    echo "   Email: ivan@admin.local\n";
    echo "   Password: password\n\n";
}

echo "✅ Готово! Можеш да се логнеш с тези данни.\n";

