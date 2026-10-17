<?php

use App\Models\User;
use App\Services\DebtReminderService;
use App\Services\FinanceService;
use App\Services\MonthlyReportService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramNotifier;
use Database\Seeders\AcademicYearSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Konsol buyruqlari va rejalashtirilgan vazifalar
|--------------------------------------------------------------------------
| Serverda cron: * * * * * php /path/artisan schedule:run >> /dev/null 2>&1
*/

Artisan::command('notifications:send {--limit=100}', function (TelegramNotifier $notifier) {
    $sent = $notifier->dispatchPending((int) $this->option('limit'));
    $this->info("Telegram: {$sent} ta xabar yuborildi.");
})->purpose('Navbatdagi bildirishnomalarni Telegram orqali yuborish');

Artisan::command('finance:debt-reminders {--min=0}', function (DebtReminderService $reminders) {
    $count = $reminders->remindAll((float) $this->option('min') ?: null);
    $this->info("Qarzdorlik eslatmasi: {$count} ta o'quvchi bo'yicha hodisa yaratildi.");
})->purpose('Qarzdor o\'quvchilarning ota-onalariga eslatma (Telegram)');

Artisan::command('reports:monthly {--year=} {--month=} {--no-ai}', function (MonthlyReportService $reports) {
    $year = (int) ($this->option('year') ?: now()->year);
    $month = (int) ($this->option('month') ?: now()->month);
    $count = $reports->dispatchAll($year, $month, ! $this->option('no-ai'));
    $this->info("Oylik hisobot ({$year}-{$month}): {$count} ta o'quvchi uchun yuborildi.");
})->purpose('Oy oxirida o\'zlashtirish tahlilini ota-onalarga yuborish');

Artisan::command('telegram:set-webhook {base?}', function () {
    $base = rtrim($this->argument('base') ?: config('app.url'), '/');

    foreach (['student' => 'student_webhook_secret', 'parent' => 'webhook_secret'] as $bot => $key) {
        $client = $bot === 'student' ? TelegramClient::student() : TelegramClient::parent();
        $secret = (string) config("services.telegram.{$key}");

        if (! $client->isConfigured() || $secret === '') {
            $this->warn("{$bot}: token yoki secret sozlanmagan — o'tkazib yuborildi.");

            continue;
        }

        $response = $client->setWebhook("{$base}/api/v1/telegram/{$bot}/{$secret}", $secret);
        $this->line("{$bot}: ".($response->json('ok') ? 'webhook o\'rnatildi' : 'xato: '.$response->body()));
    }
})->purpose('Ikkala Telegram bot uchun webhook o\'rnatish');

Artisan::command('academy:reset {--force}', function () {
    if (! $this->option('force') && ! $this->confirm('BARCHA ma\'lumotlar o\'chiriladi (superadmin, rollar, ruxsatlar, o\'quv yili va fanlar qoladi). Davom etilsinmi?')) {
        return;
    }

    $this->call('migrate:fresh', ['--force' => true]);
    $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => AcademicYearSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => SubjectSeeder::class, '--force' => true]);

    $user = User::updateOrCreate(
        ['username' => 'superadmin'],
        ['school_id' => config('school.id'), 'firstname' => 'Ismoil', 'lastname' => 'Usmonov', 'middlename' => 'Dilshod o`g`li',
            'phone' => '+998880072117', 'email' => 'superadmin@dmsystem.uz', 'password' => Hash::make('ismoil_007u'),
            'status' => 'active', 'locale' => 'uz'],
    );
    $user->roles()->sync(['superadmin']);

    $this->call('config:clear');
    $this->info('✅ Baza tozalandi. Faqat superadmin (ismoil_007u), rollar, ruxsatlar, o\'quv yili va fanlar qoldi.');
})->purpose('Demo ma\'lumotlarni o\'chirib, faqat superadminni qoldirish');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* ---------------- Jadval ---------------- */

// Telegram xabarlari — har daqiqa
Schedule::command('notifications:send')->everyMinute()->withoutOverlapping();

// Qarzdorlik eslatmalari — config('academy.debt_reminder_days') kunlari, 10:00
Schedule::command('finance:debt-reminders')->dailyAt('10:00')->when(
    fn () => in_array(now()->day, config('academy.debt_reminder_days', [5, 15, 25]), true)
);

// Oylik o'zlashtirish hisobotlari — har oyning config('academy.monthly_report_day') kuni, 18:00
Schedule::command('reports:monthly')->dailyAt('18:00')->when(
    fn () => now()->day === (int) config('academy.monthly_report_day', 28)
);

// Oylik hisob-fakturalar — config('school.invoice_generation_day') kuni
Schedule::call(fn () => app(FinanceService::class)->generateMonthlyInvoices(now()->year, now()->month))
    ->dailyAt('06:00')
    ->when(fn () => now()->day === (int) config('school.invoice_generation_day', 1))
    ->name('invoices:generate-monthly')
    ->withoutOverlapping();
