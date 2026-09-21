<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>{{ $certificate->serial }} — {{ $student->full_name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=UnifrakturMaguntia&family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
  body{margin:0;background:#0f172a;font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#0f172a}
  .cert{width:1050px;margin:32px auto;background:#fff;border:14px solid #0f172a;position:relative;padding:60px 70px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .cert:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(0,140,69,.10),rgba(255,255,255,0) 45%,rgba(205,33,42,.10));pointer-events:none}
  .ribbon{height:10px;background:linear-gradient(90deg,#008C45 33%,#f4f5f0 33% 66%,#CD212A 66%);margin:-60px -70px 40px}
  .title{font-family:'UnifrakturMaguntia',serif;font-size:56px;text-align:center;margin:0}
  .sub{font-family:'Cinzel',serif;text-align:center;letter-spacing:.3em;font-size:14px;color:#475569;margin-top:6px}
  .no{text-align:right;font-family:'Cinzel',serif;font-size:13px;color:#64748b}
  .latin{text-align:center;font-style:italic;color:#475569;margin:36px 0 14px}
  .name{font-family:'Cinzel',serif;font-size:42px;font-weight:800;text-align:center;margin:0}
  .group{text-align:center;font-size:18px;margin-top:8px;color:#334155}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:36px;align-items:center}
  .score{background:#0f172a;color:#fff;border-radius:16px;padding:22px 26px}
  .score b{font-size:40px;display:block;font-family:'Cinzel',serif}
  .score small{opacity:.8}
  .skills{display:grid;grid-template-columns:1fr 1fr;gap:10px 22px;font-size:14px}
  .bar{height:8px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin-top:4px}
  .bar i{display:block;height:100%;background:linear-gradient(90deg,#008C45,#CD212A)}
  .foot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:44px;font-size:13px;color:#475569}
  .sign{font-family:'Cinzel',serif;font-size:15px;color:#0f172a;border-top:1px solid #94a3b8;padding-top:6px;min-width:220px;text-align:center}
  .qr{width:110px;height:110px;border:1px solid #cbd5e1}
  .print{position:fixed;top:14px;right:14px;background:#008C45;color:#fff;border:0;padding:10px 18px;border-radius:10px;font-weight:600;cursor:pointer}
  @media print{body{background:#fff}.cert{margin:0;box-shadow:none;border-width:10px}.print{display:none}}
</style>
</head>
<body>
<button class="print" onclick="window.print()">Chop etish / PDF</button>
<div class="cert">
  <div class="ribbon"></div>
  <div class="no">NO: {{ $certificate->serial }}</div>
  <h1 class="title">Certificato di Competenza</h1>
  <div class="sub">{{ mb_strtoupper($school['name']) }} · {{ $levelTitle }}</div>
  <p class="latin">Per la straordinaria dedizione allo studio della lingua italiana, si conferisce il presente certificato a</p>
  <h2 class="name">{{ $student->full_name }}</h2>
  <div class="group">{{ $trackTitle }} · Livello <b>{{ $certificate->level }}</b> · {{ $certificate->period_label }}</div>

  <div class="grid">
    <div class="score">
      <small>Punteggio complessivo · {{ $certificate->grade_label }}</small>
      <b>{{ number_format($certificate->overall_score, 0) }}/100</b>
      <small>Faollik: {{ number_format($certificate->attendance_score, 0) }} · Uy ishi: {{ number_format($certificate->homework_score, 0) }} · Test: {{ number_format($certificate->test_score, 0) }}</small>
    </div>
    <div class="skills">
      @foreach(($certificate->skills ?? []) as $skill => $percent)
        <div>
          <div style="display:flex;justify-content:space-between"><span>{{ $skillLabels[$skill] ?? $skill }}</span><b>{{ number_format($percent, 0) }}%</b></div>
          <div class="bar"><i style="width:{{ min(100, max(0, $percent)) }}%"></i></div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="foot">
    <div>
      <img class="qr" alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($verifyUrl) }}">
      <div style="margin-top:6px">Verifica: <b>{{ $certificate->verify_code }}</b></div>
    </div>
    <div style="text-align:center">
      <div>{{ $certificate->issued_at->translatedFormat('d F Y') }}</div>
      <div style="margin-top:4px">{{ $school['address'] }}</div>
    </div>
    <div class="sign">{{ $academy['certificate']['signer_name'] }}</div>
  </div>
</div>
</body>
</html>
