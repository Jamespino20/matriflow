<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::logout();
redirect('/?logged_out=1');
