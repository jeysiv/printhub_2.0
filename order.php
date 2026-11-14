<?php
/**
 * order.php  -  place a NEW order, or add another item to a pending order (?order_id=5).
 * This is the main "PHP-processed feature": validate -> upload file -> save in the database.
 */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\FileUploader;
use PrintHub\Order;
use PrintHub\Promotion;
use PrintHub\Service;
use PrintHub\Validator;
use PrintHub\ValidationException;

$user = Auth::requireLogin();            // visitors are sent to the login page
if ($user->isAdmin()) {
    flash('error', 'The admin account cannot place orders.');
    redirect($user->getHomePage());
}

$orders = new Order();

// Are we adding an item to an existing order?
$existingOrder = null;
$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId > 0) {
    $existingOrder = $orders->findSummary($orderId);
    if ($existingOrder === null || (int)$existingOrder['user_id'] !== $user->getId() || $existingOrder['status'] !== 'pending') {
        flash('error', 'You can only add items to your own pending orders.');
        redirect('my_orders.php');
    }
}

$services = (new Service())->allActive();
$promos   = (new Promotion())->currentlyActive();
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();

        // ---- 1. VALIDATE ----
        $v = new Validator($_POST);
        $v->inList('service_id', 'Service', array_map('strval', array_column($services, 'service_id')));
        $v->inList('paper_size', 'Paper size', Order::PAPER_SIZES);
        if ($v->value('paper_size') === 'Custom') {
            $v->numberBetween('custom_width', 'Custom width', 0.1, 10000);
            $v->numberBetween('custom_length', 'Custom length', 0.1, 10000);
            $v->inList('custom_unit', 'Measurement unit', ['mm', 'cm', 'in', 'ft', 'm']);
        }
        $v->integerBetween('copies', 'Copies', 1, 1000);
        $v->maxLength('notes', 'Notes', 500);

        $promotionId = null;
        if ($existingOrder === null && $v->value('promotion_id') !== '') {
            $v->inList('promotion_id', 'Promotion', array_map('strval', array_column($promos, 'promotion_id')));
            $promotionId = (int)$v->value('promotion_id');
        }

        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            $paperSize = $v->value('paper_size');
            if ($paperSize === 'Custom') {
                $formatDimension = static function (string $value): string {
                    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
                };
                $paperSize = 'Custom ' . $formatDimension($v->value('custom_width'))
                    . ' × ' . $formatDimension($v->value('custom_length'))
                    . ' ' . $v->value('custom_unit');
            }

            // ---- 2. UPLOAD the file (optional) ----
            $uploader = new FileUploader(ROOT_PATH . '/uploads');
            $saved    = $uploader->save($_FILES['print_file'] ?? []);

            // ---- 3. SAVE in the database ----
            try {
                $item = [
                    'service_id' => (int)$v->value('service_id'),
                    'paper_size' => $paperSize,
                    'copies'     => (int)$v->value('copies'),
                    'file_name'  => $saved['name'] ?? null,
                    'file_path'  => $saved['path'] ?? null,
                ];

                if ($existingOrder !== null) {
                    $orders->addItem($orderId, $user->getId(), $item);
                    flash('success', 'Item added to order #' . $orderId . '.');
                } else {
                    $newId = $orders->createWithItem($user->getId(), $promotionId, $v->value('notes'), $item);
                    flash('success', 'Order #' . $newId . ' placed! We will confirm it soon.');
                }
            } catch (Throwable $e) {
                $uploader->delete($saved['path'] ?? null);   // do not keep a file for an order that failed
                throw $e;
            }
            redirect('my_orders.php');
        }
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }
}

// Which service is pre-selected? (from the form, or from the "Order this" link)
$selected = $_POST['service_id'] ?? $_GET['service_id'] ?? '';

$pageTitle = $existingOrder ? 'Add item to order' : 'Place an order';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
    <h1 class="order-page-title"><?= $existingOrder ? 'Add an item to order #' . (int)$orderId : 'Place an order' ?></h1>
    <?php errors_render($errors); ?>

    <form class="form order-form" method="post" enctype="multipart/form-data"
          action="<?= e(url('order.php' . ($existingOrder ? '?order_id=' . $orderId : ''))) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="service_id">Service</label>
        <select id="service_id" name="service_id" required>
            <option value="" data-price="0">-- Choose a service --</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= (int)$s['service_id'] ?>"
                    data-price="<?= e($s['price']) ?>"
                    data-unit="<?= e($s['unit']) ?>"
                    data-category="<?= e($s['category_name']) ?>"
                    data-description="<?= e($s['description'] ?? '') ?>"
                    <?= (string)$s['service_id'] === (string)$selected ? 'selected' : '' ?>>
                    <?= e($s['service_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <section id="selected-service-summary" class="order-service-summary" aria-live="polite" aria-atomic="true" hidden>
            <p id="service-summary-category" class="service-summary-category"></p>
            <h2 id="service-summary-name" class="service-summary-name"></h2>
            <p id="service-summary-description" class="service-summary-description"></p>
            <p id="service-summary-price" class="service-summary-price"></p>
        </section>

        <label for="print_file">File to print (optional)</label>
        <input type="file" id="print_file" name="print_file" accept=".pdf,.jpg,.jpeg,.png,.docx">
        <p class="hint">PDF, JPG, PNG, or DOCX, up to 5 MB. No file yet? Leave it empty and tell us in the notes.</p>

        <label for="paper_size">Paper size</label>
        <select id="paper_size" name="paper_size" required>
            <?php foreach (Order::PAPER_SIZES as $size): ?>
                <option value="<?= e($size) ?>" <?= ($_POST['paper_size'] ?? 'A4') === $size ? 'selected' : '' ?>><?= e($size) ?></option>
            <?php endforeach; ?>
        </select>

        <fieldset id="custom-size-panel" class="custom-size-panel" hidden>
            <legend id="custom-size-title">Custom dimensions</legend>
            <div class="custom-size-inputs">
                <div>
                    <label for="custom_width">Width</label>
                    <input type="number" id="custom_width" name="custom_width" min="0.1" max="10000" step="0.1" value="<?= old('custom_width') ?>">
                </div>
                <span class="custom-size-times" aria-hidden="true">×</span>
                <div>
                    <label for="custom_length">Length</label>
                    <input type="number" id="custom_length" name="custom_length" min="0.1" max="10000" step="0.1" value="<?= old('custom_length') ?>">
                </div>
                <div>
                    <label for="custom_unit">Unit</label>
                    <select id="custom_unit" name="custom_unit">
                        <?php foreach (['cm' => 'Centimeters (cm)', 'mm' => 'Millimeters (mm)', 'in' => 'Inches (in)', 'ft' => 'Feet (ft)', 'm' => 'Meters (m)'] as $unit => $label): ?>
                            <option value="<?= e($unit) ?>" <?= ($_POST['custom_unit'] ?? 'cm') === $unit ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <label for="copies">Number of copies / pages</label>
        <input type="number" id="copies" name="copies" min="1" max="1000" value="<?= old('copies') !== '' ? old('copies') : '1' ?>" required>

        <?php if ($existingOrder === null): ?>
            <label for="promotion_id">Promotion (optional)</label>
            <select id="promotion_id" name="promotion_id">
                <option value="">No promotion</option>
                <?php foreach ($promos as $p): ?>
                    <option value="<?= (int)$p['promotion_id'] ?>" <?= (string)($p['promotion_id']) === ($_POST['promotion_id'] ?? '') ? 'selected' : '' ?>>
                        <?= e($p['title']) ?> (<?= e(rtrim(rtrim(number_format((float)$p['discount'], 2), '0'), '.')) ?>% off)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="notes">Notes for the shop (optional)</label>
            <textarea id="notes" name="notes" rows="3" maxlength="500"><?= old('notes') ?></textarea>
        <?php endif; ?>

        <button class="btn btn-accent" type="submit"><?= $existingOrder ? 'Add item' : 'Place order' ?></button>
    </form>
</div>

<script>
// Keep the selected service summary in sync with the dropdown.
(function () {
    var service = document.getElementById('service_id');
    var paperSize = document.getElementById('paper_size');
    var customPanel = document.getElementById('custom-size-panel');
    var widthInput = document.getElementById('custom_width');
    var lengthInput = document.getElementById('custom_length');
    var unitSelect = document.getElementById('custom_unit');
    var summary = document.getElementById('selected-service-summary');
    var summaryCategory = document.getElementById('service-summary-category');
    var summaryName = document.getElementById('service-summary-name');
    var summaryDescription = document.getElementById('service-summary-description');
    var summaryPrice = document.getElementById('service-summary-price');
    function updateCustomSizePanel() {
        var visible = paperSize.value === 'Custom';
        customPanel.hidden = !visible;
        widthInput.required = visible;
        lengthInput.required = visible;
        unitSelect.required = visible;
    }

    function update() {
        var selected = service.options[service.selectedIndex];
        var price = parseFloat(selected.getAttribute('data-price')) || 0;
        if (service.value) {
            summaryCategory.textContent = selected.getAttribute('data-category') || '';
            summaryName.textContent = selected.textContent.trim();
            summaryDescription.textContent = selected.getAttribute('data-description') || '';
            summaryPrice.textContent = '\u20B1' + price.toFixed(2) + ' ' + (selected.getAttribute('data-unit') || '');
            summary.hidden = false;
        } else {
            summary.hidden = true;
        }
    }
    service.addEventListener('change', update);
    paperSize.addEventListener('change', updateCustomSizePanel);
    update();
    updateCustomSizePanel();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
