<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_filename');
            $table->unsignedInteger('source_page_count');
            $table->decimal('source_width_mm', 8, 2);
            $table->decimal('source_height_mm', 8, 2);
            // Secuencia de páginas: [{id, source|null, folio}].
            $table->json('pages');
            // Configuración de folios (FolioSettings).
            $table->json('folios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
