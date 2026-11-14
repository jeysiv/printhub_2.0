<?php
namespace PrintHub;

/** Category  -  talks to the `categories` table. */
class Category extends BaseModel
{
    protected function table(): string      { return 'categories'; }
    protected function primaryKey(): string { return 'category_id'; }

    /** Return the id of a category name. If it does not exist yet, create it first. */
    public function findOrCreate(string $name): int
    {
        $stmt = $this->db->prepare('SELECT category_id FROM categories WHERE category_name = :name');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }

        $stmt = $this->db->prepare('INSERT INTO categories (category_name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int)$this->db->lastInsertId();
    }
}
