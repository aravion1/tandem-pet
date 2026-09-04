<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_hash_is_unique(): void
    {
        $phoneHash = str_repeat('a', 64);

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'phone_ciphertext' => 'encrypted-phone',
            'phone_hash' => $phoneHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'phone_ciphertext' => 'another-encrypted-phone',
            'phone_hash' => $phoneHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_street_and_house_identify_one_plot(): void
    {
        $streetId = (string) Str::uuid();

        DB::table('streets')->insert([
            'id' => $streetId,
            'name' => 'Лесная',
        ]);

        DB::table('plots')->insert([
            'id' => (string) Str::uuid(),
            'street_id' => $streetId,
            'house' => '10',
        ]);

        $this->expectException(QueryException::class);

        DB::table('plots')->insert([
            'id' => (string) Str::uuid(),
            'street_id' => $streetId,
            'house' => '10',
        ]);
    }

    public function test_chair_role_has_roles_manage_permission(): void
    {
        $chairRoleId = DB::table('roles')
            ->where('name', 'Председатель правления')
            ->value('id');

        $this->assertNotNull($chairRoleId);
        $this->assertDatabaseHas('permissions', ['code' => 'roles.manage']);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $chairRoleId,
            'permission_code' => 'roles.manage',
        ]);
    }

    public function test_application_database_role_cannot_update_audit_log(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Проверка привилегий роли выполняется только в PostgreSQL.');
        }

        $auditLogId = (string) Str::uuid();

        DB::statement('SET ROLE app_user');

        try {
            DB::table('audit_logs')->insert([
                'id' => $auditLogId,
                'action' => 'test.audit_log.created',
                'entity_type' => 'test',
                'payload' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            foreach ([
                fn (): int => DB::table('audit_logs')
                    ->where('id', $auditLogId)
                    ->update(['action' => 'test.audit_log.updated']),
                fn (): int => DB::table('audit_logs')
                    ->where('id', $auditLogId)
                    ->delete(),
            ] as $index => $operation) {
                $savepoint = "audit_privilege_{$index}";
                DB::statement("SAVEPOINT {$savepoint}");

                try {
                    $operation();
                    $this->fail('Прикладная роль не должна изменять журнал аудита.');
                } catch (QueryException) {
                    DB::statement("ROLLBACK TO SAVEPOINT {$savepoint}");
                    $this->addToAssertionCount(1);
                } finally {
                    DB::statement("RELEASE SAVEPOINT {$savepoint}");
                }
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function test_sanctum_token_owner_uses_uuid_on_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Проверка типа Sanctum выполняется только в PostgreSQL.');
        }

        $type = DB::table('information_schema.columns')
            ->where('table_name', 'personal_access_tokens')
            ->where('column_name', 'tokenable_id')
            ->value('data_type');

        $this->assertSame('uuid', $type);
    }
}
