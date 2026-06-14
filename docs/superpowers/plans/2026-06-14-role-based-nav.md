# Role-Based Sidebar & Page Access Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make each product's sidebar and pages role-aware so external users (e.g. CONTRACTOR, CONSUMER) see only their applicant surface and internal officers see only what their role needs — per RFP §10.4 (role-based access) and the applicant-vs-officer separation in §8/§9.

**Architecture:** Each registry nav item may carry a `roles` allow-list. A pure, tested filter hides nav items the current role can't use; a page-access guard redirects a role to its product home if it opens a page it shouldn't. No nav `roles` key = visible to all the product's roles (unchanged behaviour for unrestricted items).

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), the zero-dependency test runner at `tests/run.php`.

**Prerequisite:** Foundation + PPMS + E-Tariff + Contractor are on `main`. This change branches off `main`.

---

## Role → nav map (what this enforces)

| Product | Nav item | Allowed roles |
|---|---|---|
| PPMS | dashboard, projects, reports | all PPMS roles |
| PPMS | **requisitions** | EE, SE, EIC, FINANCE, SECRETARY (not JE/AE) |
| Contractor | dashboard | all (role-adaptive: portal vs desk) |
| Contractor | **applications, registry, verify** | ASO, AE, EE, EIC (not CONTRACTOR) |
| E-Tariff | dashboard, bills | all (both already scoped to the consumer) |

External users therefore see: Contractor `CONTRACTOR` → only **dashboard**; E-Tariff `CONSUMER` → dashboard + bills (their own).

---

## File Structure

- `includes/app_context.php` — **modify** — add pure `nav_role_ok`, `app_nav_visible`, `app_can_access`, plus the page guard `app_require_access`.
- `includes/apps.php` — **modify** — tag restricted nav items with `roles`.
- `includes/sidebar.php` — **modify** — `render_app_sidebar` renders only role-visible nav items.
- `tests/nav_access_test.php` — **create** — tests for the pure functions (pass once helpers + tags both land in Task 1).
- `app/ppms/requisitions.php` — **modify** — guard with `app_require_access('requisitions')`.
- `app/contractor/applications.php` — **modify** — guard with `app_require_access('applications')`.
- `app/contractor/registry.php` — **modify** — replace the bespoke contractor redirect with `app_require_access('registry')`.

---

## Task 1: Role-aware nav core (helpers + role tags + sidebar)

This task lands the failing test, the helper functions, the registry `roles` tags, and the sidebar wiring together so the suite is green at the end.

**Files:**
- Modify: `includes/app_context.php`
- Modify: `includes/apps.php`
- Modify: `includes/sidebar.php`
- Test: `tests/nav_access_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/nav_access_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/app_context.php';

it('nav_role_ok: no restriction is visible to everyone', function () {
    assert_true(nav_role_ok(null, 'ANYONE'));
    assert_true(nav_role_ok([], 'ANYONE'));
    assert_true(nav_role_ok(null, null));
});

it('nav_role_ok: restricted item checks membership', function () {
    assert_true(nav_role_ok(['EE','SE'], 'EE'));
    assert_true(nav_role_ok(['EE','SE'], 'JE') === false);
    assert_true(nav_role_ok(['EE'], null) === false);
});

it('app_nav_visible hides back-office nav from a contractor', function () {
    set_app_context('contractor');
    assert_eq(['dashboard'], array_column(app_nav_visible(app_nav(), 'CONTRACTOR'), 'key'));
});

it('app_nav_visible shows the full desk nav to an ASO', function () {
    set_app_context('contractor');
    assert_eq(['dashboard','applications','registry','verify'], array_column(app_nav_visible(app_nav(), 'ASO'), 'key'));
});

it('app_nav_visible hides PPMS fund requisition from JE but shows it to EE', function () {
    set_app_context('ppms');
    $je = array_column(app_nav_visible(app_nav(), 'JE'), 'key');
    assert_true(!in_array('requisitions', $je, true));
    $ee = array_column(app_nav_visible(app_nav(), 'EE'), 'key');
    assert_true(in_array('requisitions', $ee, true));
});

it('app_can_access gates pages by nav role', function () {
    set_app_context('contractor');
    assert_true(app_can_access('dashboard', 'CONTRACTOR'));
    assert_true(app_can_access('applications', 'CONTRACTOR') === false);
    assert_true(app_can_access('applications', 'ASO'));
    assert_true(app_can_access('not-a-nav-key', 'CONTRACTOR')); // unknown keys are allowed
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — "Call to undefined function nav_role_ok()".

- [ ] **Step 3: Add the helpers to app_context.php**

In `includes/app_context.php`, append these functions at the end of the file (after `app_nav()`):

```php
/** Is a nav item visible to $role? Empty/absent allow-list means everyone. */
function nav_role_ok(?array $rolesAllowed, ?string $role): bool {
    if (empty($rolesAllowed)) return true;
    return $role !== null && in_array($role, $rolesAllowed, true);
}

/** Filter a nav array to the items $role may see. */
function app_nav_visible(array $nav, ?string $role): array {
    return array_values(array_filter($nav, fn($it) => nav_role_ok($it['roles'] ?? null, $role)));
}

/** Can $role open the page identified by $navKey in the active product? Unknown keys are allowed. */
function app_can_access(string $navKey, ?string $role): bool {
    foreach (app_nav() as $it) {
        if ($it['key'] === $navKey) return nav_role_ok($it['roles'] ?? null, $role);
    }
    return true;
}

/** Page guard: bounce $role to the product home if it cannot access $navKey. */
function app_require_access(string $navKey): void {
    $role = function_exists('user_role') ? user_role() : null;
    if (!app_can_access($navKey, $role)) {
        $home = app_ctx()['home'] ?? 'index.php';
        header('Location: ' . base_url($home)); exit;
    }
}
```

- [ ] **Step 4: Tag restricted nav items in apps.php**

In `includes/apps.php`, in the `'ppms'` entry's `'nav'` array, replace the `requisitions` item with (adds a `roles` key):

```php
                ['key'=>'requisitions','label'=>'Fund Requisition','url'=>'app/ppms/requisitions.php','icon'=>'₹','roles'=>['EE','SE','EIC','FINANCE','SECRETARY']],
```

In `includes/apps.php`, in the `'contractor'` entry's `'nav'` array, replace the `applications`, `registry`, and `verify` items with:

```php
                ['key'=>'applications','label'=>'Applications','url'=>'app/contractor/applications.php','icon'=>'📋','roles'=>['ASO','AE','EE','EIC']],
                ['key'=>'registry','label'=>'Registered Contractors','url'=>'app/contractor/registry.php','icon'=>'📒','roles'=>['ASO','AE','EE','EIC']],
                ['key'=>'verify','label'=>'Verify Certificate','url'=>'app/contractor/verify.php','icon'=>'✔','roles'=>['ASO','AE','EE','EIC']],
```

(The `dashboard` items and the entire E-Tariff nav stay unrestricted.)

- [ ] **Step 5: Wire the sidebar to filter by role**

In `includes/sidebar.php`, inside `render_app_sidebar`, find:

```php
    $acc   = $ctx['accent'];
    $items = app_sidebar_items();
    $roles = $ctx['roles'];
    $cur   = function_exists('user_role') ? user_role() : null;
```

Replace with:

```php
    $acc   = $ctx['accent'];
    $roles = $ctx['roles'];
    $cur   = function_exists('user_role') ? user_role() : null;
    $items = app_nav_visible(app_sidebar_items(), $cur);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS — all `nav_access_test.php` cases plus all pre-existing tests (the `apps_test.php` nav-order tests use `array_column(...,'key')` and are unaffected by the new `roles` key).

- [ ] **Step 7: Verify syntax**

Run: `php -l includes/app_context.php && php -l includes/apps.php && php -l includes/sidebar.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 8: Commit**

```bash
git add includes/app_context.php includes/apps.php includes/sidebar.php tests/nav_access_test.php
git commit -m "feat(nav): role-based nav visibility + page-access helpers; sidebar filters by role"
```

---

## Task 2: Page-access guards on restricted pages

**Files:**
- Modify: `app/ppms/requisitions.php`
- Modify: `app/contractor/applications.php`
- Modify: `app/contractor/registry.php`

- [ ] **Step 1: Guard the PPMS fund-requisition page**

In `app/ppms/requisitions.php`, find:

```php
set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='requisitions'; $PAGE_TITLE='Fund Requisition';
```

Replace with:

```php
set_app_context('ppms');
app_require_access('requisitions');
$LAYOUT='app'; $ACTIVE='requisitions'; $PAGE_TITLE='Fund Requisition';
```

- [ ] **Step 2: Guard the Contractor applications page**

In `app/contractor/applications.php`, find:

```php
set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
```

Replace with:

```php
set_app_context('contractor');
app_require_access('applications');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
```

- [ ] **Step 3: Replace the bespoke contractor redirect in registry.php with the standard guard**

In `app/contractor/registry.php`, find:

```php
contractor_require_login();
// Back-office only: the register (with PAN/GST) is not exposed to contractor logins.
if (contractor_role_view(user_role()) === 'contractor') { header('Location: ' . base_url('app/contractor/index.php')); exit; }
$pdo=db();
$contractors=$pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='registry'; $PAGE_TITLE='Registered Contractors';
```

Replace with:

```php
contractor_require_login();
$pdo=db();
$contractors=$pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();

set_app_context('contractor');
app_require_access('registry');
$LAYOUT='app'; $ACTIVE='registry'; $PAGE_TITLE='Registered Contractors';
```

- [ ] **Step 4: Verify syntax + suite**

Run: `php -l app/ppms/requisitions.php && php -l app/contractor/applications.php && php -l app/contractor/registry.php && php tests/run.php`
Expected: all lint clean; test suite passes.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/requisitions.php app/contractor/applications.php app/contractor/registry.php
git commit -m "feat(nav): enforce role page-access guards on restricted pages"
```

---

## Task 3: Full verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0.

- [ ] **Step 2: Lint every touched file**

Run: `for f in includes/app_context.php includes/sidebar.php includes/apps.php app/ppms/requisitions.php app/contractor/applications.php app/contractor/registry.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Render-check the sidebar per role (Apache + MySQL, demo DB installed)**

Run:
```
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>11,'username'=>'contractor','name'=>'N','role'=>'CONTRACTOR']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/contractor/index.php'; \$h=ob_get_clean(); echo 'CONTRACTOR nav-links: '.substr_count(\$h,'class=\"nav-link').PHP_EOL;"
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>9,'username'=>'aso','name'=>'A','role'=>'ASO']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/contractor/index.php'; \$h=ob_get_clean(); echo 'ASO nav-links: '.substr_count(\$h,'class=\"nav-link').PHP_EOL;"
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>7,'username'=>'je','name'=>'J','role'=>'JE','division_id'=>1]; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/ppms/index.php'; \$h=ob_get_clean(); echo 'PPMS JE has requisitions: '.(strpos(\$h,'app/ppms/requisitions.php')!==false?'yes(BAD)':'no(ok)').PHP_EOL;"
```
Expected: CONTRACTOR nav-links `1`; ASO nav-links `4`; PPMS JE has requisitions `no(ok)`.

- [ ] **Step 4: Verify the page guard blocks a direct URL hit**

Run:
```
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>11,'username'=>'contractor','name'=>'N','role'=>'CONTRACTOR']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/contractor/applications.php'; \$h=ob_get_clean(); echo 'contractor applications body chars: '.strlen(\$h).' (0 = redirected before render)'.PHP_EOL;"
```
Expected: `0` (guard `exit`s on redirect before any output).

- [ ] **Step 5: Push**

```bash
git push origin <current-branch>
```

---

## Notes

- The demo role-switcher dropdown in the sidebar still lists **all** of a product's roles (so a presenter can jump to any role); switching reloads the page and the nav re-filters for the new role.
- `verify.php` remains a public standalone page (reached by QR); only its *nav link* is restricted to back-office.
- Allocation & Website products (not yet built) must define `roles` on their officer-only nav items and call `app_require_access()` on officer-only pages, per this pattern.
- Separately noted (not in scope here): the RFP's contractor admin chain is ASO→SO→US→DS→JS→EIC; the demo uses a simplified ASO→AE→EE→EIC. Flag for a future fidelity pass if the board wants the full six-level chain.
