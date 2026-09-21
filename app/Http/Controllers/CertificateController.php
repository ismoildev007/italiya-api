<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\IndexCertificateRequest;
use App\Http\Requests\Certificate\IssueCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Student;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Sertifikatlar: admin ro'yxati/berish, ommaviy tekshirish va chop etish (HTML).
 */
class CertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function index(IndexCertificateRequest $request): JsonResponse
    {
        return $this->ok(CertificateResource::collection($this->certificates->paginate($request->filters(), $request->perPage())));
    }

    public function show(Certificate $certificate): JsonResponse
    {
        return $this->ok(new CertificateResource($certificate->load('student.schoolClass')));
    }

    /** Administrator/o'qituvchi o'quvchi uchun beradi (force = 25-sana qoidasini chetlab o'tish) */
    public function issue(IssueCertificateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $certificate = $this->certificates->issue(Student::findOrFail($data['student_id']), null, $request->user()->id, (bool) ($data['force'] ?? false));

        return $this->created(new CertificateResource($certificate->load('student')), 'Sertifikat berildi.');
    }

    public function eligibility(Student $student): JsonResponse
    {
        $result = $this->certificates->eligibility($student);
        $result['already_issued'] = $result['already_issued'] ? new CertificateResource($result['already_issued']) : null;

        return $this->ok($result);
    }

    /* ---------- Ommaviy (token talab qilinmaydi) ---------- */

    public function verify(string $code): JsonResponse
    {
        $certificate = $this->certificates->verify($code);

        return $certificate
            ? $this->ok(['valid' => true, 'certificate' => new CertificateResource($certificate)])
            : $this->fail('Sertifikat topilmadi.', 404);
    }

    public function html(string $serial): Response
    {
        $certificate = Certificate::where('serial', strtoupper($serial))->firstOrFail();

        return response($this->certificates->render($certificate))->header('Content-Type', 'text/html; charset=utf-8');
    }
}
