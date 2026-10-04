<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{

    protected $signature = 'app:create-admin {--password=} {--force : Sobrescribe password si el usuario ya existe}';

    protected $description = 'Crea (o actualiza) el usuario admin con contraseña desde env o opción --password';

    public function handle(): int
    {
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');

        if (empty($password)) {
            $this->error('Debe proporcionar contraseña via --password o variable ADMIN_PASSWORD en .env');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => 'admin@example.com']);

        if ($user->exists && ! $this->option('force')) {
            $this->info('Usuario admin ya existe (id: ' . $user->id . '). Use --force para actualizar contraseña.');
            return self::SUCCESS;
        }

        $user->name = $user->name ?: 'admin';
        $user->password = Hash::make($password);
        $user->save();

        $this->info($user->wasRecentlyCreated ? 'Usuario admin creado exitosamente.' : 'Usuario admin actualizado exitosamente.');

        return self::SUCCESS;
    }
}
