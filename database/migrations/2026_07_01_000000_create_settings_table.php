<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the settings table. */
return new class extends Migration
{
    public function up(): void
    {
        // Generic key/value settings store owned by the host application (as
        // opposed to the branding package's own "branding_settings" table). It
        // backs the host admin "Site settings" screen. Values are written through
        // the UI, never seeded here — App\Models\Setting falls back to sensible
        // code defaults until an admin saves.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
