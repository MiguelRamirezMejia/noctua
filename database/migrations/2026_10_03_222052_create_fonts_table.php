<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            // null = fuente del catálogo, disponible para todos.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('family');
            $table->unsignedSmallInteger('weight');
            $table->boolean('italic');
            $table->string('category')->nullable();
            // Nombre en el catálogo (puede diferir del que usa Typst, p. ej. «Shadows Into Light»).
            $table->string('catalog_family')->nullable();
            // Ruta relativa al disco local.
            $table->string('path');
            $table->string('original_name');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->unique(['user_id', 'family', 'weight', 'italic']);
            $table->index('family');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonts');
    }
};
