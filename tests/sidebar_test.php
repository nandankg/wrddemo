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
