<?php
/** admin/services.php  -  add, edit, show/hide and delete services (full CRUD). */
require __DIR__ . '/../includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Category;
use PrintHub\Service;
use PrintHub\Validator;
use PrintHub\ValidationException;

Auth::requireAdmin();

$services   = new Service();
$categories = new Category();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $action    = $_POST['action'] ?? '';
        $serviceId = (int)($_POST['service_id'] ?? 0);

        if ($action === 'save') {
            // ---- validate ----
            $v = new Validator($_POST);
            $v->required('service_name', 'Service name');
            $v->maxLength('service_name', 'Service name', 100);
            $v->maxLength('description', 'Description', 1000);
            $v->numberBetween('price', 'Price', 0, 1000000);
            $v->required('unit', 'Unit');
            $v->maxLength('unit', 'Unit', 20);
            $v->inList('status', 'Status', ['active', 'inactive']);

            $newCategory = $v->value('new_category');
            if ($newCategory !== '') {
                $v->maxLength('new_category', 'New category', 50);
            } else {
                $v->inList('category_id', 'Category', array_map('strval', array_column($categories->all(), 'category_id')));
            }

            if ($v->fails()) {
                $errors = $v->errors();
            } else {
                // a typed new category wins over the dropdown
                $categoryId = $newCategory !== '' ? $categories->findOrCreate($newCategory) : (int)$v->value('category_id');

                $services->save([
                    'category_id'  => $categoryId,
                    'service_name' => $v->value('service_name'),
                    'description'  => $v->value('description'),
                    'price'        => $v->value('price'),
                    'unit'         => $v->value('unit'),
                    'status'       => $v->value('status'),
                ], $serviceId > 0 ? $serviceId : null);

                flash('success', $serviceId > 0 ? 'Service updated.' : 'Service added.');
                redirect('admin/services.php');
            }
        } elseif ($action === 'toggle') {
            $services->toggleStatus($serviceId);
            flash('success', 'Service status changed.');
            redirect('admin/services.php');
        } elseif ($action === 'delete') {
            $services->delete($serviceId);
            flash('success', 'Service deleted.');
            redirect('admin/services.php');
        }
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }
}

// ---- Values shown in the form: from the failed POST, or from the service being edited ----
$values = ['service_id' => '', 'category_id' => '', 'service_name' => '', 'description' => '',
           'price' => '', 'unit' => 'per piece', 'status' => 'active', 'new_category' => ''];
$editId = (int)($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
    }
} elseif ($editId > 0 && ($row = $services->find($editId)) !== null) {
    $values = array_merge($values, array_intersect_key($row, $values));
}

$list          = $services->allWithCategory();
$allCategories = $categories->all();
$pageTitle     = 'Admin: Services';
require __DIR__ . '/../includes/header.php';
?>
<div class="container section">
    <h1 class="admin-page-title">Manage services</h1>
    <div class="admin-manage-form-area">
    <?php errors_render($errors); ?>

    <h2 class="admin-form-title"><?= $values['service_id'] !== '' ? 'Edit service' : 'Add a new service' ?></h2>
    <form class="form" method="post" action="<?= e(url('admin/services.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="service_id" value="<?= e($values['service_id']) ?>">

        <label for="service_name">Service name</label>
        <input type="text" id="service_name" name="service_name" value="<?= e($values['service_name']) ?>" maxlength="100" required>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <?php foreach ($allCategories as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>" <?= (string)$c['category_id'] === (string)$values['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="new_category">...or type a new category</label>
        <input type="text" id="new_category" name="new_category" value="<?= e($values['new_category']) ?>" maxlength="50">
        <p class="hint">If you type here, it is used instead of the dropdown.</p>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($values['description']) ?></textarea>

        <label for="price">Price (PHP)</label>
        <input type="number" id="price" name="price" value="<?= e($values['price']) ?>" min="0" step="0.01" required>

        <label for="unit">Unit (for example: per page)</label>
        <input type="text" id="unit" name="unit" value="<?= e($values['unit']) ?>" maxlength="20" required>

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Active (customers can order it)</option>
            <option value="inactive" <?= $values['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option>
        </select>

        <button class="btn" type="submit">Save service</button>
        <?php if ($values['service_id'] !== ''): ?>
            <a class="btn btn-outline" href="<?= e(url('admin/services.php')) ?>">Cancel edit</a>
        <?php endif; ?>
    </form>
    </div>

    <h2>All services</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Service</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($list as $s): ?>
                <tr>
                    <td><?= e($s['service_name']) ?></td>
                    <td><?= e($s['category_name']) ?></td>
                    <td><?= money($s['price']) ?> <span class="muted"><?= e($s['unit']) ?></span></td>
                    <td><span class="status status-<?= $s['status'] === 'active' ? 'completed' : 'cancelled' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                    <td>
                        <a class="btn btn-outline btn-small" href="<?= e(url('admin/services.php?edit=' . (int)$s['service_id'])) ?>">Edit</a>
                        <form method="post" class="inline-form" action="<?= e(url('admin/services.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                            <button class="btn btn-outline btn-small" type="submit"><?= $s['status'] === 'active' ? 'Hide' : 'Show' ?></button>
                        </form>
                        <form method="post" class="inline-form" action="<?= e(url('admin/services.php')) ?>" onsubmit="return confirm('Delete this service?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                            <button class="btn btn-danger btn-small" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
