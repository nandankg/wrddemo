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
