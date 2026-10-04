<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Multi-editorial: libros, fuentes e imposiciones pasan a pertenecer a una
 * editorial, y los usuarios son miembros de una o varias editoriales.
 * Los datos existentes se asignan a una editorial nueva por cada usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('publisher_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['publisher_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_publisher_id')->nullable()->after('remember_token')->constrained('publishers')->nullOnDelete();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('publisher_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('fonts', function (Blueprint $table) {
            $table->foreignId('publisher_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Una editorial por usuario existente, con él como propietario.
        $publishers = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
            $userId = is_numeric($user->id) ? (int) $user->id : 0;
            $userName = is_string($user->name) ? $user->name : 'usuario';
            $now = now();
            $publisherId = DB::table('publishers')->insertGetId([
                'name' => "Editorial de {$userName}",
                'slug' => Str::slug("editorial-{$userName}-{$userId}"),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('publisher_user')->insert([
                'publisher_id' => $publisherId,
                'user_id' => $userId,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')->where('id', $userId)->update(['current_publisher_id' => $publisherId]);
            $publishers[$userId] = $publisherId;
        }

        foreach ($publishers as $userId => $publisherId) {
            DB::table('books')->where('user_id', $userId)->update(['publisher_id' => $publisherId]);
            DB::table('fonts')->where('user_id', $userId)->update(['publisher_id' => $publisherId]);
            $this->moveFiles($userId, $publisherId);
        }

        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('publisher_id')->nullable(false)->change();
        });

        // Las fuentes propias pasan a ser de la editorial (sin usuario = catálogo).
        Schema::table('fonts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'family', 'weight', 'italic']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['publisher_id', 'family', 'weight', 'italic']);
        });
    }

    /**
     * Imposiciones y fuentes subidas pasan de la carpeta del usuario a la de la editorial.
     */
    private function moveFiles(int $userId, int $publisherId): void
    {
        $disk = Storage::disk('local');

        foreach ([
            "imposiciones/{$userId}" => "imposiciones/editorial-{$publisherId}",
            "fonts/usuarios/{$userId}" => "fonts/editoriales/{$publisherId}",
        ] as $from => $to) {
            if (! $disk->exists($from)) {
                continue;
            }

            foreach ($disk->allFiles($from) as $file) {
                $disk->move($file, $to.substr($file, strlen($from)));
            }
            $disk->deleteDirectory($from);
        }

        $fonts = DB::table('fonts')->where('publisher_id', $publisherId)->get(['id', 'path']);
        foreach ($fonts as $font) {
            DB::table('fonts')->where('id', $font->id)->update([
                'path' => str_replace("fonts/usuarios/{$userId}/", "fonts/editoriales/{$publisherId}/", is_string($font->path) ? $font->path : ''),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fonts', function (Blueprint $table) {
            $table->dropUnique(['publisher_id', 'family', 'weight', 'italic']);
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Las fuentes propias vuelven al propietario de su editorial.
        foreach (DB::table('publisher_user')->where('role', 'owner')->get(['publisher_id', 'user_id']) as $owner) {
            DB::table('fonts')->where('publisher_id', $owner->publisher_id)->update(['user_id' => $owner->user_id]);
        }

        Schema::table('fonts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publisher_id');
            $table->unique(['user_id', 'family', 'weight', 'italic']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publisher_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_publisher_id');
        });

        Schema::dropIfExists('publisher_user');
        Schema::dropIfExists('publishers');
    }
};
