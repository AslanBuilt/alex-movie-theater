<?php
declare(strict_types=1);
$pageTitle = 'Concessions';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo  = new ConcessionRepo($db);
$items = $repo->getAll();
?>

<div class="admin-page-header">
  <h1 class="admin-page-title">Concessions</h1>
  <div style="display:flex; gap:0.5rem;">
    <?php if (!empty($items)): ?>
      <button type="button" class="btn btn-outline btn-sm" id="arrange-toggle">Arrange Order &#8597;</button>
      <button type="button" class="btn btn-primary btn-sm" id="save-order" hidden>Save Order</button>
      <button type="button" class="btn btn-outline btn-sm" id="cancel-order" hidden>Cancel</button>
    <?php endif; ?>
    <a href="concession-edit.php" class="btn btn-sm btn-primary">+ Add Item</a>
  </div>
</div>

<div id="reorder-status" aria-live="polite"></div>

<?php if (empty($items)): ?>
  <p class="text-muted">No concession items yet. <a href="concession-edit.php">Add the first one.</a></p>
<?php else: ?>
  <p class="form-help" style="margin-top:-0.25rem;">Sorted by category, then order, then name. "Arrange Order" reorders the full list below (drag rows, then Save Order).</p>
  <div class="admin-table-wrap" id="concessions-table-wrap" data-csrf="<?= e($auth->generateCsrfToken()) ?>">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Category</th>
          <th>Name</th>
          <th>Price</th>
          <th>Available</th>
          <th>Order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr class="concession-row" data-concession-id="<?= (int)$item['id'] ?>">
            <td><?= e($item['category']) ?></td>
            <td>
              <span class="drag-handle" aria-hidden="true" style="display:none; cursor:grab; margin-right:0.4rem; user-select:none;">&#8942;&#8942;</span>
              <?= e($item['name']) ?>
            </td>
            <td>$<?= number_format((float)$item['price'], 2) ?></td>
            <td>
              <?php if ($item['is_available']): ?>
                <span class="badge badge-success">Yes</span>
              <?php else: ?>
                <span class="badge badge-muted">No</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$item['sort_order'] ?></td>
            <td class="admin-actions">
              <a href="concession-edit.php?id=<?= (int)$item['id'] ?>">Edit</a>
              <a href="concession-delete.php?id=<?= (int)$item['id'] ?>" class="text-danger"
                 onclick="return confirm('Delete <?= e(addslashes($item['name'])) ?>?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script src="../assets/js/admin-upload.js" defer></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
