<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Add Book';
$pdo = getDB();
$errors = []; $data = [];

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

    if (!$data['book_id'])   $errors[] = 'Book ID is required.';
    if (!$data['title'])     $errors[] = 'Title is required.';
    if (!$data['author'])    $errors[] = 'Author is required.';
    if (!$data['category'])  $errors[] = 'Category is required.';

    // Unique check
    if ($data['book_id']) {
        $check = $pdo->prepare("SELECT id FROM books WHERE book_id=?");
        $check->execute([$data['book_id']]);
        if ($check->fetch()) $errors[] = "Book ID '{$data['book_id']}' already exists.";
    }

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO books(book_id,title,author,category,shelf_location,status,condition_status)
            VALUES(?,?,?,?,?,?,?)");
        $stmt->execute(array_values($data));
        $newId = (int)$pdo->lastInsertId();
        auditLog('create','books',$newId,'Book added: '.$data['title']);
        flash('success', 'Book added successfully!');
        redirect(BASE_URL . '/modules/books/index.php');
    }
}

// Suggest next Book ID
$suggestId = nextId('BK-', 'books', 'book_id');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Add New Book</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
  <div class="page-content">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card" style="max-width:680px;margin:0 auto">
      <div class="card-header"><i class="bi bi-plus-circle"></i> Book Details</div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Book ID *</label>
              <input type="text" name="book_id" class="form-control" required
                     value="<?= sanitize($data['book_id'] ?? $suggestId) ?>" placeholder="e.g. BK-016">
              <div class="form-text">Suggested: <strong><?= $suggestId ?></strong></div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Title *</label>
              <input type="text" name="title" class="form-control" required value="<?= sanitize($data['title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Author *</label>
              <input type="text" name="author" class="form-control" required value="<?= sanitize($data['author'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Category *</label>
              <input type="text" name="category" class="form-control" required
                     list="cat-list" value="<?= sanitize($data['category'] ?? '') ?>">
              <datalist id="cat-list">
                <?php
                $cats = $pdo->query("SELECT DISTINCT category FROM books ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($cats as $c) echo "<option value='" . sanitize($c) . "'>";
                ?>
              </datalist>
            </div>
            <div class="col-md-4">
              <label class="form-label">Shelf Location</label>
              <input type="text" name="shelf_location" class="form-control" placeholder="e.g. A-1"
                     value="<?= sanitize($data['shelf_location'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['Available','Borrowed','Missing'] as $s): ?>
                <option value="<?=$s?>" <?= ($data['status']??'Available')===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Condition</label>
              <select name="condition_status" class="form-select">
                <?php foreach (['Good','Damaged'] as $s): ?>
                <option value="<?=$s?>" <?= ($data['condition_status']??'Good')===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 d-flex gap-2 mt-2">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Book</button>
              <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
