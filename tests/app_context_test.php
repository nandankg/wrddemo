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
