<?php
namespace PrintHub;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database  -  opens ONE connection to MySQL and shares it with the whole site.
 *
 * OOP CONCEPT: ENCAPSULATION
 *   - $connection is PRIVATE: no other file can touch it directly.
 *   - The constructor is PRIVATE: nobody can write "new Database()".
 *     The only way in is the public method Database::connect().
 */
class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
        // intentionally empty: blocks "new Database()"
    }

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors become exceptions
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
                    PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
                ]);
            } catch (PDOException $e) {
                // Do not leak the password or host to visitors: throw a simpler error instead.
                throw new RuntimeException('Could not connect to the database. Is MySQL running and is printhub imported?');
            }
        }
        return self::$connection;
    }
}
