<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\CourseUnitController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationEventController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherSalaryController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VocabDeckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Italiya Akademiyasi LMS — API (v1)
|--------------------------------------------------------------------------
| `permission` middleware'i bilan himoyalangan har bir route avtomatik
| permission'ga aylanadi: `php artisan permissions:sync`
| Permission nomi: <controller>-<action>, masalan `course_unit-index`.
|
| O'quvchi kabineti (`learn/*`) `role:student` bilan himoyalanadi —
| o'quvchi faqat o'z darajasi/guruhiga mos materiallarni ko'radi.
*/

Route::prefix('v1')->group(function () {

    /* ==================== Ochiq ==================== */
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');

    // Sertifikatni tekshirish va chop etish (QR / havola orqali)
    Route::get('certificates/verify/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');
    Route::get('certificates/{serial}/html', [CertificateController::class, 'html'])->name('certificates.html');

    // Telegram webhook'lari (maxfiy yo'l bilan)
    Route::post('telegram/student/{secret}', [TelegramWebhookController::class, 'student'])->name('telegram.student');
    Route::post('telegram/parent/{secret}', [TelegramWebhookController::class, 'parent'])->name('telegram.parent');

    Route::middleware('auth:sanctum')->group(function () {

        /* ============ Har qanday avtorizatsiyalangan foydalanuvchi ============ */
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('guide', [GuideController::class, 'index'])->name('guide.index');
        // Brauzer push (Firebase) — har qanday foydalanuvchi
        Route::get('push/status', [PushTokenController::class, 'status'])->name('push.status');
        Route::post('push/token', [PushTokenController::class, 'store'])->name('push.store');
        Route::delete('push/token', [PushTokenController::class, 'destroy'])->name('push.destroy');
        Route::post('push/test', [PushTokenController::class, 'test'])->name('push.test');
        Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        /* ============ O'quvchi kabineti — o'qish va yechish ============ */
        Route::prefix('learn')->middleware('role:student')->group(function () {
            Route::get('dashboard', [LearnController::class, 'dashboard']);

            // Darsliklar (daraja/yo'nalish bo'yicha); audio necha marta eshitiladi — limit bilan
            Route::get('units', [LearnController::class, 'units']);
            Route::get('units/{unit}', [LearnController::class, 'unit']);
            Route::post('units/{unit}/play', [LearnController::class, 'playUnit']);
            Route::post('units/{unit}/complete', [LearnController::class, 'completeUnit']);

            // Testlar: biriktirilgan + ochiq mashqlar; urinish/eshitish/vaqt limitlari
            Route::get('tests', [LearnController::class, 'tests']);
            Route::post('assignments/{assignment}/start', [LearnController::class, 'startAssignment']);
            Route::post('practice/{test}/start', [LearnController::class, 'startPractice']);
            Route::get('attempts/{attempt}', [LearnController::class, 'attempt']);
            Route::post('attempts/{attempt}/sections/{section}/open', [LearnController::class, 'openSection']);
            Route::post('attempts/{attempt}/sections/{section}/play', [LearnController::class, 'playSection']);
            Route::post('attempts/{attempt}/questions/{question}/play', [LearnController::class, 'playQuestion']);
            Route::post('attempts/{attempt}/answer', [LearnController::class, 'answer']);
            Route::post('attempts/{attempt}/submit', [LearnController::class, 'submit']);
            Route::get('results', [LearnController::class, 'results']);

            // Ovozli lug'at va o'yinlar
            Route::get('decks', [LearnController::class, 'decks']);
            Route::get('decks/{deck}/cards', [LearnController::class, 'cards']);
            Route::get('games', [LearnController::class, 'games']);
            Route::post('games/{game}/start', [LearnController::class, 'startGame']);
            Route::post('game-sessions/{session}/answer', [LearnController::class, 'answerGame']);
            Route::post('game-sessions/{session}/finish', [LearnController::class, 'finishGame']);
            Route::get('leaderboard/{game?}', [LearnController::class, 'leaderboard']);

            // Uy ishi, Zoom, sertifikat, oylik hisobot
            Route::get('homeworks', [LearnController::class, 'homeworks']);
            Route::post('homeworks/{homework}/submit', [LearnController::class, 'submitHomework']);
            Route::get('meetings', [LearnController::class, 'meetings']);
            Route::get('certificate/eligibility', [LearnController::class, 'certificateEligibility']);
            Route::post('certificate', [LearnController::class, 'issueCertificate']);
            Route::get('certificates', [LearnController::class, 'certificates']);
            Route::get('monthly-report', [LearnController::class, 'monthlyReport']);

            // Dars yo'li (Duolingo uslubidagi qisqa darslar, XP)
            Route::get('path', [LearnController::class, 'path']);
            Route::get('path/{level}/{skill}', [LearnController::class, 'lessonIntro']);
            Route::post('path/{level}/{skill}/start', [LearnController::class, 'lessonStart']);
            Route::get('lessons/{lesson}', [LearnController::class, 'lesson']);
            Route::post('lessons/{lesson}/answer', [LearnController::class, 'lessonAnswer']);
            Route::post('lessons/{lesson}/finish', [LearnController::class, 'lessonFinish']);

            // Duel (WebSocket + polling): sherik yoki bot bilan "kim birinchi"
            Route::get('duels', [LearnController::class, 'duels']);
            Route::post('presence', [LearnController::class, 'presence']);
            Route::get('duels/opponents', [LearnController::class, 'duelOpponents']);
            Route::post('duels', [LearnController::class, 'duelCreate']);
            Route::post('duels/join', [LearnController::class, 'duelJoin']);
            Route::get('duels/{duel}', [LearnController::class, 'duel']);
            Route::post('duels/{duel}/join', [LearnController::class, 'duelJoin']);
            Route::post('duels/{duel}/bot', [LearnController::class, 'duelBot']);
            Route::post('duels/{duel}/answer', [LearnController::class, 'duelAnswer']);
            Route::post('duels/{duel}/cancel', [LearnController::class, 'duelCancel']);
        });

        /* ============ O'quvchi / ota-ona kabineti (read-only) ============ */
        Route::prefix('portal')->middleware('permission')->group(function () {
            Route::get('children', [PortalController::class, 'children']);
            Route::get('students/{student}', [PortalController::class, 'profile']);
            Route::get('students/{student}/attendance', [PortalController::class, 'attendance']);
            Route::get('students/{student}/grades', [PortalController::class, 'grades']);
            Route::get('students/{student}/tests', [PortalController::class, 'tests']);
            Route::get('students/{student}/tests/{attempt}', [PortalController::class, 'testAttempt']);
            Route::get('students/{student}/payments', [PortalController::class, 'payments']);
        });

        /* ============ Ruxsat talab qiladigan boshqaruv route'lari ============ */
        Route::middleware('permission')->group(function () {

            /* ---------- Foydalanuvchilar va RBAC ---------- */
            Route::apiResource('users', UserController::class);
            Route::patch('users/{user}/status', [UserController::class, 'toggleStatus']);
            Route::patch('users/{user}/password', [UserController::class, 'resetPassword']);
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);

            Route::apiResource('roles', RoleController::class);
            Route::get('roles/{role}/permissions', [RoleController::class, 'permissions']);
            Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

            Route::get('permissions', [PermissionController::class, 'index']);
            Route::get('permissions/grouped', [PermissionController::class, 'grouped']);
            Route::post('permissions/sync', [PermissionController::class, 'sync']);

            Route::get('ai/status', [AiController::class, 'status']);

            /* ---------- Akademiya strukturasi ---------- */
            Route::apiResource('academic-years', AcademicYearController::class)->parameters(['academic-years' => 'academicYear']);
            Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);

            Route::apiResource('subjects', SubjectController::class);

            Route::apiResource('teachers', TeacherController::class);
            Route::put('teachers/{teacher}/salary-settings', [TeacherController::class, 'updateSalarySettings']);

            // Guruhlar (daraja A1..C2 × yo'nalish). `classes` — eski nom, `groups` — yangi nom; ikkalasi bir xil.
            foreach (['classes', 'groups'] as $prefix) {
                Route::apiResource($prefix, SchoolClassController::class)->parameters([$prefix => 'schoolClass'])->names("{$prefix}");
                Route::get("{$prefix}/{schoolClass}/students", [SchoolClassController::class, 'students']);
                Route::put("{$prefix}/{schoolClass}/teachers", [SchoolClassController::class, 'assignTeachers']);
                Route::put("{$prefix}/{schoolClass}/class-teacher", [SchoolClassController::class, 'assignClassTeacher']);
                Route::get("{$prefix}/{schoolClass}/attendance/journal", [AttendanceController::class, 'journal']);
                Route::post("{$prefix}/{schoolClass}/attendance/journal", [AttendanceController::class, 'bulkStore']);
            }

            /* ---------- O'quvchilar ---------- */
            Route::apiResource('students', StudentController::class);
            Route::post('students/{student}/transfer', [StudentController::class, 'transfer']);
            Route::put('students/{student}/level', [StudentController::class, 'changeLevel']);
            Route::get('students/{student}/level-history', [StudentController::class, 'levelHistory']);
            Route::delete('students/{student}/device', [StudentController::class, 'resetDevice']);
            Route::get('students/{student}/class-history', [StudentController::class, 'classHistory']);
            Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);
            Route::get('students/{student}/grades', [StudentController::class, 'grades']);
            Route::get('students/{student}/finance', [StudentController::class, 'financeHistory']);
            Route::get('students/{student}/certificate-eligibility', [CertificateController::class, 'eligibility']);

            /* ---------- Ota-onalar ---------- */
            Route::apiResource('guardians', GuardianController::class);
            Route::post('guardians/{guardian}/students', [GuardianController::class, 'attachStudent']);
            Route::delete('guardians/{guardian}/students/{student}', [GuardianController::class, 'detachStudent']);
            Route::post('guardians/{guardian}/link-code', [GuardianController::class, 'regenerateLinkCode']);

            /* ---------- Moliya ---------- */
            Route::apiResource('tariffs', TariffController::class);

            Route::put('students/{student}/tariff', [FinanceController::class, 'assignTariff']);
            Route::put('students/{student}/discount', [FinanceController::class, 'setDiscount']);
            Route::delete('students/{student}/discount', [FinanceController::class, 'removeDiscount']);
            Route::get('students/{student}/monthly-amount', [FinanceController::class, 'monthlyAmount']);
            Route::get('students/{student}/balance', [FinanceController::class, 'balance']);
            Route::get('students/{student}/transactions', [FinanceController::class, 'transactions']);
            Route::get('finance/debtors', [FinanceController::class, 'debtors']);

            Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'update']);
            Route::post('invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);

            Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show', 'destroy']);

            /* ---------- Davomat ---------- */
            Route::apiResource('attendance', AttendanceController::class)->parameters(['attendance' => 'attendance']);

            /* ---------- Darslar (jadval) va baholar ---------- */
            Route::apiResource('lessons', LessonController::class);

            Route::apiResource('grades', GradeController::class)->parameters(['grades' => 'grade']);
            Route::post('grades/bulk', [GradeController::class, 'bulkStore']);

            /* ---------- Darsliklar (AI + o'qituvchi) va materiallar ---------- */
            Route::post('units/generate', [CourseUnitController::class, 'generate']);
            Route::apiResource('units', CourseUnitController::class)->parameters(['units' => 'unit']);
            Route::post('units/{unit}/audio', [CourseUnitController::class, 'generateAudio']);

            Route::get('materials', [CourseMaterialController::class, 'index']);
            Route::post('materials', [CourseMaterialController::class, 'store']);
            Route::delete('materials/{material}', [CourseMaterialController::class, 'destroy']);

            /* ---------- Testlar va AI (IELTS uslubi) ---------- */
            Route::post('tests/generate', [TestController::class, 'generate']);
            Route::apiResource('tests', TestController::class)->parameters(['tests' => 'test']);
            Route::post('tests/{test}/audio', [TestController::class, 'generateAudio']);
            Route::post('tests/{test}/publish', [TestController::class, 'publish']);
            Route::post('tests/{test}/assign', [TestController::class, 'assign']);
            Route::get('test-assignments', [TestController::class, 'assignments']);
            Route::get('test-assignments/{assignment}/results', [TestController::class, 'results']);
            Route::get('test-attempts/{attempt}', [TestController::class, 'attempt']);
            Route::put('test-attempts/{attempt}/grade', [TestController::class, 'gradeAttempt']);

            /* ---------- Ovozli lug'at va o'yinlar ---------- */
            Route::post('decks/generate', [VocabDeckController::class, 'generate']);
            Route::apiResource('decks', VocabDeckController::class)->parameters(['decks' => 'deck']);
            // Bitta Excel → lug'at + so'z o'yini + mashq testi (testlar va o'yinlar bir xil format)
            Route::post('decks/import-bundle', [VocabDeckController::class, 'importBundle']);
            Route::post('decks/{deck}/import', [VocabDeckController::class, 'import']);
            Route::post('decks/{deck}/audio', [VocabDeckController::class, 'generateAudio']);

            Route::apiResource('games', GameController::class);
            Route::get('games/{game}/results', [GameController::class, 'results']);
            Route::get('games/{game}/leaderboard', [GameController::class, 'leaderboard']);
            Route::get('leaderboard', [GameController::class, 'leaderboard']);

            /* ---------- Uy ishi ---------- */
            Route::apiResource('homeworks', HomeworkController::class);
            Route::get('homeworks/{homework}/submissions', [HomeworkController::class, 'submissions']);
            Route::put('homework-submissions/{submission}/grade', [HomeworkController::class, 'grade']);

            /* ---------- Zoom mitinglar ---------- */
            Route::apiResource('meetings', MeetingController::class);

            /* ---------- Sertifikatlar ---------- */
            Route::get('certificates', [CertificateController::class, 'index']);
            Route::post('certificates', [CertificateController::class, 'issue']);
            Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->whereNumber('certificate');

            /* ---------- Ish haqi ---------- */
            Route::apiResource('teacher-salaries', TeacherSalaryController::class)
                ->only(['index', 'store', 'show', 'update'])
                ->parameters(['teacher-salaries' => 'salary']);
            Route::post('teacher-salaries/generate-monthly', [TeacherSalaryController::class, 'generateMonthly']);
            Route::post('teacher-salaries/{salary}/pay', [TeacherSalaryController::class, 'markPaid']);
            Route::get('teachers/{teacher}/salary-preview', [TeacherSalaryController::class, 'preview']);

            /* ---------- Hisobotlar ---------- */
            Route::prefix('reports')->group(function () {
                Route::get('dashboard', [ReportController::class, 'dashboard']);
                Route::get('attendance', [ReportController::class, 'attendance']);
                Route::get('grades', [ReportController::class, 'grades']);
                Route::get('payments', [ReportController::class, 'payments']);
                Route::get('debtors', [ReportController::class, 'debtors']);
                Route::get('salaries', [ReportController::class, 'salaries']);
                Route::get('skills', [ReportController::class, 'skills']);
                Route::get('monthly-progress', [ReportController::class, 'monthlyProgress']);
                Route::post('monthly-progress/send', [ReportController::class, 'sendMonthlyProgress']);
            });

            /* ---------- Telegram bot hodisalari ---------- */
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationEventController::class, 'index']);
                Route::get('pending', [NotificationEventController::class, 'pending']);
                Route::get('{event}', [NotificationEventController::class, 'show']);
                Route::post('{event}/sent', [NotificationEventController::class, 'markSent']);
                Route::post('{event}/failed', [NotificationEventController::class, 'markFailed']);
            });
        });
    });
});
