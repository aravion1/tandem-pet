<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::table('personal_access_tokens')->exists()) {
            throw new LogicException('Перед миграцией Sanctum tokenable_id создайте новые токены после резервного копирования.');
        }

        DB::statement('DROP INDEX personal_access_tokens_tokenable_type_tokenable_id_index');
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING tokenable_id::text::uuid');
        DB::statement('CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens (tokenable_type, tokenable_id)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::table('personal_access_tokens')->exists()) {
            throw new LogicException('Откат Sanctum tokenable_id с существующими UUID-токенами небезопасен.');
        }

        DB::statement('DROP INDEX personal_access_tokens_tokenable_type_tokenable_id_index');
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE bigint USING tokenable_id::text::bigint');
        DB::statement('CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens (tokenable_type, tokenable_id)');
    }
};
