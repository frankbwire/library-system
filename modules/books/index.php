<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Books';
$pdo = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];
    // Check if book has active borrows
    $active = $pdo->prepare("SELECT COUNT(*) FROM borrow_records WHERE book_id=? AND status='Not Returned'");
    $active->execute([$id]);
    if ($active->fetchColumn() > 0) {
        flash('error', 'Cannot delete: this book has active borrow records.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM books WHERE id=?");
        $stmt->execute([$id]);
        auditLog('delete','books',$id,'Book deleted');
        flash('success', 'Book deleted successfully.');
    }
    redirect(BASE_URL . '/modules/books/index.php');
}

// Filters
$search   = trim($_GET['q']   ?? '');
$category = trim($_GET['cat'] ?? '');
$status   = trim($_GET['status'] ?? '');
$perPage  = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = []; $params = [];
if ($search)   { $where[] = "(title LIKE ? OR author LIKE ? OR book_id LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($category) { $where[] = "category=?";      $params[] = $category; }
if ($status)   { $where[] = "status=?";        $params[] = $status; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM books $whereSQL");
$total->execute($params);
$p = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $pdo->prepare("SELECT * FROM books $whereSQL ORDER BY created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM books ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Books Management</span>
    <a href="<?= BASE_URL ?>/modules/books/add.php" class="btn btn-primary btn-sm ms-auto no-print">
      <i class="bi bi-plus-lg me-1"></i>Add Book
    </a>
  </div>

  <div class="page-content">
    <?= renderFlash() ?>

    <!-- Filters -->
    <div class="card mb-4 no-print">
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-sm-4 col-lg-3">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Title, author, ID…" value="<?= sanitize($search) ?>">
          </div>
          <div class="col-sm-3 col-lg-2">
            <label class="form-label">Category</label>
            <select name="cat" class="form-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= sanitize($c) ?>" <?= $category===$c?'selected':'' ?>><?= sanitize($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-3 col-lg-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All Status</option>
              <option value="Available" <?= $status==='Available'?'selected':'' ?>>Available</option>
              <option value="Borrowed"  <?= $status==='Borrowed'?'selected':'' ?>>Borrowed</option>
              <option value="Missing"   <?= $status==='Missing'?'selected':'' ?>>Missing</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x-lg"></i></a>
          </div>
          <div class="col-auto ms-auto d-flex gap-2">
            <a href="<?= BASE_URL ?>/modules/reports/export.php?type=books&<?= http_build_query(['q'=>$search,'cat'=>$category,'status'=>$status]) ?>"
               class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>CSV</a>
            <button class="btn btn-outline-secondary btn-sm btn-print"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="bi bi-book"></i> Books
        <span class="badge bg-secondary ms-2"><?= number_format($p['total']) ?></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>#</th><th>Book ID</th><th>Title</th><th>Author</th>
                <th>Category</th><th>Shelf</th><th>Status</th><th>Condition</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($books): ?>
            <?php foreach ($books as $i => $b): ?>
            <tr>
              <td><?= $p['offset'] + $i + 1 ?></td>
              <td><code><?= sanitize($b['book_id']) ?></code></td>
              <td><?= sanitize($b['title']) ?></td>
              <td class="text-muted"><?= sanitize($b['author']) ?></td>
              <td><?= sanitize($b['category']) ?></td>
              <td><?= sanitize($b['shelf_location'] ?? '—') ?></td>
              <td><?= badge($b['status']) ?></td>
              <td><?= badge($b['condition_status']) ?></td>
              <td class="no-print" style="white-space:nowrap">
                <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No books found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($p['total_pages'] > 1): ?>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted">Showing <?= $p['offset']+1 ?>–<?= min($p['offset']+$p['per_page'],$p['total']) ?> of <?= $p['total'] ?></small>
        <?= pagerHtml($p, '?' . http_build_query(['q'=>$search,'cat'=>$category,'status'=>$status])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
