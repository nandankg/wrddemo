<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
set_app_context('ppms');
$APP = app_ctx();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/ppms/index.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/ppms/index.php')); exit; }

// PPMS role quick-pick (only this product's roles)
$quick = [
  ['SECRETARY','Secretary','🏛'],['EIC','Engineer-in-Chief','⚙'],['SE','Superintending Engr','📐'],
  ['EE','Executive Engineer','📋'],['AE','Assistant Engineer','📏'],['JE','Junior Engineer','🛠'],
  ['FINANCE','Finance Officer','₹'],
];
$acc = $APP['accent'];
?><!doctype html>
<html lang="<?= lang() ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($APP['short']) ?> · Sign in · WRD Jharkhand</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<style>body{font-family:'Inter',sans-serif} .d{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="min-h-screen grid lg:grid-cols-2" style="--acc:<?= e($acc) ?>">
  <!-- brand panel -->
  <div class="hidden lg:flex flex-col justify-between p-12 text-white relative"
       style="background:radial-gradient(1000px 300px at 80% -10%, <?= e($acc) ?>55, transparent), linear-gradient(180deg,#0a2a44,#0c3350)">
    <a href="<?= base_url('index.php') ?>" class="relative z-10 flex items-center gap-3">
      <span class="grid place-items-center w-11 h-11 rounded-xl text-2xl" style="background:<?= e($acc) ?>33"><?= $APP['icon'] ?></span>
      <div><div class="d font-bold"><?= e($APP['short']) ?></div><div class="text-xs text-cyan-100"><?= t('govt') ?></div></div>
    </a>
    <div class="relative z-10">
      <h1 class="d text-4xl font-bold leading-tight"><?= is_hi()?e($APP['name_hi']):e($APP['name']) ?></h1>
      <p class="text-cyan-100/90 mt-4 max-w-md"><?= is_hi()?e($APP['tagline_hi']):e($APP['tagline']) ?></p>
    </div>
    <p class="relative z-10 text-xs text-cyan-200/70">Secured with MFA · RBAC · TLS 1.3 · CERT-In VAPT</p>
  </div>

  <!-- form panel -->
  <div class="flex items-center justify-center p-6 bg-paper">
    <div class="w-full max-w-md">
      <div class="card p-7">
        <a href="<?= base_url('index.php') ?>" class="text-xs text-slate-500 hover:underline">← <?= is_hi()?'सभी उत्पाद':'All products' ?></a>
        <h2 class="d text-2xl font-bold text-ink mt-2"><?= e($APP['short']) ?> · <?= t('login') ?></h2>
        <p class="text-sm text-slate-500 mt-1"><?= is_hi()?'अपने विभागीय खाते से प्रवेश करें':'Sign in to your departmental account' ?></p>

        <?php if ($error): ?><div class="mt-4 bg-rose-50 text-rose-700 text-sm rounded-lg px-3 py-2 ring-1 ring-rose-200"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="mt-5 space-y-4">
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपयोगकर्ता नाम':'Username' ?></label>
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. ee">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="demo123">
          </div>
          <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in (PPMS roles)</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= e($q[0]) ?>&to=<?= urlencode(base_url('app/ppms/index.php')) ?>"
                 class="text-center border border-slate-200 rounded-xl px-2 py-2.5 hover:border-slate-400 hover:bg-white transition">
                <div class="text-lg"><?= $q[2] ?></div><div class="text-[11px] text-slate-600 mt-0.5 leading-tight"><?= e($q[1]) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="text-[11px] text-slate-400 mt-3 text-center">All accounts · password <code class="bg-slate-100 px-1 rounded">demo123</code></p>
        </div>
      </div>
    </div>
  </div>
</body></html>
