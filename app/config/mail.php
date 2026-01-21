<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMTP Configuration
    |-------------------------------------------------------------------------
    |
    |

    // Default to Mailtrap (Development)
    // To use Gmail, comment these out and uncomment the Gmail section below.

    /*'smtp_host'  => 'sandbox.smtp.mailtrap.io',
    'smtp_port'    => 2525,
    'smtp_user'    => 'fea6bb39e5ea6b',
    'smtp_pass'    => '28db8bb3ce7cbd',
    */

    //GMAIL EXAMPLE:
    'smtp_host'    => 'smtp.gmail.com',
    'smtp_port'    => 587,
    'smtp_user'    => 'matriflow.chmc@gmail.com',
    'smtp_pass'    => 'rwvu jzds fpwg fqht', // Generate at myaccount.google.com/apppasswords


    'from_email'   => 'noreply@matriflow.infinityfreeapp.com',
    'from_name'    => 'MatriFlow - CHMC Maternal Health System',

    'debug'        => true, // Set to false in production
];
