<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            /*Online configs here
            $host = 'sql306.infinityfree.com';
            $dbname = 'if0_40602930_matriflow_db';
            $user = 'if0_40602930';
            $pass = 'lsteo4ShSHgYfo';
            */

            /*Offline configs here*/
            $host = 'localhost';
            $dbname = 'matriflow_db';
            $user = 'root';
            $pass = ''; // Standard XAMPP root password is empty. Change to 'Bryant0824!' if you set one.

            $port = 3307; // From my.cnf
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::$instance->exec("SET time_zone = '+08:00'");
        }

        return self::$instance;
    }
}
