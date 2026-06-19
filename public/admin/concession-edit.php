<?php
declare(strict_types=1);
$pageTitle = 'Edit Concession Item';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo = new ConcessionRepo($db);
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = $id > 0 ? $repo->getById($id) : null;

$isNew  = $item === null;
$errors = [];
$old    = $item ?? [
    'category'       => '',
    'name'           => '',
    'description'    => '',
    'price'          => '',
    'cost'           => '',
    'reorder_point'  => '',
    'stock_quantity' => 0,
    'image_path'     => '',
    'is_available'   => 1,
    'sort_order'     => 0,
];

$csrf = $auth->generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid form token. Please try again.';
    } else {
        $data = [
            'category'       => trim($_POST['category']      ?? ''),
            'name'           => trim($_POST['name']          ?? ''),
            'description'    => trim($_POST['description']   ?? ''),
            'price'          => trim($_POST['price']         ?? ''),
            'cost'           => trim($_POST['cost']          ?? ''),
            'reorder_point'  => trim($_POST['reorder_point'] ?? ''),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'image_path'     => trim($_POST['image_path']    ?? ''),
            'is_available'   => isset($_POST['is_available']) ? 1 : 0,
            'sort_order'     => (int)($_POST['sort_order']   ?? 0),
        ];
        $old = $data;

        if ($data['name'] === '') $errors[] = 'Name is required.';
        if (!is_numeric($data['price']) || (float)$data['price'] < 0) $errors[] = 'Price must be a non-negative number.';
        if ($data['cost'] !== '' && (!is_numeric($data['cost']) || (float)$data['cost'] < 0)) $errors[] = 'Cost must be a non-negative number.';
        if ($data['reorder_point'] !== '' && (!ctype_digit($data['reorder_point']) || (int)$data['reorder_point'] < 0)) $errors[] = 'Reorder point must be a non-negative whole number.';

        if (empty($errors)) {
            if ($isNew) {
                $newId = $repo->create($data);
                if ($newId) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Concession item added.'];
                    header('Location: concessions.php');
                    exit;
                }
                $errors[] = 'Failed to save item.';
            } else {
                if ($repo->update($id, $data)) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Item updated.'];
                    header('Location: concessions.php');
                    exit;
                }
                $errors[] = 'Failed to update item.';
            }
        }
    }
}
?>

<div class="admin-page-header">
  <h1 class="admin-page-title"><?= $isNew ? 'Add Concession Item' : 'Edit: ' . e((string)$item['name']) ?></h1>
  <div style="display:flex; gap:0.5rem;">
    <?php if (!$isNew): ?>
      <a href="concession-options.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">Manage Options</a>
      <a href="concession-stock.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">Adjust Stock</a>
    <?php endif; ?>
    <a href="concessions.php" class="btn btn-sm btn-secondary">&#8592; Back</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST" action="concession-edit.php<?= $isNew ? '' : '?id=' . $id ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div class="form-group">
    <label for="category">Category</label>
    <input type="text" id="category" name="category" maxlength="80"
           value="<?= e((string)$old['category']) ?>"
           placeholder="e.g. Popcorn, Drinks, Candy, Combos">
  </div>

  <div class="form-group">
    <label for="name">Name <span class="required">*</span></label>
    <input type="text" id="name" name="name" required maxlength="255"
           value="<?= e((string)$old['name']) ?>">
  </div>

  <div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3"><?= e((string)$old['description']) ?></textarea>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="price">Sell Price ($) <span class="required">*</span></label>
      <input type="number" id="price" name="price" required min="0" step="0.01"
             value="<?= e((string)$old['price']) ?>" placeholder="0.00">
    </div>
    <div class="form-group">
      <label for="cost">Cost ($) <small style="font-weight:400;">(what you pay)</small></label>
      <input type="number" id="cost" name="cost" min="0" step="0.01"
             value="<?= e((string)($old['cost'] ?? '')) ?>" placeholder="Leave blank for now">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="stock_quantity">Current Stock</label>
      <input type="number" id="stock_quantity" name="stock_quantity" min="0"
             value="<?= (int)($old['stock_quantity'] ?? 0) ?>">
      <small class="form-help">Set starting inventory quantity.</small>
    </div>
    <div class="form-group">
      <label for="reorder_point">Reorder Point</label>
      <input type="number" id="reorder_point" name="reorder_point" min="0"
             value="<?= e((string)($old['reorder_point'] ?? '')) ?>" placeholder="e.g. 5">
      <small class="form-help">Alert fires when stock drops to or below this number.</small>
    </div>
  </div>

  <div class="form-group">
    <label for="image_path">Image Path</label>
    <input type="text" id="image_path" name="image_path" maxlength="500"
           value="<?= e((string)$old['image_path']) ?>"
           placeholder="images/concessions/popcorn.jpg">
    <small class="form-help">Relative to <code>assets/</code> — leave blank for no image.</small>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="sort_order">Sort Order</label>
      <input type="number" id="sort_order" name="sort_order" min="0"
             value="<?= (int)$old['sort_order'] ?>">
    </div>
    <div class="form-group" style="padding-top:1.75rem;">
      <label class="checkbox-label">
        <input type="checkbox" name="is_available" value="1"
               <?= $old['is_available'] ? 'checked' : '' ?>>
        Available for ordering
      </label>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $isNew ? 'Add Item' : 'Save Changes' ?>
    </button>
    <a href="concessions.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
