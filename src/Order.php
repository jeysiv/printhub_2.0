<?php
namespace PrintHub;

use PDO;
use Throwable;

/**
 * Order  -  talks to the `orders` table AND the `order_items` junction table.
 *
 * An order can hold MANY items (services), so creating an order touches two tables.
 * We use a TRANSACTION so both inserts succeed together or neither happens.
 */
class Order extends BaseModel
{
    public const STATUSES    = ['pending', 'confirmed', 'printing', 'ready', 'completed', 'cancelled'];
    public const PAPER_SIZES = ['A4', 'Short', 'Long', 'A3', 'Custom'];

    protected function table(): string      { return 'orders'; }
    protected function primaryKey(): string { return 'order_id'; }

    /**
     * One query that gives each order with its customer name, discount and TOTAL.
     * The total is CALCULATED here (SUM of copies x unit_price, minus the discount)
     * instead of being stored in a column. Stored totals can become wrong; calculated ones cannot.
     * $where lets callers narrow the list (all orders / one customer / one order).
     */
    private function summarySql(string $where): string
    {
        return "SELECT o.order_id, o.user_id, o.notes, o.status, o.order_date,
                       u.name AS customer_name,
                       p.title AS promo_title,
                       COALESCE(o.promotion_discount, 0) AS discount,
                       COALESCE(SUM(oi.copies * oi.unit_price), 0) AS subtotal,
                       ROUND(COALESCE(SUM(oi.copies * oi.unit_price), 0) * (1 - COALESCE(o.promotion_discount, 0) / 100), 2) AS total
                FROM orders o
                JOIN users u ON u.user_id = o.user_id
                LEFT JOIN promotions p ON p.promotion_id = o.promotion_id
                LEFT JOIN order_items oi ON oi.order_id = o.order_id
                $where
                GROUP BY o.order_id, o.user_id, o.notes, o.status, o.order_date, u.name, p.title, o.promotion_discount
                ORDER BY o.order_date DESC, o.order_id DESC";
    }

    /** All orders (admin). */
    public function allSummaries(): array
    {
        return $this->db->query($this->summarySql(''))->fetchAll();
    }

    /** All orders of ONE customer. */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare($this->summarySql('WHERE o.user_id = :uid'));
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** One order summary, or null. */
    public function findSummary(int $orderId): ?array
    {
        $stmt = $this->db->prepare($this->summarySql('WHERE o.order_id = :id'));
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** The items inside one order (JOIN with services to show the service name). */
    public function items(int $orderId): array
    {
        $sql = 'SELECT oi.*, s.service_name, (oi.copies * oi.unit_price) AS line_total
                FROM order_items oi
                JOIN services s ON s.service_id = oi.service_id
                WHERE oi.order_id = :id
                ORDER BY oi.order_item_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetchAll();
    }

    /** Find an order item and its owner for an authenticated file download. */
    public function findItemForDownload(int $itemId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.order_item_id, oi.order_id, oi.file_name, oi.file_path, o.user_id
             FROM order_items oi
             JOIN orders o ON o.order_id = oi.order_id
             WHERE oi.order_item_id = :id'
        );
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Create a NEW order with its first item.
     * $item keys: service_id, paper_size, copies, file_name, file_path
     * Returns the new order_id.
     */
    public function createWithItem(int $userId, ?int $promotionId, string $notes, array $item): int
    {
        $this->db->beginTransaction();          // start: "all or nothing"
        try {
            $promotion = $promotionId === null ? null : (new Promotion())->findCurrentlyActive($promotionId);
            if ($promotionId !== null && $promotion === null) {
                throw new ValidationException(['That promotion is not available right now.']);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO orders (user_id, promotion_id, promotion_discount, notes)
                 VALUES (:user_id, :promotion_id, :promotion_discount, :notes)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'promotion_id' => $promotionId,
                'promotion_discount' => $promotion === null ? 0 : $promotion['discount'],
                'notes' => $notes,
            ]);
            $orderId = (int)$this->db->lastInsertId();

            $this->insertItem($orderId, $item);

            $this->db->commit();                // both inserts worked: save them
            return $orderId;
        } catch (Throwable $e) {
            $this->db->rollBack();              // something failed: undo everything
            throw $e;                           // pass the problem to the page
        }
    }

    /** Add another item to an order that is still pending and belongs to this customer. */
    public function addItem(int $orderId, int $userId, array $item): void
    {
        $order = $this->find($orderId);
        if ($order === null || (int)$order['user_id'] !== $userId || $order['status'] !== 'pending') {
            throw new ValidationException(['You can only add items to your own pending orders.']);
        }
        $this->insertItem($orderId, $item);
    }

    /** Writes one row into the junction table. Private: only this class may call it. */
    private function insertItem(int $orderId, array $item): void
    {
        $service = (new Service())->findActive((int)$item['service_id']);
        if ($service === null) {
            throw new ValidationException(['That service is not available.']);
        }

        $sql = 'INSERT INTO order_items (order_id, service_id, file_name, file_path, paper_size, copies, unit_price)
                VALUES (:order_id, :service_id, :file_name, :file_path, :paper_size, :copies, :unit_price)';
        $this->db->prepare($sql)->execute([
            'order_id'   => $orderId,
            'service_id' => $service['service_id'],
            'file_name'  => $item['file_name'],
            'file_path'  => $item['file_path'],
            'paper_size' => $item['paper_size'],
            'copies'     => (int)$item['copies'],
            'unit_price' => $service['price'],   // copy today's price into the order (price history)
        ]);
    }

    /** Admin: change the status of an order. */
    public function updateStatus(int $orderId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException(['Invalid order status.']);
        }
        $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE order_id = :id');
        $stmt->execute(['status' => $status, 'id' => $orderId]);
    }

    /** Customer: cancel their own order, only while it is still pending. */
    public function cancel(int $orderId, int $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE orders SET status = 'cancelled'
             WHERE order_id = :id AND user_id = :uid AND status = 'pending'"
        );
        $stmt->execute(['id' => $orderId, 'uid' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new ValidationException(['Only your own pending orders can be cancelled.']);
        }
    }

    /** For the dashboard: how many orders per status, e.g. ['pending' => 2, 'completed' => 1]. */
    public function countByStatus(): array
    {
        return $this->db->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * OVERRIDES BaseModel::delete().
     * Besides deleting the order row (its items are removed automatically by ON DELETE CASCADE),
     * we also remove the uploaded files from the uploads folder.
     */
    public function delete(int $id): bool
    {
        $files   = array_column($this->items($id), 'file_path'); // remember file names first
        $deleted = parent::delete($id);                          // BaseModel's normal delete

        if ($deleted) {
            $uploader = new FileUploader(ROOT_PATH . '/uploads');
            foreach ($files as $path) {
                $uploader->delete($path);
            }
        }
        return $deleted;
    }
}
