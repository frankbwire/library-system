<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Edit Book';
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$book = $pdo->prepare("SELECT * FROM books WHERE id=?");
$book->execute([$id]);
$book = $book->fetch();
if (!$book) { flash('error','Book not found.'); redirect(BASE_URL.'/modules/books/index.php'); }

$errors = []; $data = $book;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'book_id'          => strtoupper(trim($_POST['book_id'] ?? '')),
        'title'            => trim($_POST['title'] ?? ''),
        'author'           => trim($_POST['author'] ?? ''),
        'category'         => trim($_POST['category'] ?? ''),
        'shelf_location'   => trim($_POST['shelf_location'] ?? ''),
        'status'           => $_POST['status'] ?? 'Available',
        'condition_status' => $_POST['condition_status'] ?? 'Good',
    ];

    if (!$data['book_id'])  $errors[] = 'Book ID is required.';
    if (!$data['title'])    $errors[] = 'Title is required.';
    if (!$data['author'])   $errors[] = 'Author is required.';
    if (!$data['category']) $errors[] = 'Category is required.';

    // Unique check (exclude self)
    $check = $pdo->prepare("SELECT id FROM books WHERE book_id=? AND id!=?");
    $check->execute([$data['book_id'], $id]);
    if ($check->fetch()) $errors[] = "Book ID '{$data['book_id']}' already used by another record.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE books SET book_id=?,title=?,author=?,category=?,shelf_location=?,status=?,condition_status=? WHERE id=?");
        $stmt->execute([...(array_values($data)), $id]);
        auditLog('update','books',$id,'Book updated: '.$data['title']);
        flash('success', 'Book updated successfully!');
        redirect(BASE_URL . '/modules/books/index.php');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Edit Book</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="page-content">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="card" style="max-width:680px;margin:0 auto">
      <div class="card-header"><i class="bi bi-pencil"></i> Edit: <?= sanitize($book['title']) ?></div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Book ID *</label>
              <input type="text" name="book_id" class="form-control" required value="<?= sanitize($data['book_id']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Title *</label>
              <input type="text" name="title" class="form-control" required value="<?= sanitize($data['title']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Author *</label>
              <input type="text" name="author" class="form-control" required value="<?= sanitize($data['author']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Category *</label>
              <input type="text" name="category" class="form-control" required list="cat-list" value="<?= sanitize($data['category']) ?>">
              <datalist id="cat-list">
                <?php foreach ($pdo->query("SELECT DISTINCT category FROM books ORDER BY category")->fetchAll(PDO::FETCH_COLUMN) as $c): ?>
                <option value="<?= sanitize($c) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-4">
              <label class="form-label">Shelf Location</label>
              <input type="text" name="shelf_location" class="form-control" value="<?= sanitize($data['shelf_location'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['Available','Borrowed','Missing'] as $s): ?>
                <option value="<?=$s?>" <?= $data['status']===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Condition</label>
              <select name="condition_status" class="form-select">
                <?php foreach (['Good','Damaged'] as $s): ?>
                <option value="<?=$s?>" <?= $data['condition_status']===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Book</button>
              <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
