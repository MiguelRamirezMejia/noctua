<?php

namespace App\Http\Controllers;

use App\Actions\Imposition\StoredImposition;
use App\Models\Book;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $publisher = $user->publisher();
        $books = Book::query()->whereBelongsTo($publisher)->latest('updated_at')->get();
        $printed = StoredImposition::recent($publisher, 1) !== [];

        return Inertia::render('Dashboard', [
            'recentBooks' => $books->take(6)->map(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'page_count' => count($book->pages),
                'cover_url' => route('books.thumbnail', [$book, $book->sequence()->items[0]->sourcePage ?? 1]),
                'updated_at' => $book->updated_at?->toIso8601String(),
            ])->values()->all(),
            'bookCount' => $books->count(),
            // Primeros pasos: se marcan solos según lo que ya se ha hecho en el espacio.
            'steps' => [
                'book' => $books->isNotEmpty(),
                'edit' => $books->contains(fn (Book $book): bool => $book->folioSettings()->enabled
                    || $book->bookLayout()->hasMargins()
                    || array_filter($book->sequence()->items, fn ($item): bool => ! $item->design->isEmpty()) !== []),
                'print' => $printed,
            ],
        ]);
    }
}
