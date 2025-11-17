<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin 
                            {--email=admin@admin.local : Admin email}
                            {--password=admin123 : Admin password}
                            {--name=Админ Потребител : Admin name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update admin user (owner role)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        $this->info('🔧 Създаване/актуализация на админ потребител...');
        $this->newLine();

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->warn("⚠️  Потребителят вече съществува. Актуализиране...");
            $user->update([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'owner',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]);
            $this->info('✅ Потребителят беше актуализиран!');
        } else {
            $this->info('➕ Създаване на нов админ потребител...');
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'owner',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]);
            $this->info('✅ Потребителят беше създаден!');
        }

        $this->newLine();
        $this->info('📋 Данни за вход:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Email: {$email}");
        $this->line("Password: {$password}");
        $this->line("Role: owner");
        $this->line("Status: approved");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Also update the original admin from seeder
        $originalAdmin = User::where('email', 'ivan@admin.local')->first();
        if ($originalAdmin) {
            $originalAdmin->update([
                'password' => Hash::make('password'),
                'status' => 'approved',
                'role' => 'owner',
            ]);
            $this->info('✅ Оригиналният админ акаунт (ivan@admin.local) също беше актуализиран!');
            $this->line('   Email: ivan@admin.local');
            $this->line('   Password: password');
            $this->newLine();
        }

        $this->info('✅ Готово! Можеш да се логнеш с тези данни.');

        return Command::SUCCESS;
    }
}

