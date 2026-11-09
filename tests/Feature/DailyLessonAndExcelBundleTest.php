<?php

use App\Models\CourseUnit;
use App\Models\Game;
use App\Models\Teacher;
use App\Models\TestTemplate;
use App\Models\VocabDeck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kunlik darsliklar (PDF majburiy, YouTube video, ochilish sanasi) va
 * bitta Excel orqali lug'at + o'yin + test yaratish.
 */
beforeEach(function () {
    seedRbac();
    Storage::fake('public');
    $this->user = actingAsUser('teacher');
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
});

function unitPayload(array $extra = []): array
{
    return $extra + [
        'level' => 'A1', 'skill' => 'reading', 'title' => 'Giorno 1', 'content' => '# Testo', 'is_published' => true,
        'pdf' => UploadedFile::fake()->create('giorno-1.pdf', 30, 'application/pdf'),
        'available_on' => today()->toDateString(),
    ];
}

function xlsxFixture(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/italyan_50_soz.xlsx'), 'italyan_50_soz.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('kunlik darslik PDF va ochilish sanasisiz qabul qilinmaydi, YouTube havolasi video id ga aylanadi', function () {
    $this->postJson('/api/v1/units', ['level' => 'A1', 'skill' => 'reading', 'title' => 'PDF yo\'q', 'available_on' => today()->toDateString()])
        ->assertStatus(422)->assertJsonValidationErrors(['pdf']);

    $this->postJson('/api/v1/units', ['level' => 'A1', 'skill' => 'reading', 'title' => 'Sana yo\'q', 'pdf' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertStatus(422)->assertJsonValidationErrors(['available_on']);

    $this->postJson('/api/v1/units', unitPayload(['pdf' => UploadedFile::fake()->create('rasm.jpg', 10, 'image/jpeg')]))
        ->assertStatus(422)->assertJsonValidationErrors(['pdf']);

    $unit = $this->postJson('/api/v1/units', unitPayload(['media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s']))
        ->assertCreated()->json('data');

    expect($unit['has_pdf'])->toBeTrue()
        ->and($unit['pdf_url'])->toContain('/storage/units/')
        ->and($unit['youtube_id'])->toBe('dQw4w9WgXcQ')
        ->and($unit['youtube_embed_url'])->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0')
        ->and($unit['available_on'])->toBe(today()->toDateString())
        ->and($unit['is_available'])->toBeTrue();

    Storage::disk('public')->assertExists(CourseUnit::find($unit['id'])->pdf_path);

    // Tahrirda PDF ixtiyoriy — yangisi yuklansa eskisi o'chadi
    $oldPath = CourseUnit::find($unit['id'])->pdf_path;
    $this->putJson("/api/v1/units/{$unit['id']}", ['title' => 'Giorno 1 (yangilandi)'])->assertOk()->assertJsonPath('data.has_pdf', true);
    $this->post("/api/v1/units/{$unit['id']}", ['_method' => 'PUT', 'pdf' => UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists(CourseUnit::find($unit['id'])->pdf_path);
});

it('o\'quvchi kelajakdagi darsliklarni ko\'rmaydi — faqat bugungi va oldingilarini', function () {
    $yesterday = $this->postJson('/api/v1/units', unitPayload(['title' => 'Kecha', 'available_on' => today()->subDay()->toDateString()]))->assertCreated()->json('data.id');
    $todayId = $this->postJson('/api/v1/units', unitPayload(['title' => 'Bugun', 'order' => 2]))->assertCreated()->json('data.id');
    $tomorrow = $this->postJson('/api/v1/units', unitPayload(['title' => 'Ertaga', 'order' => 3, 'available_on' => today()->addDay()->toDateString()]))->assertCreated()->json('data.id');
    $nextMonth = $this->postJson('/api/v1/units', unitPayload(['title' => 'Keyingi oy', 'order' => 4, 'available_on' => today()->addMonth()->toDateString()]))->assertCreated()->json('data.id');

    // O'qituvchi hammasini ko'radi
    $this->getJson('/api/v1/units?level=A1')->assertOk()->assertJsonCount(4, 'data');

    loginAsStudent(makeStudent('A1'));
    $ids = collect($this->getJson('/api/v1/learn/units')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$yesterday, $todayId])
        ->and($ids)->not->toContain($tomorrow, $nextMonth);

    $this->getJson("/api/v1/learn/units/{$todayId}")->assertOk()->assertJsonPath('data.is_available', true);
    $this->getJson("/api/v1/learn/units/{$tomorrow}")->assertForbidden();
    $this->postJson("/api/v1/learn/units/{$tomorrow}/complete")->assertForbidden();
    $this->postJson("/api/v1/learn/units/{$todayId}/complete")->assertOk();
});

it('bitta Excel (italyan_50_soz.xlsx) dan lug\'at to\'plami, so\'z o\'yini va mashq testi yaratiladi', function () {
    $res = $this->post('/api/v1/decks/import-bundle', ['file' => xlsxFixture(), 'level' => 'A1', 'question_count' => 20], ['Accept' => 'application/json'])
        ->assertCreated()->json();

    expect($res['data']['imported'])->toBe(50)
        ->and($res['data']['deck']['title'])->toBe("Italiya Tili - Eng Kerakli 50 ta So'z")
        ->and($res['data']['deck']['cards_count'])->toBe(50)
        ->and($res['data']['deck']['cards'][0]['word'])->toBe('Ciao')
        ->and($res['data']['deck']['cards'][0]['translations']['uz'])->toBe('Salom / Xayr')
        ->and($res['data']['deck']['cards'][0]['category'])->toBe('Salomlashish')
        ->and($res['data']['game']['type'])->toBe('choice')
        ->and($res['data']['game']['card_count'])->toBe(10)
        ->and($res['data']['test']['kind'])->toBe('practice')
        ->and($res['data']['test']['question_count'])->toBe(20)
        ->and($res['data']['test']['is_published'])->toBeTrue();

    $test = TestTemplate::with('questions.options')->find($res['data']['test']['id']);

    expect($test->questions)->toHaveCount(20)
        ->and($test->questions->every(fn ($q) => $q->type === 'single_choice' && $q->options->count() === 4 && $q->options->where('is_correct', true)->count() === 1))->toBeTrue()
        ->and($test->questions->first()->body)->toContain('so\'zining');

    expect(Game::count())->toBe(1)->and(VocabDeck::count())->toBe(1)->and(Game::first()->vocab_deck_id)->toBe(VocabDeck::first()->id);

    // O'quvchi o'yinni va mashq testini ko'radi
    loginAsStudent(makeStudent('A1'));
    $this->getJson('/api/v1/learn/games')->assertOk()->assertJsonPath('data.0.id', $res['data']['game']['id']);
    $this->getJson('/api/v1/learn/tests')->assertOk()->assertJsonPath('data.practice.0.template_id', $res['data']['test']['id']);
});

it('import kunlik darsga biriktiriladi va o\'yin/test ixtiyoriy', function () {
    $unit = $this->postJson('/api/v1/units', unitPayload())->assertCreated()->json('data');

    $csv = "Italyan tili — 1-dars\n\n№,Italiya so'zi,O'zbekcha tarjimasi,Kategoriya\n1,Ciao,Salom,Salomlashish\n2,Grazie,Rahmat,Odob\n3,Pane,Non,Ovqat\n";
    $res = $this->post('/api/v1/decks/import-bundle', [
        'file' => UploadedFile::fake()->createWithContent('sozlar.csv', $csv),
        'level' => 'A1', 'course_unit_id' => $unit['id'], 'create_game' => false, 'title' => 'Dars 1',
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');

    expect($res['game'])->toBeNull()
        ->and($res['deck']['title'])->toBe('Dars 1')
        ->and($res['deck']['cards_count'])->toBe(3)
        ->and($res['test']['question_count'])->toBe(3)
        ->and($res['test']['course_unit_id'])->toBe($unit['id']);

    expect(CourseUnit::find($unit['id'])->test_template_id)->toBe($res['test']['id']);

    // Variantlar soni so'zlar yetarli bo'lmaganda ham to'g'ri javob bitta
    $test = TestTemplate::with('questions.options')->find($res['test']['id']);
    expect($test->questions->every(fn ($q) => $q->options->count() === 3 && $q->options->where('is_correct', true)->count() === 1))->toBeTrue();

    // Kamida 2 ta so'z kerak
    $this->post('/api/v1/decks/import-bundle', ['file' => UploadedFile::fake()->createWithContent('bitta.csv', "so'z,tarjima\nCiao,Salom\n"), 'level' => 'A1'], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('mavjud to\'plamga ham sarlavhali va № ustunli Excel import bo\'ladi', function () {
    $deck = VocabDeck::create(['school_id' => 1, 'title' => 'Import', 'level' => 'A1']);

    $this->post("/api/v1/decks/{$deck->id}/import", ['file' => xlsxFixture()], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.cards_count', 50)->assertJsonPath('data.cards.49.word', 'Caldo')->assertJsonPath('data.cards.49.translations.uz', 'Issiq');
});
