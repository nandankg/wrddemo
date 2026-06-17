<?php
require_once __DIR__ . '/../includes/leaders.php';

it('wrd_leaders returns the four dignitaries with all keys', function () {
    $ls = wrd_leaders();
    assert_eq(4, count($ls));
    foreach ($ls as $l) {
        foreach (['slug','name','name_hi','designation','designation_hi'] as $k) {
            assert_true(isset($l[$k]) && $l[$k] !== '', "missing $k");
        }
    }
});

it('wrd_leaders are in protocol order with expected slugs', function () {
    $slugs = array_map(fn($l) => $l['slug'], wrd_leaders());
    assert_eq(['hemant-soren','hafizul-hassan','prashant-kumar','joint-secretary'], $slugs);
});

it('wrd_leader_initials strips honorifics and takes two initials', function () {
    assert_eq('HS', wrd_leader_initials('Shri Hemant Soren'));
    assert_eq('HH', wrd_leader_initials('Shri Hafizul Hassan'));
    assert_eq('PK', wrd_leader_initials('Shri Prashant Kumar'));
    assert_eq('JS', wrd_leader_initials('Joint Secretary'));
});
