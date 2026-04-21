<?php

namespace Modules\Watchlist\Actions;

use App\Models\LegalDocument;
use App\Models\User;
use App\Models\Watchlist;

class ToggleWatchlistAction
{
    public function execute(User $user, LegalDocument $document): bool
    {
        $existing = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        Watchlist::create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
        ]);

        return true;
    }
}
