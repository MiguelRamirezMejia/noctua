<?php

namespace App\Policies;

use App\Enums\PublisherRole;
use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * Los libros son de la editorial: cualquier miembro puede verlos y editarlos.
     */
    public function view(User $user, Book $book): bool
    {
        return $user->belongsToPublisher($book->publisher);
    }

    public function update(User $user, Book $book): bool
    {
        return $user->belongsToPublisher($book->publisher);
    }

    /**
     * Borrar: quien lo creó o un propietario de la editorial.
     */
    public function delete(User $user, Book $book): bool
    {
        return $book->user_id === $user->id || $user->roleIn($book->publisher) === PublisherRole::Owner;
    }
}
