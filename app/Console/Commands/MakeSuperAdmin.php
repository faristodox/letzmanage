<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin {email} {--name=} {--password=}';

    protected $description = 'Promote an existing user to platform super-admin, or create one';

    public function handle(): int
    {
        $email = $this->argument('email');

        // Look across all organizations (and none) — super-admins have no org.
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if ($user) {
            $user->is_super_admin = true;
            $user->save();
            $this->info("Promoted {$email} to super-admin.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->option('password') ?: $this->secret('Password');

        if (! $password) {
            $this->error('A password is required to create a new super-admin.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => UserStatus::Active,
            'is_super_admin' => true,
            'organization_id' => null,
        ]);

        $this->info("Created super-admin {$email}.");

        return self::SUCCESS;
    }
}
