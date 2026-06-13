<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/dashboard.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/dashboard.php')); exit; }

$quick = [
  ['secretary','Secretary',  '🏛'],['eic','Engineer-in-Chief','⚙'],['ee','Executive Engineer','📐'],
  ['ae','Assistant Engineer','📏'],['je','Junior Engineer','🛠'],['finance','Finance Officer','₹'],
  ['aso','Section Officer','🗂'],['consumer','Water Consumer','🏭'],['contractor','Contractor','⚒'],
];
?><!doctype html>
<html lang="<?= lang() ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login · WRD Jharkhand</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{ink:'#06314a',brand:'#0E7C86',branddeep:'#0a5d65',brandsoft:'#e6f4f4'},fontFamily:{display:['Fraunces','serif'],sans:['Mukta','sans-serif']}}}}</script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="min-h-screen grid lg:grid-cols-2 font-sans">
  <!-- brand panel -->
  <div class="water-hero grain hidden lg:flex flex-col justify-between p-12 text-white relative">
    <a href="<?= base_url('index.php') ?>" class="relative z-10 flex items-center gap-3">
      <span class="grid place-items-center w-11 h-11 rounded-xl bg-white/15">
        <svg width="22" height="22" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg>
      </span>
      <div><div class="font-display font-semibold"><?= t('portal_name') ?></div><div class="text-xs text-cyan-100"><?= t('govt') ?></div></div>
    </a>
    <div class="relative z-10">
      <h1 class="font-display text-4xl font-semibold leading-tight"><?= is_hi()?'एकीकृत डिजिटल रीढ़':'One integrated digital backbone' ?></h1>
      <p class="text-cyan-100/90 mt-4 max-w-md"><?= is_hi()?'पाँच मंच, एक सुरक्षित लॉगिन। भूमिका के अनुसार डैशबोर्ड एवं कार्यप्रवाह।':'Five platforms, one secure login. Role-based dashboards and workflows across the WRD ecosystem.' ?></p>
      <div class="flex flex-wrap gap-2 mt-6 text-xs">
        <?php foreach (['PPMS','Contractor','Allocation','E-Tariff','CMS'] as $m): ?>
          <span class="bg-white/10 ring-1 ring-white/20 px-3 py-1.5 rounded-full"><?= $m ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="relative z-10 text-xs text-cyan-200/70">Secured with MFA · RBAC · TLS 1.3 · CERT-In VAPT</p>
  </div>

  <!-- form panel -->
  <div class="flex items-center justify-center p-6 bg-paper">
    <div class="w-full max-w-md">
      <div class="lg:hidden mb-6 text-center">
        <div class="font-display text-2xl font-semibold text-ink"><?= t('portal_name') ?></div>
        <div class="text-sm text-slate-500"><?= t('govt') ?></div>
      </div>
      <div class="card p-7">
        <h2 class="font-display text-2xl font-semibold text-ink"><?= t('login') ?></h2>
        <p class="text-sm text-slate-500 mt-1"><?= is_hi()?'अपने विभागीय खाते से प्रवेश करें':'Sign in to your departmental account' ?></p>

        <?php if ($error): ?><div class="mt-4 bg-rose-50 text-rose-700 text-sm rounded-lg px-3 py-2 ring-1 ring-rose-200"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="mt-5 space-y-4">
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपयोगकर्ता नाम':'Username' ?></label>
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand focus:border-brand" placeholder="e.g. ee">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand focus:border-brand" placeholder="demo123">
          </div>
          <button class="w-full bg-brand hover:bg-branddeep text-white font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= strtoupper($q[0]==='secretary'?'SECRETARY':($q[0]==='eic'?'EIC':($q[0]==='ee'?'EE':($q[0]==='ae'?'AE':($q[0]==='je'?'JE':($q[0]==='finance'?'FINANCE':($q[0]==='aso'?'ASO':($q[0]==='consumer'?'CONSUMER':'CONTRACTOR')))))))) ?>"
                 class="text-center border border-slate-200 rounded-xl px-2 py-2.5 hover:border-brand hover:bg-brandsoft transition">
                <div class="text-lg"><?= $q[2] ?></div><div class="text-[11px] text-slate-600 mt-0.5 leading-tight"><?= e($q[1]) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="text-[11px] text-slate-400 mt-3 text-center">All accounts · password <code class="bg-slate-100 px-1 rounded">demo123</code></p>
        </div>
      </div>
      <p class="text-center mt-4 text-sm"><a href="<?= base_url('index.php') ?>" class="text-slate-500 hover:text-brand">← <?= t('home') ?></a></p>
    </div>
  </div>
</body></html>
