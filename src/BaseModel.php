<?php
namespace PrintHub;

use PDO;

/**
 * BaseModel  -  the PARENT of every class that talks to a database table.
 *
 * OOP CONCEPTS
 *   ABSTRACTION : "abstract" means you cannot write new BaseModel(). It is only a blueprint.
 *                 Each child MUST say which table it uses (table() and primaryKey()).
 *   INHERITANCE : Service, Order, User... "extends BaseModel", so they all get
 *                 all(), find() and delete() for free (no copy-pasting).
 *   ENCAPSULATION: $db is protected: only this class and its children can use it.
 *
 * Note: table names are written by US in the code, never typed by a visitor,
 *       so it is safe to put them inside the SQL text. Visitor data ALWAYS goes
 *       through prepared-statement placeholders (:name) instead.
 */
abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    abstract protected function table(): string;       // children must provide
    abstract protected function primaryKey(): string;  // children must provide

    /** Get every row of the table. */
    public function all(): array
    {
        $sql = 'SELECT * FROM ' . $this->table() . ' ORDER BY ' . $this->primaryKey();
        return $this->db->query($sql)->fetchAll();
    }

    /** Get ONE row by its id, or null if it does not exist. */
    public function find(int $id): ?array
    {
        $sql  = 'SELECT * FROM ' . $this->table() . ' WHERE ' . $this->primaryKey() . ' = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Delete ONE row by its id. Returns true if a row was deleted. */
    public function delete(int $id): bool
    {
        $sql  = 'DELETE FROM ' . $this->table() . ' WHERE ' . $this->primaryKey() . ' = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
