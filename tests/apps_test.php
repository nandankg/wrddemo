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

it('ppms nav exposes dashboard, projects, requisitions, reports in order', function () {
    assert_eq(['dashboard','projects','requisitions','reports'], array_column(wrd_app('ppms')['nav'], 'key'));
});

it('etariff nav exposes dashboard and bills in order', function () {
    assert_eq(['dashboard','bills'], array_column(wrd_app('etariff')['nav'], 'key'));
});
