<?php

namespace Pickem;

require_once __DIR__ . '/Env.php';

class Database
{
    private static ?\PDO $pdo = null;

    public static function connect(): \PDO
    {
        if (self::$pdo === null) {
            Env::load();
            $host = Env::get('DB_HOST', '127.0.0.1');
            $name = Env::get('DB_NAME', 'pickem');
            $user = Env::get('DB_USER', 'pickem');
            $pass = Env::get('DB_PASS', '');
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
}
