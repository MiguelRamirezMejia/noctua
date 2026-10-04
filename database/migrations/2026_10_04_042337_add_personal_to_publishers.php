<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Espacios personales: cada cuenta tiene uno propio, además de las editoriales
 * (espacios compartidos) a las que pertenezca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishers', function (Blueprint $table) {
            $table->boolean('personal')->default(false)->after('slug');
        });

        // Los espacios creados automáticamente al introducir las editoriales eran personales.
        DB::table('publishers')->where('name', 'like', 'Editorial de %')->update(['personal' => true, 'name' => 'Personal']);
    }

    public function down(): void
    {
        Schema::table('publishers', function (Blueprint $table) {
            $table->dropColumn('personal');
        });
    }
};
