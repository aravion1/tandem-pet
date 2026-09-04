<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('phone_ciphertext');
            $table->char('phone_hash', 64)->unique();
            $table->text('password_hash')->nullable();
            $table->timestampTz('consented_at')->nullable();
            $table->string('consent_version', 64)->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
