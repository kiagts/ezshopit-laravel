<?php

namespace App\Services\Reviews;

use App\Models\User;

class ReviewerName
{
    public function display(User $user): string
    {
        $parts = preg_split('/\s+/', trim($user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'Customer';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.str($parts[1])->substr(0, 1)->upper().'.';
    }
}
