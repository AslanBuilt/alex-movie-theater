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
  <a href="concession-edit.php" class="btn btn-sm btn-primary">+ Add Item</a>
</div>

<?php if (empty($items)): ?>
  <p class="text-muted">No concession items yet. <a href="concession-edit.php">Add the first one.</a></p>
<?php else: ?>
  <div class="admin-table-wrap">
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
          <tr>
            <td><?= e($item['category']) ?></td>
            <td><?= e($item['name']) ?></td>
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

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
