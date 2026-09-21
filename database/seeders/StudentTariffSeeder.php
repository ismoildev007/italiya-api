<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Tariff;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Har bir o'quvchiga tarif biriktiriladi.
 * Bir qismiga individual chegirma qo'yiladi (faqat shu o'quvchiga ta'sir qiladi).
 */
class StudentTariffSeeder extends Seeder
{
    /** Chegirma sabablari — hayotiy holatlar */
    private const REASONS = [
        'Kam ta\'minlangan oila',
        'Ko\'p bolali oila (4+ farzand)',
        'Bir maktabda ikkinchi farzand',
        'Fan olimpiadasi g\'olibi',
        'Xodim farzandi',
        'Boquvchisini yo\'qotgan',
    ];

    public function run(FinanceService $finance): void
    {
        mt_srand(4242);

        $tariffs = Tariff::orderBy('id')->get()->keyBy('name');
        $standart = $tariffs['Standart'];
        $lunch = $tariffs['Standart + tushlik'];
        $fullDay = $tariffs['To\'liq kun'];
        $special = $tariffs['Ixtisoslashtirilgan (aniq fanlar)'];
        $benefit = $tariffs['Imtiyozli'];

        $start = now()->startOfYear();
        $discounted = 0;

        DB::transaction(function () use ($finance, $standart, $lunch, $fullDay, $special, $benefit, $start, &$discounted) {
            foreach (Student::orderBy('id')->get() as $index => $student) {
                $tariff = match ($index % 10) {
                    0, 1, 2, 3 => $standart,
                    4, 5, 6 => $lunch,
                    7, 8 => $fullDay,
                    default => $index % 20 === 9 ? $benefit : $special,
                };

                $finance->assignTariff($student, $tariff, $start);

                // ~18% o'quvchiga individual chegirma
                if ($index % 11 === 0 || $index % 17 === 0) {
                    $isPercent = $index % 2 === 0;
                    $reason = self::REASONS[$index % count(self::REASONS)];

                    $finance->setDiscount(
                        $student,
                        $isPercent ? StudentDiscount::TYPE_PERCENT : StudentDiscount::TYPE_AMOUNT,
                        $isPercent ? [10, 15, 20, 30, 50][$index % 5] : [200000, 300000, 500000][$index % 3],
                        $reason,
                        $start,
                    );

                    $discounted++;
                }
            }
        });

        $total = Student::count();
        $this->command?->info("✅ {$total} ta o'quvchiga tarif biriktirildi, {$discounted} tasiga individual chegirma qo'yildi.");
    }
}
