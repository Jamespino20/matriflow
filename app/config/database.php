<?php

declare(strict_types=1);

function db(): PDO
{
    return Database::getInstance();
}
