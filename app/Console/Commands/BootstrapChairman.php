<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\ResidentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapChairman extends Command
{
    protected $signature = 'users:bootstrap-chairman {phone} {street} {house}';

    protected $description = 'Создаёт единственную стартовую учётную запись председателя.';

    public function handle(ResidentService $residents, AuditLogger $audit): int
    {
        if (DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.name', 'Председатель правления')
            ->exists()) {
            $this->error('Председатель уже создан.');

            return self::FAILURE;
        }

        $user = $residents->import([
            'phone' => $this->argument('phone'),
            'street' => $this->argument('street'),
            'house' => $this->argument('house'),
        ], null);
        $chairRoleId = DB::table('roles')->where('name', 'Председатель правления')->value('id');
        DB::table('user_roles')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $chairRoleId]);
        $audit->record(null, 'user.bootstrap_chairman', 'user', $user->id, ['source' => 'console']);
        $this->info('Учётная запись председателя импортирована. Завершите её активацию через API.');

        return self::SUCCESS;
    }
}
