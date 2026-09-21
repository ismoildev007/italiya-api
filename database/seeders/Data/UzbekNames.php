<?php

namespace Database\Seeders\Data;

/**
 * Seeder'lar uchun real o'zbekcha ism-familiyalar to'plami.
 * UI'da ma'lumot tabiiy ko'rinishi uchun tasodifiy "lorem" o'rniga ishlatiladi.
 */
class UzbekNames
{
    /** @var array<int, string> */
    public const MALE_FIRST = [
        'Abdulaziz', 'Akmal', 'Alisher', 'Anvar', 'Asadbek', 'Aziz', 'Behruz', 'Bekzod',
        'Bobur', 'Davron', 'Dilshod', 'Diyorbek', 'Doston', 'Eldor', 'Farrux', 'Firdavs',
        'Ibrohim', 'Islom', 'Jahongir', 'Jasur', 'Javohir', 'Kamron', 'Muhammadali',
        'Nodirbek', 'Nurbek', 'Otabek', 'Oybek', 'Ozodbek', 'Rustam', 'Sanjar', 'Sardor',
        'Shahzod', 'Sherzod', 'Shohruh', 'Sirojiddin', 'Temur', 'Ulugbek', 'Umid',
        'Xurshid', 'Zafar',
    ];

    /** @var array<int, string> */
    public const FEMALE_FIRST = [
        'Aziza', 'Charos', 'Dilnoza', 'Diyora', 'Farangiz', 'Feruza', 'Gulbahor', 'Gulnora',
        'Hilola', 'Iroda', 'Kamola', 'Laylo', 'Madina', 'Malika', 'Marjona', 'Mohira',
        'Munisa', 'Muslima', 'Nargiza', 'Nasiba', 'Nilufar', 'Nodira', 'Oysha', 'Ozoda',
        'Rayhona', 'Robiya', 'Sabina', 'Sevara', 'Shahnoza', 'Shahzoda', 'Sitora', 'Umida',
        'Yulduz', 'Zarina', 'Zilola', 'Zuhra',
    ];

    /** Familiya o'zagi — erkak uchun `-ov/-yev`, ayol uchun `-ova/-yeva`. */
    public const SURNAME_STEMS = [
        'Abdullay', 'Aliy', 'Azim', 'Bekmurod', 'Ergash', 'Fayziy', 'Gafur', 'Hasan',
        'Ibragim', 'Ismoil', 'Jalil', 'Karim', 'Xolmat', 'Mahmud', 'Nazar', 'Normat',
        'Olim', 'Qodir', 'Rahim', 'Rashid', 'Said', 'Salim',
        'Toshmat', 'Turgun', 'Umar', 'Usmon', 'Yusup', 'Zokir', 'Sattor', 'Nurmat',
        'Hamdam', 'Bozor', 'Qurbon', 'Xudoyor', 'Sobir', 'Mirzay', 'Tursun', 'Sharip',
    ];

    /** @var array<int, string> Fanlar (O'zbekiston umumta'lim maktabi) */
    /** Til o'quv markazi fanlari (yo'nalishlar bo'yicha) */
    public const SUBJECTS = [
        ['Italyan tili', 'ITA'],
        ['Italyan tili — tibbiyot', 'ITA-MED'],
        ['Italyan tili — agrar', 'ITA-AGR'],
        ['Italyan tili — biznes va viza', 'ITA-SPC'],
        ['Ingliz tili', 'ENG'],
    ];

    public const STREETS = [
        'Bunyodkor', 'Amir Temur', 'Navoiy', 'Chilonzor', 'Yunusobod', 'Mustaqillik',
        'Farobiy', 'Beruniy', 'Shota Rustaveli', 'Qatortol', 'Lutfiy', 'Sebzor',
    ];

    public static function surname(string $stem, bool $female): string
    {
        $suffix = str_ends_with($stem, 'y') ? 'ev' : 'ov';

        return $stem.$suffix.($female ? 'a' : '');
    }

    public static function patronymic(string $fatherName, bool $female): string
    {
        $base = rtrim($fatherName, ' ');

        return $base.($female ? 'ovna' : 'ovich');
    }

    public static function address(int $seed): string
    {
        $street = self::STREETS[$seed % count(self::STREETS)];
        $house = ($seed % 90) + 1;
        $flat = ($seed % 60) + 1;

        return "Toshkent sh., {$street} ko'chasi, {$house}-uy, {$flat}-xonadon";
    }

    public static function phone(int $seed): string
    {
        $codes = ['90', '91', '93', '94', '97', '98', '99', '88', '77'];
        $code = $codes[$seed % count($codes)];
        $n = 1000000 + (($seed * 7919) % 8999999);

        return sprintf('+998%s%s', $code, substr((string) $n, 0, 7));
    }
}
