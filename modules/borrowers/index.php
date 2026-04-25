<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Borrowers';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];
    $active = $pdo->prepare("SELECT COUNT(*) FROM borrow_records WHERE borrower_id=? AND status='Not Returned'");
    $active->execute([$id]);
    if ($active->fetchColumn() > 0) {
        flash('error', 'Cannot delete: borrower has unreturned books.');
    } else {
        $pdo->prepare("DELETE FROM borrowers WHERE id=?")->execute([$id]);
        auditLog('delete','borrowers',$id);
        flash('success','Borrower deleted.');
    }
    redirect(BASE_URL . '/modules/borrowers/index.php');
}

$search   = trim($_GET['q']   ?? '');
$category = trim($_GET['cat'] ?? '');
$status   = trim($_GET['status'] ?? '');
$perPage  = 20; $page = max(1,(int)($_GET['page']??1));

$where=[]; $params=[];
if ($search)   { $where[]="(full_name LIKE ? OR borrower_id LIKE ? OR email LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($category) { $where[]="category=?"; $params[]=$category; }
if ($status)   { $where[]="status=?";   $params[]=$status; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM borrowers $whereSQL");
$total->execute($params);
$p = paginate((int)$total->fetchColumn(),$perPage,$page);

$stmt = $pdo->prepare("SELECT b.*,
    (SELECT COUNT(*) FROM borrow_records br WHERE br.borrower_id=b.id) total_borrows,
    (SELECT COUNT(*) FROM borrow_records br WHERE br.borrower_id=b.id AND br.status='Not Returned') active_borrows
    FROM borrowers b $whereSQL ORDER BY b.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$borrowers = $stmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Borrowers</span>
    <a href="add.php" class="btn btn-primary btn-sm ms-auto no-print">
      <i class="bi bi-plus-lg me-1"></i>Add Borrower
    </a>
  </div>
  <div class="page-content">
    <?= renderFlash() ?>
    <div class="card mb-4 no-print">
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-sm-4">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Name, ID, email…" value="<?= sanitize($search) ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label">Category</label>
            <select name="cat" class="form-select">
              <option value="">All</option>
              <?php foreach (['Student','Teacher','Staff'] as $c): ?>
              <option value="<?=$c?>" <?= $category===$c?'selected':'' ?>><?=$c?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="Active"   <?= $status==='Active'?'selected':'' ?>>Active</option>
              <option value="Inactive" <?= $status==='Inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x-lg"></i></a>
          </div>
          <div class="col-auto ms-auto d-flex gap-2">
            <a href="<?= BASE_URL ?>/modules/reports/export.php?type=borrowers&<?= http_build_query(['q'=>$search,'cat'=>$category,'status'=>$status]) ?>"
               class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>CSV</a>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-print"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-people"></i> Borrowers <span class="badge bg-secondary ms-2"><?= number_format($p['total']) ?></span></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>#</th><th>ID</th><th>Name</th><th>Category</th><th>Department</th><th>Phone</th><th>Email</th><th>Status</th><th>Borrows</th><th class="no-print">Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($borrowers): ?>
            <?php foreach ($borrowers as $i => $b): ?>
            <tr>
              <td><?= $p['offset']+$i+1 ?></td>
              <td><code><?= sanitize($b['borrower_id']) ?></code></td>
              <td><strong><?= sanitize($b['full_name']) ?></strong></td>
              <td><?= badge($b['category']) ?></td>
              <td class="text-muted"><?= sanitize($b['department']??'—') ?></td>
              <td><?= sanitize($b['phone']??'—') ?></td>
              <td><?= sanitize($b['email']??'—') ?></td>
              <td><?= badge($b['status']) ?></td>
              <td>
                <span title="Total borrows" class="badge bg-secondary"><?= $b['total_borrows'] ?></span>
                <?php if ($b['active_borrows'] > 0): ?>
                  <span class="badge bg-warning text-dark"><?= $b['active_borrows'] ?> active</span>
                <?php endif; ?>
              </td>
              <td class="no-print" style="white-space:nowrap">
                <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-pencil"></i></a>
                <a href="history.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-info py-0 px-2"><i class="bi bi-clock-history"></i></a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No borrowers found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($p['total_pages'] > 1): ?>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted">Showing <?= $p['offset']+1 ?>–<?= min($p['offset']+$p['per_page'],$p['total']) ?> of <?= $p['total'] ?></small>
        <?= pagerHtml($p,'?'.http_build_query(['q'=>$search,'cat'=>$category,'status'=>$status])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
