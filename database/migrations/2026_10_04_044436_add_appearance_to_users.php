<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // light | dark | system
            $table->string('appearance')->default('system')->after('remember_token');
            // #rrggbb o null para el color de Noctua por defecto.
            $table->string('accent_color', 7)->nullable()->after('appearance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['appearance', 'accent_color']);
        });
    }
};
