<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de actividad: qué hace cada persona en Noctua. Guarda una copia
     * del nombre de quien actúa y del título del libro, para que el historial
     * se conserve aunque la cuenta (por ejemplo, una de prueba) o el libro se
     * borren.
     */
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60)->index();
            $table->string('category', 20)->index();
            $table->string('description', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor', 120);
            $table->boolean('actor_is_demo')->default(false);
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('book_title')->nullable();
            $table->json('properties')->nullable();
            $table->string('session', 16)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
