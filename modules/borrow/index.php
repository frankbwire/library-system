<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Borrow Records';
$pdo = getDB();

$search     = trim($_GET['q']      ?? '');
$status     = trim($_GET['status'] ?? '');
$dateFrom   = trim($_GET['from']   ?? '');
$dateTo     = trim($_GET['to']     ?? '');
$perPage    = 20; $page = max(1,(int)($_GET['page']??1));

$where = []; $params = [];
if ($search)   { $where[] = "(b.title LIKE ? OR b.book_id LIKE ? OR bo.full_name LIKE ? OR br.record_id LIKE ?)";
                 $params  = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($status)   { $where[] = "br.status=?";             $params[] = $status; }
if ($dateFrom) { $where[] = "br.borrow_date >= ?";     $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "br.borrow_date <= ?";     $params[] = $dateTo; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countSQL = "SELECT COUNT(*) FROM borrow_records br
    JOIN books b ON br.book_id=b.id
    JOIN borrowers bo ON br.borrower_id=bo.id $whereSQL";
$total = $pdo->prepare($countSQL);
$total->execute($params);
$p = paginate((int)$total->fetchColumn(), $perPage, $page);

$dataSQL = "SELECT br.*, b.title, b.book_id book_code, bo.full_name borrower_name,
    bo.borrower_id borrower_code, bo.category borrower_cat,
    DATEDIFF(COALESCE(br.return_date, CURDATE()), br.borrow_date) days_borrowed
    FROM borrow_records br
    JOIN books b  ON br.book_id=b.id
    JOIN borrowers bo ON br.borrower_id=bo.id
    $whereSQL ORDER BY br.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}";
$stmt = $pdo->prepare($dataSQL);
$stmt->execute($params);
$records = $stmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Borrow & Return Records</span>
    <div class="ms-auto d-flex gap-2 no-print">
      <a href="issue.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Issue Book</a>
    </div>
  </div>
  <div class="page-content">
    <?= renderFlash() ?>

    <!-- Filters -->
    <div class="card mb-4 no-print">
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-sm-3">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Book, borrower, record ID…" value="<?= sanitize($search) ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="Not Returned" <?= $status==='Not Returned'?'selected':'' ?>>Not Returned</option>
              <option value="Returned"     <?= $status==='Returned'?'selected':'' ?>>Returned</option>
            </select>
          </div>
          <div class="col-sm-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
          </div>
          <div class="col-auto">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x-lg"></i></a>
          </div>
          <div class="col-auto ms-auto d-flex gap-2">
            <a href="<?= BASE_URL ?>/modules/reports/export.php?type=borrow_records&<?= http_build_query(['q'=>$search,'status'=>$status,'from'=>$dateFrom,'to'=>$dateTo]) ?>"
               class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>CSV</a>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-print"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="bi bi-arrow-left-right"></i> Records
        <span class="badge bg-secondary ms-2"><?= number_format($p['total']) ?></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>#</th><th>Record ID</th><th>Book</th><th>Borrower</th>
                <th>Category</th><th>Borrow Date</th><th>Due Date</th>
                <th>Return Date</th><th>Days</th><th>Status</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($records): ?>
            <?php foreach ($records as $i => $r): ?>
            <?php $overdue = isOverdue($r['borrow_date'],$r['return_date'],$r['status']); ?>
            <tr <?= $overdue ? 'class="table-warning"' : '' ?>>
              <td><?= $p['offset']+$i+1 ?></td>
              <td><code style="font-size:.8rem"><?= sanitize($r['record_id']) ?></code></td>
              <td>
                <div><?= sanitize($r['title']) ?></div>
                <small class="text-muted"><?= sanitize($r['book_code']) ?></small>
              </td>
              <td>
                <div><?= sanitize($r['borrower_name']) ?></div>
                <small class="text-muted"><?= sanitize($r['borrower_code']) ?></small>
              </td>
              <td><?= badge($r['borrower_cat']) ?></td>
              <td><?= date('d M Y',strtotime($r['borrow_date'])) ?></td>
              <td><?= date('d M Y',strtotime($r['due_date'])) ?></td>
              <td><?= $r['return_date'] ? date('d M Y',strtotime($r['return_date'])) : '<span class="text-muted">—</span>' ?></td>
              <td>
                <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>"><?= $r['days_borrowed'] ?></span>
              </td>
              <td>
                <?= badge($r['status']) ?>
                <?php if ($overdue): ?><span class="badge bg-danger">Overdue</span><?php endif; ?>
              </td>
              <td class="no-print" style="white-space:nowrap">
                <?php if ($r['status'] === 'Not Returned'): ?>
                <a href="return.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-success py-0 px-2">
                  <i class="bi bi-arrow-return-left"></i> Return
                </a>
                <?php else: ?>
                <span class="text-muted small">Returned</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="11" class="text-center text-muted py-4">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($p['total_pages'] > 1): ?>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted">Showing <?= $p['offset']+1 ?>–<?= min($p['offset']+$p['per_page'],$p['total']) ?> of <?= $p['total'] ?></small>
        <?= pagerHtml($p,'?'.http_build_query(['q'=>$search,'status'=>$status,'from'=>$dateFrom,'to'=>$dateTo])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
