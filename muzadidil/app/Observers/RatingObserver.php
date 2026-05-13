<?php

namespace App\Observers;

use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingObserver
{
    public function created(Rating $rating): void
    {
        $this->refreshUserAggregate($rating->ratee_id);
    }

    public function updated(Rating $rating): void
    {
        $this->refreshUserAggregate($rating->ratee_id);
    }

    public function deleted(Rating $rating): void
    {
        $this->refreshUserAggregate($rating->ratee_id);
    }

    private function refreshUserAggregate(int $userId): void
    {
        $stats = DB::table('ratings')
            ->where('ratee_id', $userId)
            ->selectRaw('AVG(stars) AS avg_stars, COUNT(*) AS total')
            ->first();

        User::where('id', $userId)->update([
            'average_rating' => $stats?->avg_stars ? round((float) $stats->avg_stars, 2) : null,
            'rating_count' => (int) ($stats?->total ?? 0),
        ]);
    }
}
