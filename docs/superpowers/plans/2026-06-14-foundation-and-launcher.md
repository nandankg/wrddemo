# WRD Suite — Foundation & Launcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the integrated portal's shared shell with a per-product theming foundation (app registry + app-context + themed shell + role scoping), and turn `index.php` into the official "WRD Project Suite" launcher.

**Architecture:** A single **app registry** describes each of the five independent products (key, names, accent, icon, roles, nav). An **app-context** layer lets any page declare "I am product X"; the shared header/sidebar then theme themselves from that context instead of the hard-coded integrated menu. The launcher renders the registry as premium product cards. No product knows about another; the only shared thing is the design-system layer.

**Tech Stack:** PHP 8.2, Tailwind (CDN) + `assets/css/app.css`, MariaDB. Zero-dependency PHP test runner (no Composer, XAMPP-friendly).

**Scope note:** This plan delivers the shared foundation + launcher only. Each of the five products (PPMS first as reference) is a separate follow-on plan that consumes this foundation.

---

## File Structure

- `tests/run.php` — **create** — zero-dep test runner; discovers and runs `tests/*_test.php`.
- `tests/helpers.php` — **create** — `assert_eq`, `assert_true`, `it()` helpers + counters.
- `includes/apps.php` — **create** — `wrd_apps()` registry + `wrd_app($key)` accessor (pure, no DB).
- `includes/app_context.php` — **create** — `set_app_context()`, `app_ctx()`, `app_accent()`, `app_nav()`, `app_roles()`.
- `includes/auth.php` — **modify** — add pure `role_allowed_in_app()`; scope `switch_to_role()` to current app.
- `includes/sidebar.php` — **modify** — replace integrated `render_sidebar()` with context-driven `render_app_sidebar()` + pure `app_sidebar_items()`.
- `includes/header.php` — **modify** — theme masthead/title from app-context; accent CSS var; dashboard link → app home.
- `includes/i18n.php` — **modify** — add launcher strings.
- `assets/css/app.css` — **modify** — add accent-token design-system utilities (`var(--acc)`).
- `index.php` — **rewrite** — WRD Project Suite launcher from the registry (no aggregate stats).
- `api/ppms_stats.php` — **delete** — cross-feed removed.

---

## Task 1: Zero-dependency test harness

**Files:**
- Create: `tests/helpers.php`
- Create: `tests/run.php`

- [ ] **Step 1: Write the test helpers**

Create `tests/helpers.php`:

```php
<?php
// Minimal zero-dependency test harness (no Composer needed).
declare(strict_types=1);

$GLOBALS['__tests'] = ['pass' => 0, 'fail' => 0, 'fails' => []];

function it(string $name, callable $fn): void {
    try {
        $fn();
        $GLOBALS['__tests']['pass']++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__tests']['fails'][] = "$name — " . $e->getMessage();
        echo "  \033[31m✗\033[0m $name — " . $e->getMessage() . "\n";
    }
}

function assert_true($cond, string $msg = 'expected true'): void {
    if ($cond !== true) throw new Exception($msg);
}

function assert_eq($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $e = var_export($expected, true); $a = var_export($actual, true);
        throw new Exception(($msg ? "$msg: " : '') . "expected $e, got $a");
    }
}
```

- [ ] **Step 2: Write the runner**

Create `tests/run.php`:

```php
<?php
// Usage: php tests/run.php
declare(strict_types=1);
require __DIR__ . '/helpers.php';

foreach (glob(__DIR__ . '/*_test.php') as $file) {
    echo "\n" . basename($file) . "\n";
    require $file;
}

$t = $GLOBALS['__tests'];
echo "\n" . str_repeat('-', 40) . "\n";
echo "Passed: {$t['pass']}  Failed: {$t['fail']}\n";
if ($t['fail'] > 0) { foreach ($t['fails'] as $f) echo "  FAIL: $f\n"; exit(1); }
exit(0);
```

- [ ] **Step 3: Verify the harness runs with no tests**

Run: `php tests/run.php`
Expected: prints `Passed: 0  Failed: 0` and exits 0.

- [ ] **Step 4: Commit**

```bash
git add tests/helpers.php tests/run.php
git commit -m "test: add zero-dependency PHP test harness"
```

---

## Task 2: App registry

**Files:**
- Create: `includes/apps.php`
- Test: `tests/apps_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/apps_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/apps.php';

it('registry has exactly the five products', function () {
    assert_eq(['ppms','contractor','allocation','etariff','website'], array_keys(wrd_apps()));
});

it('each product has the required fields', function () {
    foreach (wrd_apps() as $key => $a) {
        foreach (['key','short','name','name_hi','accent','icon','tagline','tagline_hi','home','roles','nav'] as $f) {
            assert_true(array_key_exists($f, $a), "$key missing field $f");
        }
        assert_eq($key, $a['key'], "key field must match registry key for $key");
        assert_true(is_array($a['roles']) && count($a['roles']) > 0, "$key needs roles");
        assert_true(is_array($a['nav'])   && count($a['nav'])   > 0, "$key needs nav");
        assert_true((bool)preg_match('/^#[0-9a-f]{6}$/i', $a['accent']), "$key accent must be hex");
    }
});

it('wrd_app returns one product or null', function () {
    assert_eq('PPMS', wrd_app('ppms')['short']);
    assert_eq(null, wrd_app('nope'));
});

it('ppms roles match the spec', function () {
    assert_eq(['JE','AE','EE','SE','EIC','FINANCE','SECRETARY'], wrd_app('ppms')['roles']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — "Call to undefined function wrd_apps()".

- [ ] **Step 3: Write the registry**

Create `includes/apps.php`:

```php
<?php
declare(strict_types=1);

/**
 * Registry of the five INDEPENDENT WRD products.
 * Pure data — no DB, no session. Each product is self-contained.
 * `home`/nav `url` values are app-root-relative (pass through base_url() at render).
 */
function wrd_apps(): array {
    return [
        'ppms' => [
            'key' => 'ppms', 'short' => 'PPMS',
            'name' => 'Project Progress Monitoring', 'name_hi' => 'परियोजना प्रगति निगरानी',
            'accent' => '#0e7c86', 'icon' => '📊',
            'tagline' => 'Physical & financial tracking, fund flow, GIS & MIS.',
            'tagline_hi' => 'भौतिक एवं वित्तीय प्रगति, निधि प्रवाह, जीआईएस एवं एमआईएस।',
            'home' => 'app/ppms/index.php',
            'roles' => ['JE','AE','EE','SE','EIC','FINANCE','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Command Centre','url'=>'app/ppms/index.php','icon'=>'▤'],
                ['key'=>'requisitions','label'=>'Fund Requisition','url'=>'app/ppms/requisitions.php','icon'=>'₹'],
                ['key'=>'reports','label'=>'Reports / MIS','url'=>'app/ppms/reports.php','icon'=>'▦'],
            ],
        ],
        'contractor' => [
            'key' => 'contractor', 'short' => 'Contractor Reg.',
            'name' => 'Contractor Registration & Empanelment', 'name_hi' => 'ठेकेदार पंजीकरण एवं सूचीयन',
            'accent' => '#2563eb', 'icon' => '⚒️',
            'tagline' => 'Online empanelment, verification, e-certificate with QR.',
            'tagline_hi' => 'ऑनलाइन सूचीयन, सत्यापन, क्यूआर सहित ई-प्रमाणपत्र।',
            'home' => 'app/contractor/index.php',
            'roles' => ['CONTRACTOR','ASO','AE','EE','EIC'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Registry Desk','url'=>'app/contractor/index.php','icon'=>'▤'],
                ['key'=>'verify','label'=>'Verify Certificate','url'=>'app/contractor/verify.php','icon'=>'✔'],
            ],
        ],
        'allocation' => [
            'key' => 'allocation', 'short' => 'Allocation',
            'name' => 'Industrial Water Allocation', 'name_hi' => 'औद्योगिक जल आवंटन',
            'accent' => '#0891b2', 'icon' => '💧',
            'tagline' => 'Apply, technical scrutiny, approval & licence issuance.',
            'tagline_hi' => 'आवेदन, तकनीकी जाँच, अनुमोदन एवं लाइसेंस जारी।',
            'home' => 'app/allocation/index.php',
            'roles' => ['CONSUMER','AE','EE','CE','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Allocation Desk','url'=>'app/allocation/index.php','icon'=>'▤'],
            ],
        ],
        'etariff' => [
            'key' => 'etariff', 'short' => 'E-Tariff',
            'name' => 'Water E-Tariff & Billing', 'name_hi' => 'जल ई-टैरिफ एवं बिलिंग',
            'accent' => '#059669', 'icon' => '🧾',
            'tagline' => 'Drawal, slab billing, online payment & revenue MIS.',
            'tagline_hi' => 'जल आहरण, स्लैब बिलिंग, ऑनलाइन भुगतान एवं राजस्व एमआईएस।',
            'home' => 'app/etariff/index.php',
            'roles' => ['CONSUMER','JE','AE','EE','ACCOUNTS','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Billing Desk','url'=>'app/etariff/index.php','icon'=>'▤'],
            ],
        ],
        'website' => [
            'key' => 'website', 'short' => 'Website + CMS',
            'name' => 'Departmental Website + CMS', 'name_hi' => 'विभागीय वेबसाइट एवं सीएमएस',
            'accent' => '#4f46e5', 'icon' => '🏛️',
            'tagline' => 'Bilingual public site, notices, RTI, grievance & admin CMS.',
            'tagline_hi' => 'द्विभाषी सार्वजनिक वेबसाइट, सूचनाएँ, आरटीआई, शिकायत एवं सीएमएस।',
            'home' => 'public/home.php',
            'roles' => ['CITIZEN','EDITOR','ADMIN'],
            'nav' => [
                ['key'=>'dashboard','label'=>'CMS Admin','url'=>'app/cms/index.php','icon'=>'✎'],
            ],
        ],
    ];
}

/** One product by key, or null. */
function wrd_app(string $key): ?array {
    return wrd_apps()[$key] ?? null;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS (4 tests in apps_test.php).

- [ ] **Step 5: Commit**

```bash
git add includes/apps.php tests/apps_test.php
git commit -m "feat: add five-product app registry"
```

---

## Task 3: App-context layer

**Files:**
- Create: `includes/app_context.php`
- Test: `tests/app_context_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/app_context_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/app_context.php';

it('set_app_context returns true for a known product and exposes it', function () {
    assert_true(set_app_context('ppms'));
    assert_eq('ppms', app_ctx()['key']);
    assert_eq('#0e7c86', app_accent());
    assert_eq(['JE','AE','EE','SE','EIC','FINANCE','SECRETARY'], app_roles());
    assert_eq('dashboard', app_nav()[0]['key']);
});

it('set_app_context returns false for an unknown product and clears context', function () {
    set_app_context('ppms');
    assert_true(set_app_context('nope') === false);
    assert_eq(null, app_ctx());
});

it('accent falls back to brand teal when no context is set', function () {
    set_app_context('nope');           // clears
    assert_eq('#0E7C86', app_accent());
    assert_eq([], app_roles());
    assert_eq([], app_nav());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — "Call to undefined function set_app_context()".

- [ ] **Step 3: Write the context layer**

Create `includes/app_context.php`:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/apps.php';

/** Default brand accent used by the launcher and any non-product page. */
const WRD_DEFAULT_ACCENT = '#0E7C86';

/**
 * Declare which product the current page belongs to.
 * Returns true if the key is known (context set), false otherwise (context cleared).
 */
function set_app_context(string $key): bool {
    $app = wrd_app($key);
    $GLOBALS['APP_CTX'] = $app;   // array or null
    return $app !== null;
}

function app_ctx(): ?array { return $GLOBALS['APP_CTX'] ?? null; }

function app_accent(): string { return app_ctx()['accent'] ?? WRD_DEFAULT_ACCENT; }

function app_roles(): array { return app_ctx()['roles'] ?? []; }

function app_nav(): array { return app_ctx()['nav'] ?? []; }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/app_context.php tests/app_context_test.php
git commit -m "feat: add per-product app-context layer"
```

---

## Task 4: Role scoping in auth

**Files:**
- Modify: `includes/auth.php`
- Test: `tests/auth_scope_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/auth_scope_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/apps.php';
require_once __DIR__ . '/../includes/auth_roles.php';

it('a role inside a product is allowed', function () {
    assert_true(role_allowed_in_app('JE', 'ppms'));
    assert_true(role_allowed_in_app('CONSUMER', 'etariff'));
});

it('a role outside a product is rejected', function () {
    assert_true(role_allowed_in_app('CONSUMER', 'ppms') === false);
    assert_true(role_allowed_in_app('JE', 'website') === false);
});

it('an unknown product rejects every role', function () {
    assert_true(role_allowed_in_app('JE', 'nope') === false);
});
```

> **Note:** the pure role-check lives in its own file `includes/auth_roles.php` so it can be unit-tested without booting sessions/DB. `auth.php` requires it.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — `require_once` of `includes/auth_roles.php` fails (file does not exist).

- [ ] **Step 3: Create the pure role helper**

Create `includes/auth_roles.php`:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/apps.php';

/** Pure check: is $role one of $appKey's stakeholder roles? */
function role_allowed_in_app(string $role, string $appKey): bool {
    $app = wrd_app($appKey);
    return $app ? in_array($role, $app['roles'], true) : false;
}
```

- [ ] **Step 4: Wire it into auth.php and scope the demo role switch**

In `includes/auth.php`, after line 3 (`require_once __DIR__ . '/../config/db.php';`) add:

```php
require_once __DIR__ . '/auth_roles.php';
require_once __DIR__ . '/app_context.php';
```

Then replace the existing `switch_to_role()` function (lines 21-28) with:

```php
/** Quick role switch for the demo (no password). Scoped to the active product's roles. */
function switch_to_role(string $role): bool {
    $ctx = app_ctx();
    if ($ctx && !role_allowed_in_app($role, $ctx['key'])) return false;
    $st = db()->prepare('SELECT * FROM users WHERE role = ? LIMIT 1');
    $st->execute([$role]);
    $u = $st->fetch();
    if ($u) { unset($u['password_hash']); $_SESSION['user'] = $u; return true; }
    return false;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/auth_roles.php includes/auth.php tests/auth_scope_test.php
git commit -m "feat: scope role switching to the active product"
```

---

## Task 5: Context-driven sidebar

**Files:**
- Modify: `includes/sidebar.php`
- Test: `tests/sidebar_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/sidebar_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/app_context.php';
require_once __DIR__ . '/../includes/sidebar.php';

it('sidebar items come from the active product, not a global menu', function () {
    set_app_context('ppms');
    $items = app_sidebar_items();
    assert_eq(['dashboard','requisitions','reports'], array_column($items, 'key'));
});

it('sidebar items are empty when no product context is set', function () {
    set_app_context('nope');
    assert_eq([], app_sidebar_items());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — "Call to undefined function app_sidebar_items()".

- [ ] **Step 3: Rewrite sidebar.php**

Replace the **entire contents** of `includes/sidebar.php` with:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/app_context.php';

/** Pure: nav items for the active product (testable without rendering). */
function app_sidebar_items(): array {
    return app_nav();
}

/** Render the themed, per-product sidebar. */
function render_app_sidebar(string $active): void {
    $ctx = app_ctx();
    if (!$ctx) return;
    $acc   = $ctx['accent'];
    $items = app_sidebar_items();
    $roles = $ctx['roles'];
    $cur   = function_exists('user_role') ? user_role() : null;
    ?>
    <aside class="hidden lg:flex flex-col w-64 shrink-0 text-white px-3 py-5 gap-1"
           style="background:#0a263d">
      <div class="px-2 pb-3 mb-2 border-b border-white/10 flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg grid place-items-center text-lg"
              style="background:<?= e($acc) ?>1f;color:<?= e($acc) ?>"><?= $ctx['icon'] ?></span>
        <div>
          <p class="text-sm font-semibold leading-tight"><?= e($ctx['short']) ?></p>
          <p class="text-[11px] text-slate-400"><?= is_hi() ? e($ctx['name_hi']) : e($ctx['name']) ?></p>
        </div>
      </div>
      <?php foreach ($items as $it): ?>
        <a href="<?= base_url($it['url']) ?>"
           class="nav-link <?= $active===$it['key']?'active':'' ?>"
           style="<?= $active===$it['key'] ? '--acc:'.e($acc).';' : '' ?>">
          <span class="w-5 text-center text-base"><?= $it['icon'] ?></span><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>

      <div class="mt-auto pt-4 border-t border-white/10">
        <label class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold px-2">Demo · Switch Role</label>
        <select onchange="if(this.value)location.href='<?= base_url('auth/role_switch.php') ?>?role='+this.value"
                class="mt-1.5 w-full bg-ink2 border border-white/15 text-slate-100 text-sm rounded-lg px-2 py-2 focus:outline-none">
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r) ?>" <?= $cur===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-slate-400 mt-2 px-2"><?= is_hi()?'इस उत्पाद की भूमिकाओं के बीच स्विच करें।':'Switch across this product\'s roles during the demo.' ?></p>
      </div>
    </aside>
    <?php
}
```

- [ ] **Step 4: Update the caller in header.php**

In `includes/header.php`, line 134, replace `<?php render_sidebar($ACTIVE); ?>` with `<?php render_app_sidebar($ACTIVE); ?>`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/sidebar.php includes/header.php tests/sidebar_test.php
git commit -m "feat: per-product themed sidebar from app context"
```

---

## Task 6: Accent theming + dashboard link in header

**Files:**
- Modify: `includes/header.php`

- [ ] **Step 1: Require the context layer in the header**

In `includes/header.php`, after line 5 (`require_once __DIR__ . '/auth.php';`) add:

```php
require_once __DIR__ . '/app_context.php';
$APP = app_ctx();
$ACCENT = app_accent();
```

- [ ] **Step 2: Expose the accent as a CSS variable on `<body>`**

In `includes/header.php`, replace line 47:

```php
<body class="min-h-screen flex flex-col">
```

with:

```php
<body class="min-h-screen flex flex-col" style="--acc: <?= e($ACCENT) ?>">
```

- [ ] **Step 3: Title + masthead subtitle reflect the active product**

In `includes/header.php`, replace line 26:

```php
<title><?= $PAGE_TITLE ? e($PAGE_TITLE).' · ' : '' ?>WRD Jharkhand</title>
```

with:

```php
<title><?= $PAGE_TITLE ? e($PAGE_TITLE).' · ' : '' ?><?= $APP ? e($APP['short']).' · ' : '' ?>WRD Jharkhand</title>
```

And replace line 87:

```php
<div class="text-[11px] sm:text-xs text-slate-500"><?= t('govt') ?> · <?= t('tagline') ?></div>
```

with:

```php
<div class="text-[11px] sm:text-xs text-slate-500"><?= t('govt') ?> · <?= $APP ? (is_hi()?e($APP['name_hi']):e($APP['name'])) : t('tagline') ?></div>
```

- [ ] **Step 4: Point the masthead "Dashboard" button at the active product's home**

In `includes/header.php`, replace line 101:

```php
<a href="<?= base_url('app/dashboard.php') ?>" class="hidden sm:inline-flex items-center gap-1.5 bg-brand hover:bg-branddeep text-white text-sm font-semibold px-3.5 py-2 rounded-lg"><?= t('dashboard') ?></a>
```

with:

```php
<a href="<?= base_url($APP['home'] ?? 'index.php') ?>" class="hidden sm:inline-flex items-center gap-1.5 text-white text-sm font-semibold px-3.5 py-2 rounded-lg" style="background:<?= e($ACCENT) ?>"><?= t('dashboard') ?></a>
```

- [ ] **Step 5: Verify pages still render (smoke test)**

Run: `php -l includes/header.php`
Expected: `No syntax errors detected in includes/header.php`.

- [ ] **Step 6: Commit**

```bash
git add includes/header.php
git commit -m "feat: theme masthead from active product accent"
```

---

## Task 7: Accent design-system utilities

**Files:**
- Modify: `assets/css/app.css`

- [ ] **Step 1: Append accent-token utilities**

Append to the end of `assets/css/app.css`:

```css
/* ===== Per-product accent design-system tokens ===== */
:root { --acc: #0E7C86; }

/* Sidebar active item uses the product accent */
.nav-link.active {
  background: color-mix(in srgb, var(--acc) 18%, transparent);
  color: #fff;
  box-shadow: inset 3px 0 0 var(--acc);
}

/* Card with an accent top-border (launcher + dashboards) */
.acc-card { border-top: 4px solid var(--acc); }

/* KPI tile with an accent left-border */
.acc-kpi { border-left: 3px solid var(--acc); }

/* Accent-filled button */
.btn-acc { background: var(--acc); color: #fff; }
.btn-acc:hover { filter: brightness(.94); }

/* Soft accent chip */
.chip-acc { background: color-mix(in srgb, var(--acc) 14%, #fff); color: var(--acc); }
```

- [ ] **Step 2: Verify the file is valid CSS (no PHP needed)**

Run: `php -r "echo file_exists('assets/css/app.css') && strpos(file_get_contents('assets/css/app.css'), '--acc') !== false ? 'OK' : 'MISSING';"`
Expected: `OK`.

- [ ] **Step 3: Commit**

```bash
git add assets/css/app.css
git commit -m "feat: accent-token design-system utilities"
```

---

## Task 8: Launcher strings

**Files:**
- Modify: `includes/i18n.php`

- [ ] **Step 1: Add launcher strings**

In `includes/i18n.php`, inside the `$STRINGS` array (before the closing `];` on line 54), add:

```php
    'suite_name'    => ['en'=>'WRD Project Suite', 'hi'=>'जल संसाधन विभाग परियोजना सूट'],
    'suite_hero'    => ['en'=>'Transparent, secure, accountable water governance for Jharkhand', 'hi'=>'झारखंड हेतु पारदर्शी, सुरक्षित एवं उत्तरदायी जल शासन'],
    'suite_sub'     => ['en'=>'Select a product to explore its live demonstration. Each runs as an independent system with its own users, dashboards and workflows.', 'hi'=>'किसी उत्पाद का लाइव डेमो देखने हेतु चयन करें। प्रत्येक स्वतंत्र प्रणाली है — अपने उपयोगकर्ता, डैशबोर्ड एवं कार्यप्रवाह सहित।'],
    'open_demo'     => ['en'=>'Open demo', 'hi'=>'डेमो खोलें'],
    'five_products' => ['en'=>'Five digital products · one department', 'hi'=>'पाँच डिजिटल उत्पाद · एक विभाग'],
```

- [ ] **Step 2: Verify syntax**

Run: `php -l includes/i18n.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/i18n.php
git commit -m "feat: add launcher i18n strings"
```

---

## Task 9: WRD Project Suite launcher

**Files:**
- Rewrite: `index.php`

- [ ] **Step 1: Replace index.php with the launcher**

Replace the **entire contents** of `index.php` with:

```php
<?php
require_once __DIR__ . '/includes/header.php';   // $LAYOUT defaults to 'public'; no app context => default accent
require_once __DIR__ . '/includes/apps.php';
$apps = wrd_apps();
?>
<!-- ===== Suite hero ===== -->
<section class="text-white" style="background:
   radial-gradient(1200px 320px at 80% -10%, rgba(14,124,134,.30), transparent),
   linear-gradient(180deg,#0a2a44,#0c3350)">
  <div class="max-w-7xl mx-auto px-4 pt-14 pb-12">
    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/20 px-3 py-1 text-xs font-medium">
      ● <?= t('five_products') ?>
    </span>
    <h1 class="font-display font-semibold text-3xl sm:text-4xl lg:text-5xl leading-[1.1] mt-5 max-w-3xl"><?= t('suite_hero') ?></h1>
    <p class="text-cyan-100/90 text-base mt-4 max-w-2xl"><?= t('suite_sub') ?></p>
    <div class="flex flex-wrap gap-2 mt-7">
      <?php foreach (['GIGW 3.0','WCAG 2.1 AA','DPDP Act 2023','CERT-In','हिंदी / English'] as $b): ?>
        <span class="text-[11px] bg-white/8 ring-1 ring-white/18 rounded-lg px-2.5 py-1.5 text-cyan-100/90">✓ <?= e($b) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== Product cards ===== -->
<section class="max-w-7xl mx-auto px-4 py-12">
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($apps as $a): ?>
      <a href="<?= base_url($a['home']) ?>" class="card acc-card p-6 lift group" style="--acc:<?= e($a['accent']) ?>">
        <div class="w-12 h-12 rounded-xl grid place-items-center text-2xl mb-4 chip-acc"><?= $a['icon'] ?></div>
        <div class="font-display text-lg font-semibold text-ink group-hover:opacity-90"><?= is_hi()?e($a['name_hi']):e($a['name']) ?></div>
        <p class="text-sm text-slate-500 mt-1.5 leading-relaxed"><?= is_hi()?e($a['tagline_hi']):e($a['tagline']) ?></p>
        <div class="mt-4 text-sm font-semibold" style="color:<?= e($a['accent']) ?>"><?= t('open_demo') ?> →</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual visual verification**

Start Apache + MySQL, open `http://localhost/WRD/`.
Expected: the premium WRD Project Suite launcher renders with five accent product cards + compliance badges; no aggregate KPI numbers, no "Integrated Backbone" stats row. Hindi/English toggle and accessibility controls work.

- [ ] **Step 4: Commit**

```bash
git add index.php
git commit -m "feat: WRD Project Suite launcher replacing integrated landing"
```

---

## Task 10: Remove the cross-product feed

**Files:**
- Delete: `api/ppms_stats.php`

- [ ] **Step 1: Confirm nothing else references the feed**

Run: `grep -rn "ppms_stats" --include=*.php .`
Expected: only matches inside `api/ppms_stats.php` itself (the public website's fetch of it is being removed as part of the website product plan; if any caller remains, note it for that plan — do not wire it back up).

- [ ] **Step 2: Delete the feed**

Run: `git rm api/ppms_stats.php`

- [ ] **Step 3: Verify the suite still loads**

Open `http://localhost/WRD/` — launcher renders with no errors.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: remove cross-product PPMS stats feed"
```

---

## Task 11: Full suite verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0.

- [ ] **Step 2: Lint every touched PHP file**

Run: `for f in index.php includes/header.php includes/sidebar.php includes/auth.php includes/apps.php includes/app_context.php includes/auth_roles.php includes/i18n.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Push**

```bash
git push origin main
```

---

## Notes for follow-on product plans

- Each product plan sets context at the top of every page: `set_app_context('ppms');` (etc.) **before** requiring `includes/header.php`, and uses `$LAYOUT='app'` + `$ACTIVE='<nav key>'`.
- `app/dashboard.php` (the old aggregate Command Centre) is **replaced** by the PPMS plan with a PPMS-scoped `app/ppms/index.php`; remove the aggregate page there, not here.
- The website product plan adds `public/home.php` (the public site home referenced by the registry) and the CMS editor/approver split.
- New roles introduced by the registry (`ASO`, `ACCOUNTS`, `CITIZEN`, `EDITOR`) must be added to the seed `users` table in the relevant product plan so the demo role-switcher resolves them.
