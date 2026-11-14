<?php
/** my_orders.php  -  the customer's own orders, with items, totals and status. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Order;
use PrintHub\ValidationException;

$user = Auth::requireLogin();
if ($user->isAdmin()) {
    redirect($user->getHomePage());
}

$orders = new Order();

// A customer can cancel their own pending order (an UPDATE in the database)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $orders->cancel((int)($_POST['order_id'] ?? 0), $user->getId());
        flash('success', 'Order cancelled.');
    } catch (ValidationException $e) {
        flash('error', implode(' ', $e->getErrors()));
    }
    redirect('my_orders.php');   // redirect after POST so a refresh does not resubmit
}

$list      = $orders->forUser($user->getId());
$pendingOrders = array_values(array_filter($list, static fn(array $order): bool => $order['status'] === 'pending'));
$cancelledOrders = array_values(array_filter($list, static fn(array $order): bool => $order['status'] === 'cancelled'));
$otherOrders = array_values(array_filter($list, static fn(array $order): bool => !in_array($order['status'], ['pending', 'cancelled'], true)));
$orderedList = array_merge($pendingOrders, $otherOrders, $cancelledOrders);
$pageTitle = 'My Orders';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
    <h1>My orders</h1>
    <p><a class="btn btn-accent" href="<?= e(url('order.php')) ?>">Place a new order</a></p>

    <?php if (!$list): ?>
        <p>You have no orders yet.</p>
    <?php endif; ?>

    <?php $previousGroup = null; foreach ($orderedList as $order): ?>
        <?php
        $group = $order['status'] === 'pending' ? 'pending' : ($order['status'] === 'cancelled' ? 'cancelled' : 'other');
        if ($group !== $previousGroup):
            if ($group === 'pending'):
        ?>
            <h2 class="orders-section-heading">Pending orders</h2>
        <?php elseif ($group === 'other'): ?>
            <h2 class="orders-section-heading">Other orders</h2>
        <?php else: ?>
            <div class="orders-divider" role="heading" aria-level="2"><span>Cancelled orders</span></div>
        <?php endif; $previousGroup = $group; endif; ?>
        <article class="card order-card">
            <div class="order-head">
                <h2>Order #<?= (int)$order['order_id'] ?></h2>
                <span class="status status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
            </div>
            <p class="muted">Placed on <?= e(date('F j, Y g:i A', strtotime($order['order_date']))) ?></p>

            <ul class="item-list">
                <?php foreach ($orders->items((int)$order['order_id']) as $item): ?>
                    <li>
                        <?= e($item['service_name']) ?> &times; <?= (int)$item['copies'] ?>
                        (<?= e($item['paper_size']) ?>) = <?= money($item['line_total']) ?>
                        <?php if ($item['file_path']): ?>
                            <ul class="order-item-file"><li>File: <a href="<?= e(url('download.php?item_id=' . (int)$item['order_item_id'])) ?>"><?= e($item['file_name']) ?></a></li></ul>
                        <?php elseif ($item['file_name']): ?>
                            <ul class="order-item-file"><li>File: <?= e($item['file_name']) ?> (no file uploaded)</li></ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?>
                <p><strong>Notes:</strong> <?= e($order['notes']) ?></p>
            <?php endif; ?>
            <?php if ($order['promo_title']): ?>
                <p><strong>Promotion:</strong> <?= e($order['promo_title']) ?> (<?= e(rtrim(rtrim(number_format((float)$order['discount'], 2), '0'), '.')) ?>% off)</p>
            <?php endif; ?>
            <p class="price">Total: <?= money($order['total']) ?></p>

            <?php if ($order['status'] === 'pending'): ?>
                <div class="actions-row">
                    <a class="btn btn-outline btn-small" href="<?= e(url('order.php?order_id=' . (int)$order['order_id'])) ?>">Add another item</a>
                    <form method="post" class="inline-form" action="<?= e(url('my_orders.php')) ?>"
                          onsubmit="return confirm('Cancel this order?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                        <button class="btn btn-danger btn-small" type="submit">Cancel order</button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
