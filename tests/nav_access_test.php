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
    assert_true(app_can_access('not-a-nav-key', 'CONTRACTOR'));
});
