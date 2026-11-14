<?php
/** admin/index.php  -  the admin dashboard: see every order and change its status. */
require __DIR__ . '/../includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Order;
use PrintHub\ValidationException;

Auth::requireAdmin();          // customers and visitors cannot enter
$orders = new Order();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $orderId = (int)($_POST['order_id'] ?? 0);

        if (($_POST['action'] ?? '') === 'status') {
            $orders->updateStatus($orderId, (string)($_POST['status'] ?? ''));          // UPDATE
            flash('success', 'Order #' . $orderId . ' updated.');
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $summary = $orders->findSummary($orderId);
            if ($summary === null || $summary['status'] !== 'cancelled') {
                throw new ValidationException(['Only cancelled orders can be deleted.']);
            }
            $orders->delete($orderId);                                                   // DELETE
            flash('success', 'Order #' . $orderId . ' deleted.');
        }
    } catch (ValidationException $e) {
        flash('error', implode(' ', $e->getErrors()));
    }
    redirect('admin/index.php');
}

$all    = $orders->allSummaries();
$counts = $orders->countByStatus();
$selectedStatus = (string)($_GET['status'] ?? 'all');
if ($selectedStatus !== 'all' && !in_array($selectedStatus, Order::STATUSES, true)) {
    $selectedStatus = 'all';
}
$visibleOrders = $selectedStatus === 'all'
    ? $all
    : array_values(array_filter($all, static fn(array $order): bool => $order['status'] === $selectedStatus));

$sales = 0;                                        // money earned from completed orders
foreach ($all as $o) {
    if ($o['status'] === 'completed') {
        $sales += (float)$o['total'];
    }
}

$pageTitle = 'Admin: Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="container section admin-dashboard">
    <h1 class="admin-page-title">Orders dashboard</h1>

    <div class="stats">
        <a class="stat stat-link<?= $selectedStatus === 'all' ? ' is-selected' : '' ?>" href="<?= e(url('admin/index.php?status=all')) ?>"<?= $selectedStatus === 'all' ? ' aria-current="page"' : '' ?>>
            <strong><?= count($all) ?></strong> All orders
        </a>
        <?php foreach (Order::STATUSES as $status): ?>
            <a class="stat stat-link<?= $selectedStatus === $status ? ' is-selected' : '' ?>" href="<?= e(url('admin/index.php?status=' . rawurlencode($status))) ?>"<?= $selectedStatus === $status ? ' aria-current="page"' : '' ?>>
                <strong><?= (int)($counts[$status] ?? 0) ?></strong> <?= e(ucfirst($status)) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($selectedStatus === 'all'): ?>
        <div class="card admin-sales-summary">Sales from completed orders: <strong><?= money($sales) ?></strong></div>
    <?php endif; ?>

    <?php if (!$visibleOrders): ?><p>No <?= $selectedStatus === 'all' ? 'orders' : e($selectedStatus) . ' orders' ?> to show.</p><?php else: ?>
    <div class="table-wrap">
        <table class="admin-orders-table">
            <caption class="muted"><?= $selectedStatus === 'all' ? 'All customer orders, newest first' : e(ucfirst($selectedStatus)) . ' orders, newest first' ?></caption>
            <thead>
                <tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($visibleOrders as $order): ?>
                <tr>
                    <td>#<?= (int)$order['order_id'] ?><br><span class="muted"><?= e(date('M j, Y', strtotime($order['order_date']))) ?></span></td>
                    <td><?= e($order['customer_name']) ?></td>
                    <td>
                        <ul class="item-list">
                        <?php foreach ($orders->items((int)$order['order_id']) as $item): ?>
                            <li>
                                <?= e($item['service_name']) ?> &times; <?= (int)$item['copies'] ?> (<?= e($item['paper_size']) ?>)
                                <?php if ($item['file_path']): ?>
                                    - <a href="<?= e(url('download.php?item_id=' . (int)$item['order_item_id'])) ?>"><?= e($item['file_name']) ?></a>
                                <?php elseif ($item['file_name']): ?>
                                    <span class="muted">- <?= e($item['file_name']) ?> (no file uploaded)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?><em>Note: <?= e($order['notes']) ?></em><?php endif; ?>
                    </td>
                    <td>
                        <?= money($order['total']) ?>
                        <?php if ($order['promo_title']): ?><br><span class="muted"><?= e($order['promo_title']) ?></span><?php endif; ?>
                    </td>
                    <td><span class="status status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                    <td class="admin-actions-cell">
                        <form method="post" class="admin-actions-form" action="<?= e(url('admin/index.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                            <label class="muted" for="status-<?= (int)$order['order_id'] ?>">Status</label>
                            <select id="status-<?= (int)$order['order_id'] ?>" name="status">
                                <?php foreach (Order::STATUSES as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-small" type="submit">Update</button>
                        </form>
                        <?php if ($order['status'] === 'cancelled'): ?>
                            <form method="post" class="admin-actions-form admin-delete-form" action="<?= e(url('admin/index.php')) ?>" onsubmit="return confirm('Delete this order permanently?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
