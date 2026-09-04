<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('streets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255)->unique();
        });

        Schema::create('plots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('street_id')->constrained()->restrictOnDelete();
            $table->string('house', 64);
            $table->unique(['street_id', 'house']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('code', 100)->primary();
        });

        Schema::create('user_plots', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plot_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'plot_id']);
            $table->index('plot_id');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission_code', 100);
            $table->primary(['role_id', 'permission_code']);
            $table->foreign('permission_code')->references('code')->on('permissions')->cascadeOnDelete();
            $table->index('permission_code');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->uuid('entity_id')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('created_at');
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id', 'created_at']);
        });

        $this->seedRolesAndPermissions();
        $this->protectAuditLog();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_plots');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('plots');
        Schema::dropIfExists('streets');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('REVOKE ALL PRIVILEGES ON SCHEMA public FROM app_user');
            DB::unprepared('DROP ROLE IF EXISTS app_user');
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $now = now();
        $roles = [
            'Житель',
            'Правление',
            'Бухгалтер',
            'Председатель правления',
            'Заместитель председателя правления',
        ];

        foreach ($roles as $name) {
            DB::table('roles')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('permissions')->insertOrIgnore(array_map(
            fn (string $code): array => ['code' => $code],
            [
                'users.manage',
                'requests.view_public',
                'requests.view_private',
                'requests.moderate',
                'tasks.view',
                'tasks.manage',
                'tasks.assign',
                'tasks.close',
                'discussions.moderate',
                'news.manage',
                'infoboards.manage',
                'analytics.view',
                'analytics.export',
                'budget.view',
                'budget.manage',
                'audit.view',
                'roles.manage',
            ],
        ));

        $chairRoleId = DB::table('roles')
            ->where('name', 'Председатель правления')
            ->value('id');

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $chairRoleId,
            'permission_code' => 'roles.manage',
        ]);
    }

    private function protectAuditLog(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_user') THEN
                    CREATE ROLE app_user NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
                END IF;
            END
            $$;
        SQL);
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT ON TABLE audit_logs TO app_user');
        DB::statement('REVOKE UPDATE, DELETE ON TABLE audit_logs FROM app_user');
    }
};
