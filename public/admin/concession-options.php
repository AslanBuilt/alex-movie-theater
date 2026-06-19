<?php
declare(strict_types=1);
$pageTitle = 'Manage Options';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo = new ConcessionRepo($db);
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = $id > 0 ? $repo->getById($id) : null;

if (!$item) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Concession item not found.'];
    header('Location: concessions.php');
    exit;
}

$errors  = [];
$csrf    = $auth->generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $label = trim($_POST['option_label'] ?? '');
            if ($label === '') {
                $errors[] = 'Option label cannot be empty.';
            } else {
                $sort = (int)($repo->getOptions($id) ? count($repo->getOptions($id)) : 0);
                if ($repo->addOption($id, $label, $sort)) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option added.'];
                    header('Location: concession-options.php?id=' . $id);
                    exit;
                }
                $errors[] = 'Failed to add option.';
            }
        } elseif ($action === 'delete') {
            $optId = (int)($_POST['option_id'] ?? 0);
            if ($repo->deleteOption($optId)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option removed.'];
                header('Location: concession-options.php?id=' . $id);
                exit;
            }
            $errors[] = 'Failed to remove option.';
        }
    }
}

$options = $repo->getOptions($id);
?>

<div class="admin-page-header">
  <h1>Options: <?= e((string)$item['name']) ?></h1>
  <a href="concession-edit.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">&#8592; Back to Item</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<p style="color:var(--color-text-muted); margin-bottom:1.5rem;">
  Options are shown to customers as a dropdown when they add this item (e.g. fountain flavors, candy choices).
</p>

<?php if (!empty($options)): ?>
  <table class="admin-table" style="margin-bottom:2rem;">
    <thead><tr><th>Option</th><th>Available</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($options as $opt): ?>
        <tr>
          <td><?= e((string)$opt['option_label']) ?></td>
          <td><?= $opt['is_available'] ? 'Yes' : 'No' ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="option_id" value="<?= (int)$opt['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger"
                      onclick="return confirm('Remove this option?')">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p style="color:var(--color-text-muted); margin-bottom:1.5rem;">No options yet.</p>
<?php endif; ?>

<div class="policy-box">
  <h3 style="margin-bottom:1rem;">Add Option</h3>
  <form method="POST" style="display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="flex:1; min-width:200px; margin:0;">
      <label for="option_label">Option Label</label>
      <input type="text" id="option_label" name="option_label" maxlength="100" placeholder="e.g. Pepsi" required>
    </div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
