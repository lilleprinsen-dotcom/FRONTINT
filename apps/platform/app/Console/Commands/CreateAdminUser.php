<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'omnibridge:create-admin
        {--name= : Admin user name}
        {--email= : Admin user email}
        {--password= : Admin user password}
        {--organization=Lilleprinsen : First organization name}
        {--slug=lilleprinsen : First organization slug}';

    protected $description = 'Create the first OmniBridge admin user and organization membership.';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Admin name');
        $email = $this->option('email') ?: $this->ask('Admin email');
        $password = $this->option('password') ?: $this->secret('Admin password');
        $organizationName = $this->option('organization') ?: 'Lilleprinsen';
        $slug = Str::slug($this->option('slug') ?: $organizationName);

        if (! $name || ! $email || ! $password) {
            $this->error('Name, email, and password are required.');

            return self::FAILURE;
        }

        $organization = Organization::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $organizationName,
                'environment' => 'staging',
                'status' => 'active',
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner'],
        ]);

        $this->info("Admin user {$email} is ready for {$organization->name}.");

        return self::SUCCESS;
    }
}
