<?php

declare(strict_types=1);

const SESSION_NAME = 'matriflow_session';

const SESSION_IDLE_SECONDS = 20 * 60;      // 20 minutes
const SESSION_ABSOLUTE_SECONDS = 8 * 60 * 60; // 8 hours

const CSRF_SESSION_KEY = '_csrf_token';

const LOGIN_LOCK_AFTER = 5;
const LOGIN_LOCK_MINUTES = 15;

const INACTIVITY_FORCE_2FA_DAYS = 30;

const PASSWORD_MIN_LEN = 8;

// Set cookie params before session_start (SessionManager handles it)
return [
    'password_min_length'  => 8,
    'require_special_char' => true,
    'require_number'       => true,
    'require_uppercase'    => true,
];
