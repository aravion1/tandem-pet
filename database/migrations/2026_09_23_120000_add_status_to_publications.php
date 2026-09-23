<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['news', 'infoboards'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('status', 32)->default('draft')->index();
            });
            DB::table($table)->whereNotNull('published_at')->update(['status' => 'published']);
        }
    }

    public function down(): void
    {
        foreach (['news', 'infoboards'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['status']);
                $blueprint->dropColumn('status');
            });
        }
    }
};
