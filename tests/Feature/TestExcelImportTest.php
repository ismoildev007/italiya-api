<?php

use App\Models\Teacher;
use Illuminate\Http\UploadedFile;

/** Excel/CSV dan test savollarini o'qish (preview) — o'qituvchi to'g'ri javobni belgilab saqlaydi. */
beforeEach(function () {
    seedRbac();
    $this->user = actingAsUser('teacher');
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
});

it('standart formatdagi savollar faylini o\'qiydi: harf, raqam, matn va bir nechta to\'g\'ri javob', function () {
    $csv = "A1 Grammatika testi\n\n№,Savol,A,B,C,D,To'g'ri javob,Izoh,Ball\n"
        ."1,Come ti chiami?,Mi chiamo Anna,Ho fame,Sono qui,Grazie,A,Ism so'rash,2\n"
        ."2,Scegli il saluto,Pane,Ciao,Acqua,Treno,2,,\n"
        ."3,Quale è un colore?,Rosso,Verde,Cane,Casa,\"A, B\",,\n"
        ."4,Buongiorno = Xayrli tong,,,,,true,,\n"
        ."5,Traduci: Grazie,,,,,Rahmat,,\n"
        ."6,Belgilanmagan savol,Uno,Due,Tre,Quattro,,,\n";

    $data = $this->post('/api/v1/tests/import-preview', ['file' => UploadedFile::fake()->createWithContent('test.csv', $csv)], ['Accept' => 'application/json'])
        ->assertOk()->json('data');

    expect($data['source'])->toBe('questions')
        ->and($data['title'])->toBe('A1 Grammatika testi')
        ->and($data['questions'])->toHaveCount(6)
        ->and($data['unmarked'])->toBe(1)
        ->and($data['questions'][0]['type'])->toBe('single_choice')
        ->and((float) $data['questions'][0]['score'])->toBe(2.0)
        ->and($data['questions'][0]['explanation'])->toBe('Ism so\'rash')
        ->and(collect($data['questions'][0]['options'])->firstWhere('is_correct', true)['body'])->toBe('Mi chiamo Anna')
        ->and(collect($data['questions'][1]['options'])->firstWhere('is_correct', true)['body'])->toBe('Ciao')
        ->and($data['questions'][2]['type'])->toBe('multiple_choice')
        ->and(collect($data['questions'][2]['options'])->where('is_correct', true)->pluck('body')->all())->toBe(['Rosso', 'Verde'])
        ->and($data['questions'][3]['type'])->toBe('true_false')
        ->and($data['questions'][3]['correct_answer'])->toBe('true')
        ->and($data['questions'][4]['type'])->toBe('short_text')
        ->and($data['questions'][4]['correct_answer'])->toBe('Rahmat')
        ->and(collect($data['questions'][5]['options'])->where('is_correct', true)->count())->toBe(0);

    // O'qituvchi belgilab saqlaydi — mavjud POST tests
    $questions = $data['questions'];
    $questions[5]['options'][2]['is_correct'] = true;
    $saved = $this->postJson('/api/v1/tests', ['title' => $data['title'], 'level' => 'A1', 'kind' => 'practice', 'skill' => 'grammar', 'questions' => $questions])
        ->assertCreated()->json('data');

    expect($saved['question_count'])->toBe(6);
});

it('lug\'at formatidagi fayldan variantli savollar tuzadi', function () {
    $file = new UploadedFile(base_path('tests/Fixtures/italyan_50_soz.xlsx'), 'italyan_50_soz.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $data = $this->post('/api/v1/tests/import-preview', ['file' => $file, 'question_count' => 12], ['Accept' => 'application/json'])
        ->assertOk()->json('data');

    expect($data['source'])->toBe('vocabulary')
        ->and($data['questions'])->toHaveCount(12)
        ->and($data['unmarked'])->toBe(0)
        ->and(collect($data['questions'])->every(fn ($q) => count($q['options']) === 4 && collect($q['options'])->where('is_correct', true)->count() === 1))->toBeTrue();
});

it('sarlavhasiz va bo\'sh fayl', function () {
    $data = $this->post('/api/v1/tests/import-preview', ['file' => UploadedFile::fake()->createWithContent('q.csv', "Chi sei?,Io,Tu,Lui,Lei,C\n")], ['Accept' => 'application/json'])
        ->assertOk()->json('data');

    expect($data['questions'][0]['body'])->toBe('Chi sei?')
        ->and(collect($data['questions'][0]['options'])->firstWhere('is_correct', true)['body'])->toBe('Lui');

    $this->post('/api/v1/tests/import-preview', ['file' => UploadedFile::fake()->createWithContent('e.csv', "\n")], ['Accept' => 'application/json'])->assertStatus(422);
});
