<?php
namespace PrintHub;

use PDOException;

/**
 * Service  -  talks to the `services` table (the things PrintHub sells).
 * It gets all(), find() from BaseModel and adds its own methods.
 */
class Service extends BaseModel
{
    protected function table(): string      { return 'services'; }
    protected function primaryKey(): string { return 'service_id'; }

    // A JOIN: each service row plus the name of its category (from the categories table)
    private const SELECT = 'SELECT s.*, c.category_name
                            FROM services s
                            JOIN categories c ON c.category_id = s.category_id';

    /** Every service (active and inactive). Used by the admin. */
    public function allWithCategory(): array
    {
        return $this->db->query(self::SELECT . ' ORDER BY c.category_name, s.service_name')->fetchAll();
    }

    /** Only services customers may order. */
    public function allActive(): array
    {
        return $this->db->query(
            self::SELECT . " WHERE s.status = 'active' ORDER BY c.category_name, s.service_name"
        )->fetchAll();
    }

    /** The first few active services, for the front page. */
    public function featured(int $limit): array
    {
        return $this->db->query(
            self::SELECT . " WHERE s.status = 'active' ORDER BY s.service_id LIMIT " . (int)$limit
        )->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE service_id = :id AND status = 'active'");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Add a new service ($id = null) or change an existing one.
     * $data keys: category_id, service_name, description, price, unit, status
     */
    public function save(array $data, ?int $id = null): void
    {
        if ($id === null) {
            $sql = 'INSERT INTO services (category_id, service_name, description, price, unit, status)
                    VALUES (:category_id, :service_name, :description, :price, :unit, :status)';
            $this->db->prepare($sql)->execute($data);
        } else {
            $sql = 'UPDATE services
                    SET category_id = :category_id, service_name = :service_name, description = :description,
                        price = :price, unit = :unit, status = :status
                    WHERE service_id = :id';
            $data['id'] = $id;
            $this->db->prepare($sql)->execute($data);
        }
    }

    /** Switch between active and inactive. */
    public function toggleStatus(int $id): void
    {
        $sql = "UPDATE services SET status = IF(status = 'active', 'inactive', 'active') WHERE service_id = :id";
        $this->db->prepare($sql)->execute(['id' => $id]);
    }

    /**
     * OVERRIDES BaseModel::delete().
     * A service that was already ordered is protected by a FOREIGN KEY, so MySQL refuses to delete it.
     * We catch that database error and turn it into a clear message.
     */
    public function delete(int $id): bool
    {
        try {
            return parent::delete($id); // run the normal delete from BaseModel
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // 23000 = "integrity constraint violation"
                throw new ValidationException(['This service is used in existing orders, so it cannot be deleted. Set it to inactive instead.']);
            }
            throw $e;
        }
    }
}
