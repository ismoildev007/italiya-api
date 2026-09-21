<?php

use App\Models\Guardian;
use App\Models\NotificationEvent;
use App\Models\Student;
use App\Models\Tariff;
use App\Services\FinanceService;

beforeEach(function () {
    seedRbac();
    $this->tariff = Tariff::factory()->create(['monthly_price' => 1_000_000]);
    $this->student = Student::factory()->create();
    $this->finance = app(FinanceService::class);
    $this->finance->assignTariff($this->student, $this->tariff);
});

it('oylik summani tarif narxidan hisoblaydi', function () {
    expect($this->finance->monthlyAmount($this->student)['total'])->toBe(1000000.0);
});

it('foizli individual chegirmani qo\'llaydi', function () {
    $this->finance->setDiscount($this->student, 'percent', 30, 'Ko\'p bolali oila');

    $amounts = $this->finance->monthlyAmount($this->student->fresh());

    expect($amounts['base'])->toBe(1000000.0)
        ->and($amounts['discount'])->toBe(300000.0)
        ->and($amounts['total'])->toBe(700000.0);
});

it('summaviy chegirmani qo\'llaydi', function () {
    $this->finance->setDiscount($this->student, 'amount', 250000, 'Xodim farzandi');

    expect($this->finance->monthlyAmount($this->student->fresh())['total'])->toBe(750000.0);
});

it('chegirma faqat bitta o\'quvchiga ta\'sir qiladi', function () {
    $boshqa = Student::factory()->create();
    $this->finance->assignTariff($boshqa, $this->tariff);

    $this->finance->setDiscount($this->student, 'percent', 50, 'Imtiyoz');

    expect($this->finance->monthlyAmount($this->student->fresh())['total'])->toBe(500000.0)
        ->and($this->finance->monthlyAmount($boshqa->fresh())['total'])->toBe(1000000.0);
});

it('to\'lov kam bo\'lsa qarzdorlik (manfiy balans) ko\'rsatadi', function () {
    $this->finance->generateInvoice($this->student, 2026, 9);
    $this->finance->registerPayment($this->student, ['amount' => 400_000]);

    $balance = $this->finance->recalculate($this->student);

    expect((float) $balance->balance)->toBe(-600000.0)
        ->and($balance->isDebtor())->toBeTrue();
});

it('umuman to\'lanmasa to\'liq summa qarz bo\'lib qoladi', function () {
    $this->finance->generateInvoice($this->student, 2026, 9);

    expect((float) $this->finance->recalculate($this->student)->balance)->toBe(-1000000.0);
});

it('ortiqcha to\'lov musbat balans bo\'lib keyingi oyga o\'tadi', function () {
    $this->finance->generateInvoice($this->student, 2026, 9);
    $this->finance->registerPayment($this->student, ['amount' => 1_500_000]);

    expect((float) $this->finance->recalculate($this->student)->balance)->toBe(500000.0);

    // Keyingi oy hisobi musbat balansdan avtomatik qoplanadi
    $keyingi = $this->finance->generateInvoice($this->student->fresh(), 2026, 10);

    expect((float) $keyingi->paid_amount)->toBe(500000.0)
        ->and($keyingi->status)->toBe('partial')
        ->and((float) $this->finance->recalculate($this->student)->balance)->toBe(-500000.0);
});

it('to\'liq to\'langan hisob-fakturani `paid` deb belgilaydi', function () {
    $invoice = $this->finance->generateInvoice($this->student, 2026, 9);
    $this->finance->registerPayment($this->student, ['amount' => 1_000_000]);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $this->finance->recalculate($this->student)->balance)->toBe(0.0);
});

it('har bir moliyaviy harakatni tarixga yozadi', function () {
    $this->finance->generateInvoice($this->student, 2026, 9);
    $this->finance->registerPayment($this->student, ['amount' => 1_000_000]);

    $types = $this->student->transactions()->pluck('type');

    expect($types)->toContain('tariff_change', 'invoice', 'payment');
});

it('bir oy uchun ikkinchi hisob-faktura yaratmaydi', function () {
    $birinchi = $this->finance->generateInvoice($this->student, 2026, 9);
    $ikkinchi = $this->finance->generateInvoice($this->student->fresh(), 2026, 9);

    expect($ikkinchi->id)->toBe($birinchi->id)
        ->and($this->student->invoices()->count())->toBe(1);
});

it('to\'lov qabul qilinganda ota-onaga hodisa generatsiya qiladi', function () {
    $guardian = Guardian::factory()->linked()->create();
    $guardian->students()->attach($this->student->id, [
        'school_id' => config('school.id'), 'relation' => 'father', 'is_primary' => true,
    ]);

    $this->finance->generateInvoice($this->student, 2026, 9);
    $this->finance->registerPayment($this->student, ['amount' => 1_000_000]);

    expect(NotificationEvent::where('type', 'payment_received')->count())->toBe(1);
});
