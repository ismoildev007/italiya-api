<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            ['name' => 'Standart', 'monthly_price' => 1500000, 'description' => 'Asosiy o\'quv dasturi, tushlik kiritilmagan.'],
            ['name' => 'Standart + tushlik', 'monthly_price' => 1850000, 'description' => 'Asosiy dastur va kunlik issiq ovqat.'],
            ['name' => 'To\'liq kun', 'monthly_price' => 2400000, 'description' => 'Darslar, tushlik va kunduzgi guruh (18:00 gacha).'],
            ['name' => 'Ixtisoslashtirilgan (aniq fanlar)', 'monthly_price' => 2900000, 'description' => 'Matematika/fizika yo\'nalishi bo\'yicha qo\'shimcha mashg\'ulotlar.'],
            ['name' => 'Imtiyozli', 'monthly_price' => 900000, 'description' => 'Ko\'p bolali va kam ta\'minlangan oilalar uchun.'],
        ];

        foreach ($tariffs as $tariff) {
            Tariff::updateOrCreate(['name' => $tariff['name']], $tariff + ['is_active' => true]);
        }

        $this->command?->info('✅ '.count($tariffs).' ta tarif yaratildi.');
    }
}
