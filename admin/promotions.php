<?php
/** admin/promotions.php  -  add, edit, switch on/off and delete promotions. */
require __DIR__ . '/../includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Promotion;
use PrintHub\Validator;
use PrintHub\ValidationException;

Auth::requireAdmin();

$promotions = new Promotion();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $action      = $_POST['action'] ?? '';
        $promotionId = (int)($_POST['promotion_id'] ?? 0);

        if ($action === 'save') {
            $v = new Validator($_POST);
            $v->required('title', 'Title');
            $v->maxLength('title', 'Title', 100);
            $v->maxLength('description', 'Description', 1000);
            $v->numberBetween('discount', 'Discount', 0, 100);
            $v->date('start_date', 'Start date');
            $v->date('end_date', 'End date');
            $v->inList('status', 'Status', ['active', 'inactive']);
            if (!$v->fails() && $v->value('end_date') < $v->value('start_date')) {   // YYYY-MM-DD text compares correctly
                $v->addError('The end date cannot be before the start date.');
            }

            if ($v->fails()) {
                $errors = $v->errors();
            } else {
                $promotions->save([
                    'title'       => $v->value('title'),
                    'description' => $v->value('description'),
                    'discount'    => $v->value('discount'),
                    'start_date'  => $v->value('start_date'),
                    'end_date'    => $v->value('end_date'),
                    'status'      => $v->value('status'),
                ], $promotionId > 0 ? $promotionId : null);

                flash('success', $promotionId > 0 ? 'Promotion updated.' : 'Promotion added.');
                redirect('admin/promotions.php');
            }
        } elseif ($action === 'toggle') {
            $promotions->toggleStatus($promotionId);
            flash('success', 'Promotion status changed.');
            redirect('admin/promotions.php');
        } elseif ($action === 'delete') {
            $promotions->delete($promotionId);
            flash('success', 'Promotion deleted.');
            redirect('admin/promotions.php');
        }
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }
}

$values = ['promotion_id' => '', 'title' => '', 'description' => '', 'discount' => '',
           'start_date' => date('Y-m-d'), 'end_date' => '', 'status' => 'active'];
$editId = (int)($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
    }
} elseif ($editId > 0 && ($row = $promotions->find($editId)) !== null) {
    $values = array_merge($values, array_intersect_key($row, $values));
}

$list      = $promotions->all();
$pageTitle = 'Admin: Promotions';
require __DIR__ . '/../includes/header.php';
?>
<div class="container section">
    <h1 class="admin-page-title">Manage promotions</h1>
    <div class="admin-manage-form-area">
    <?php errors_render($errors); ?>

    <h2 class="admin-form-title"><?= $values['promotion_id'] !== '' ? 'Edit promotion' : 'Add a new promotion' ?></h2>
    <form class="form" method="post" action="<?= e(url('admin/promotions.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="promotion_id" value="<?= e($values['promotion_id']) ?>">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" maxlength="100" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($values['description']) ?></textarea>

        <label for="discount">Discount (percent, 0 to 100)</label>
        <input type="number" id="discount" name="discount" value="<?= e($values['discount']) ?>" min="0" max="100" step="0.01" required>

        <label for="start_date">Start date</label>
        <input type="date" id="start_date" name="start_date" value="<?= e($values['start_date']) ?>" required>

        <label for="end_date">End date</label>
        <input type="date" id="end_date" name="end_date" value="<?= e($values['end_date']) ?>" required>

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $values['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button class="btn" type="submit">Save promotion</button>
        <?php if ($values['promotion_id'] !== ''): ?>
            <a class="btn btn-outline" href="<?= e(url('admin/promotions.php')) ?>">Cancel edit</a>
        <?php endif; ?>
    </form>
    </div>

    <h2>All promotions</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Title</th><th scope="col">Discount</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($list as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e(rtrim(rtrim(number_format((float)$p['discount'], 2), '0'), '.')) ?>%</td>
                    <td><?= e($p['start_date']) ?> to <?= e($p['end_date']) ?></td>
                    <td><span class="status status-<?= $p['status'] === 'active' ? 'completed' : 'cancelled' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                    <td>
                        <a class="btn btn-outline btn-small" href="<?= e(url('admin/promotions.php?edit=' . (int)$p['promotion_id'])) ?>">Edit</a>
                        <form method="post" class="inline-form" action="<?= e(url('admin/promotions.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="promotion_id" value="<?= (int)$p['promotion_id'] ?>">
                            <button class="btn btn-outline btn-small" type="submit"><?= $p['status'] === 'active' ? 'Turn off' : 'Turn on' ?></button>
                        </form>
                        <form method="post" class="inline-form" action="<?= e(url('admin/promotions.php')) ?>" onsubmit="return confirm('Delete this promotion?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="promotion_id" value="<?= (int)$p['promotion_id'] ?>">
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
