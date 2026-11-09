<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Session expired. Refresh and try again.';
    else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'delete') {
                $stmt = db()->prepare('DELETE FROM menu_items WHERE id = :id'); $stmt->execute(['id' => (int)($_POST['id'] ?? 0)]);
                set_flash('success', 'Menu item deleted.'); redirect('admin/menu.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
            $category = (string)($_POST['category'] ?? 'coffee');
            $description = trim((string)($_POST['description'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $image = trim((string)($_POST['image_url'] ?? ''));
            $featured = isset($_POST['is_featured']) ? 1 : 0;
            $available = isset($_POST['is_available']) ? 1 : 0;
            $sort = max(0, (int)($_POST['sort_order'] ?? 0));
            if ($name === '' || $slug === '' || $description === '' || $price === false || $price < 0 || !in_array($category, ['coffee','dessert','pastry','tea'], true) || ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL))) throw new RuntimeException('Complete the fields with a valid name, slug, category, description, price and image URL.');
            $params = ['name'=>$name,'slug'=>$slug,'category'=>$category,'description'=>$description,'price'=>$price,'image_url'=>$image ?: null,'featured'=>$featured,'available'=>$available,'sort_order'=>$sort];
            if ($id > 0) { $params['id']=$id; $stmt = db()->prepare('UPDATE menu_items SET name=:name, slug=:slug, category=:category, description=:description, price=:price, image_url=:image_url, is_featured=:featured, is_available=:available, sort_order=:sort_order WHERE id=:id'); }
            else { $stmt = db()->prepare('INSERT INTO menu_items (name,slug,category,description,price,image_url,is_featured,is_available,sort_order) VALUES (:name,:slug,:category,:description,:price,:image_url,:featured,:available,:sort_order)'); }
            $stmt->execute($params); set_flash('success', $id ? 'Menu item updated.' : 'Menu item added.'); redirect('admin/menu.php');
        } catch (Throwable $exception) { $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not save the menu item. Check that its slug is unique.'; error_log('Menu admin error: ' . $exception->getMessage()); }
    }
}
$items = db()->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id DESC')->fetchAll();
$editId = (int)($_GET['edit'] ?? 0); $edit = null; foreach ($items as $item) if ((int)$item['id'] === $editId) $edit = $item;
$adminPageTitle = 'Menu items'; require __DIR__ . '/includes/header.php';
?><section class="admin-panel"><div class="panel-heading"><div><span class="admin-overline">YOUR COUNTER, ONLINE</span><h2><?= $edit ? 'Edit a menu item' : 'Add something good' ?></h2></div></div><?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? 0)) ?>"><input type="hidden" name="action" value="save"><div class="admin-form-grid"><label>Item name<input name="name" required maxlength="120" value="<?= e($edit['name'] ?? '') ?>"></label><label>URL slug<input name="slug" required maxlength="140" placeholder="e.g. velvet-latte" value="<?= e($edit['slug'] ?? '') ?>"></label><label>Category<select name="category"><?php foreach (['coffee','dessert','pastry','tea'] as $category): ?><option value="<?= $category ?>" <?= ($edit['category'] ?? 'coffee') === $category ? 'selected' : '' ?>><?= ucfirst($category) ?></option><?php endforeach; ?></select></label><label>Price (₦)<input type="number" min="0" step="50" name="price" required value="<?= e((string)($edit['price'] ?? '')) ?>"></label><label class="span-two">Description<textarea name="description" rows="2" required maxlength="500"><?= e($edit['description'] ?? '') ?></textarea></label><label class="span-two">Image URL (optional)<input type="url" name="image_url" maxlength="1000" value="<?= e($edit['image_url'] ?? '') ?>" placeholder="https://..."></label><label>Display order<input type="number" min="0" name="sort_order" value="<?= e((string)($edit['sort_order'] ?? 0)) ?>"></label><div class="admin-checks"><label><input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>> Featured item</label><label><input type="checkbox" name="is_available" <?= !isset($edit['is_available']) || !empty($edit['is_available']) ? 'checked' : '' ?>> Available on website</label></div></div><div class="admin-form-actions"><button class="admin-button" type="submit"><?= $edit ? 'Save changes' : 'Add menu item' ?> ↗</button><?php if ($edit): ?><a class="admin-cancel" href="<?= e(base_url('admin/menu.php')) ?>">Cancel edit</a><?php endif; ?></div></form></section><section class="admin-panel"><div class="panel-heading"><div><span class="admin-overline">CURRENT SELECTION</span><h2>Menu inventory <small><?= count($items) ?> items</small></h2></div></div><div class="table-wrap"><table><thead><tr><th>ITEM</th><th>CATEGORY</th><th>PRICE</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= e($item['name']) ?></strong><small><?= e($item['slug']) ?></small></td><td><?= e(ucfirst($item['category'])) ?></td><td><?= e(money($item['price'])) ?></td><td><span class="status-pill <?= $item['is_available'] ? 'read' : 'archived' ?>"><?= $item['is_available'] ? 'Available' : 'Hidden' ?></span></td><td class="actions-cell"><a class="table-action" href="<?= e(base_url('admin/menu.php?edit=' . $item['id'])) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this menu item?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="table-action danger-link" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section><?php require __DIR__ . '/includes/footer.php'; ?>
