<?php
namespace PrintHub;

/** Promotion  -  talks to the `promotions` table (discounts). */
class Promotion extends BaseModel
{
    protected function table(): string      { return 'promotions'; }
    protected function primaryKey(): string { return 'promotion_id'; }

    // "Running now" = switched on AND today is between start_date and end_date
    private const RUNNING = "status = 'active' AND CURDATE() BETWEEN start_date AND end_date";

    public function currentlyActive(): array
    {
        return $this->db->query('SELECT * FROM promotions WHERE ' . self::RUNNING . ' ORDER BY end_date')->fetchAll();
    }

    public function findCurrentlyActive(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promotions WHERE promotion_id = :id AND ' . self::RUNNING);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** $data keys: title, description, discount, start_date, end_date, status */
    public function save(array $data, ?int $id = null): void
    {
        if ($id === null) {
            $sql = 'INSERT INTO promotions (title, description, discount, start_date, end_date, status)
                    VALUES (:title, :description, :discount, :start_date, :end_date, :status)';
            $this->db->prepare($sql)->execute($data);
        } else {
            $sql = 'UPDATE promotions
                    SET title = :title, description = :description, discount = :discount,
                        start_date = :start_date, end_date = :end_date, status = :status
                    WHERE promotion_id = :id';
            $data['id'] = $id;
            $this->db->prepare($sql)->execute($data);
        }
    }

    public function toggleStatus(int $id): void
    {
        $sql = "UPDATE promotions SET status = IF(status = 'active', 'inactive', 'active') WHERE promotion_id = :id";
        $this->db->prepare($sql)->execute(['id' => $id]);
    }
}
