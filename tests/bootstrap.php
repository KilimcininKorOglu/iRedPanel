<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Set minimal env vars required for Settings singleton in tests
$_ENV['IREDPANEL_BACKEND'] = 'ldap';
$_ENV['IREDPANEL_SECRET_KEY'] = 'test-secret';
$_ENV['IREDPANEL_LDAP_URI'] = 'ldap://localhost';
$_ENV['IREDPANEL_LDAP_ROOT_DN'] = 'dc=test,dc=com';
$_ENV['IREDPANEL_LDAP_USER'] = 'admin@test.com';
$_ENV['IREDPANEL_LDAP_PASSWORD'] = 'test';
