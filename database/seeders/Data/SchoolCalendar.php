<?php

namespace Database\Seeders\Data;

use Illuminate\Support\Carbon;

class SchoolCalendar
{
    /**
     * O'quv kunlari (dushanba–shanba), berilgan oraliqda.
     *
     * @return array<int, Carbon>
     */
    public static function schoolDays(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            if (! $cursor->isSunday()) {
                $days[] = $cursor->copy();
            }

            $cursor->addDay();
        }

        return $days;
    }
}
