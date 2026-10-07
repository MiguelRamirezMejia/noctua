<?php

namespace App\Actions\Demo;

use App\Actions\Imposition\StoredImposition;
use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Fonts\FontLibrary;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra una cuenta de prueba con todo lo suyo: libros y sus archivos, fuentes
 * subidas, imposiciones y su espacio. Nunca toca cuentas normales.
 */
final readonly class PurgeDemoAccount
{
    public function __construct(
        private FontLibrary $fonts,
        private ActivityLog $activity,
    ) {}

    public function handle(User $user): void
    {
        if (! $user->isDemo()) {
            return;
        }

        $disk = Storage::disk('local');

        DB::transaction(function () use ($user, $disk): void {
            foreach (Book::query()->where('user_id', $user->id)->get() as $book) {
                $disk->deleteDirectory($book->directory());
                $book->delete();
            }

            // Sus espacios: solo los que no comparte con nadie (una cuenta de
            // prueba no puede crear editoriales ni invitar, pero por si acaso).
            foreach ($user->publishers()->withCount('members')->get() as $publisher) {
                if ($publisher->members_count > 1) {
                    continue;
                }

                $disk->deleteDirectory($this->fonts->publisherDirectory($publisher));
                $disk->deleteDirectory(dirname(StoredImposition::directoryFor($publisher, 'x')));
                $publisher->delete();
            }

            $user->delete();
        });
    }

    /**
     * Borra las cuentas de prueba caducadas. Devuelve cuántas.
     */
    public function expired(): int
    {
        $count = 0;

        foreach (User::query()->whereNotNull('demo_expires_at')->where('demo_expires_at', '<=', now())->get() as $user) {
            $this->activity->record(ActivityType::DemoExpired, 'Su cuenta de prueba caducó y se borró con todos sus libros.', user: $user);
            $this->handle($user);
            $count++;
        }

        return $count;
    }
}
