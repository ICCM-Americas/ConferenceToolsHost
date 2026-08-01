<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the users table. */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Roles and account state.
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('must_change_password')->default(false);

            // Multi-factor authentication (TOTP) fields.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // NOTE: This application uses the "file" session driver (see config/session.php
        // and the SESSION_DRIVER env value) so that the framework does not require a
        // database "sessions" table. The scheduler package keeps its own domain tables
        // safely namespaced under the "bof_" prefix (e.g. bof_sessions) regardless.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
