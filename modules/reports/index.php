<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Reports';
$pdo = getDB();

$type      = $_GET['type']   ?? 'borrow_records';
$dateFrom  = $_GET['from']   ?? date('Y-m-01');
$dateTo    = $_GET['to']     ?? date('Y-m-d');
$bookCat   = $_GET['bcat']   ?? '';
$borrowCat = $_GET['pcat']   ?? '';
$status    = $_GET['status'] ?? '';

$validTypes = ['borrow_records','all_books','overdue','top_books','active_borrowers'];
if (!in_array($type, $validTypes)) $type = 'borrow_records';

// ── Build report data ───────────────────────────────────────────
$reportData = []; $reportTitle = '';

// Common filter builder for borrow_records queries
function buildBorrowWhere(array $filters): array {
    $where = []; $params = [];
    ['from'=>$from,'to'=>$to,'bcat'=>$bcat,'pcat'=>$pcat,'status'=>$status] = $filters;
    if ($from)   { $where[] = 'br.borrow_date >= ?';    $params[] = $from; }
    if ($to)     { $where[] = 'br.borrow_date <= ?';    $params[] = $to; }
    if ($bcat)   { $where[] = 'b.category = ?';         $params[] = $bcat; }
    if ($pcat)   { $where[] = 'bo.category = ?';        $params[] = $pcat; }
    if ($status) { $where[] = 'br.status = ?';          $params[] = $status; }
    return [$where ? 'WHERE '.implode(' AND ',$where) : '', $params];
}

$filters = ['from'=>$dateFrom,'to'=>$dateTo,'bcat'=>$bookCat,'pcat'=>$borrowCat,'status'=>$status];

switch ($type) {
    case 'borrow_records':
        $reportTitle = 'Borrow Records Report';
        [$whereSQL, $params] = buildBorrowWhere($filters);
        $stmt = $pdo->prepare("SELECT br.record_id, b.book_id book_code, b.title, b.category book_cat,
            bo.borrower_id borrower_code, bo.full_name borrower_name, bo.category borrower_cat,
            br.borrow_date, br.due_date, br.return_date, br.status,
            DATEDIFF(COALESCE(br.return_date, CURDATE()), br.borrow_date) days_borrowed
            FROM borrow_records br
            JOIN books b ON br.book_id=b.id
            JOIN borrowers bo ON br.borrower_id=bo.id
            $whereSQL ORDER BY br.borrow_date DESC");
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'all_books':
        $reportTitle = 'Books Inventory Report';
        $where = []; $params = [];
        if ($bookCat) { $where[] = "category=?"; $params[] = $bookCat; }
        if ($status)  { $where[] = "status=?";   $params[] = $status; }
        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT book_id, title, author, category, shelf_location, status, condition_status, created_at FROM books $w ORDER BY category, title");
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'overdue':
        $reportTitle = 'Overdue Books Report';
        $stmt = $pdo->query("SELECT br.record_id, b.book_id book_code, b.title, bo.full_name borrower_name,
            bo.borrower_id borrower_code, bo.category borrower_cat, bo.phone,
            br.borrow_date, br.due_date,
            DATEDIFF(CURDATE(), br.borrow_date) days_borrowed,
            DATEDIFF(CURDATE(), br.due_date) days_overdue
            FROM borrow_records br
            JOIN books b ON br.book_id=b.id
            JOIN borrowers bo ON br.borrower_id=bo.id
            WHERE br.status='Not Returned' AND DATEDIFF(CURDATE(), br.borrow_date) > " . BORROW_DAYS . "
            ORDER BY days_overdue DESC");
        $reportData = $stmt->fetchAll();
        break;

    case 'top_books':
        $reportTitle = 'Most Borrowed Books';
        $stmt = $pdo->query("SELECT b.book_id, b.title, b.author, b.category,
            COUNT(br.id) total_borrows,
            SUM(br.status='Not Returned') currently_out,
            MAX(br.borrow_date) last_borrowed
            FROM books b
            LEFT JOIN borrow_records br ON b.id=br.book_id
            GROUP BY b.id ORDER BY total_borrows DESC, b.title LIMIT 50");
        $reportData = $stmt->fetchAll();
        break;

    case 'active_borrowers':
        $reportTitle = 'Most Active Borrowers';
        $stmt = $pdo->query("SELECT bo.borrower_id, bo.full_name, bo.category, bo.department,
            COUNT(br.id) total_borrows,
            SUM(br.status='Not Returned') active_books,
            MAX(br.borrow_date) last_borrow
            FROM borrowers bo
            LEFT JOIN borrow_records br ON bo.id=br.borrower_id
            GROUP BY bo.id ORDER BY total_borrows DESC LIMIT 50");
        $reportData = $stmt->fetchAll();
        break;
}

// For filter dropdowns
$bookCategories = $pdo->query("SELECT DISTINCT category FROM books ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Reports & Analytics</span>
    <div class="ms-auto d-flex gap-2 no-print">
      <a href="export.php?<?= http_build_query(array_merge($_GET,['type'=>$type])) ?>"
         class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export CSV</a>
      <button class="btn btn-outline-secondary btn-sm btn-print"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>

  <div class="page-content">
    <?= renderFlash() ?>

    <!-- Report type tabs -->
    <div class="card mb-4 no-print">
      <div class="card-body py-2 px-3">
        <nav class="nav nav-pills gap-1 flex-wrap">
          <?php
          $tabs = [
            'borrow_records'  => ['bi-arrow-left-right','Borrow Records'],
            'all_books'       => ['bi-book','All Books'],
            'overdue'         => ['bi-clock-history','Overdue Books'],
            'top_books'       => ['bi-trophy','Top Borrowed'],
            'active_borrowers'=> ['bi-people','Active Borrowers'],
          ];
          foreach ($tabs as $key => [$ico,$label]):
            $active = $type === $key ? 'active' : '';
            $href = '?'.http_build_query(array_merge($_GET,['type'=>$key]));
          ?>
          <a href="<?=$href?>" class="nav-link <?=$active?> py-1 px-3" style="font-size:.82rem">
            <i class="bi bi-<?=$ico?> me-1"></i><?=$label?>
          </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>

    <!-- Filters (not for top_books / active_borrowers) -->
    <?php if (in_array($type,['borrow_records','all_books','overdue'])): ?>
    <div class="card mb-4 no-print">
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="type" value="<?= $type ?>">
          <?php if ($type !== 'all_books'): ?>
          <div class="col-sm-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
          </div>
          <?php endif; ?>
          <div class="col-sm-2">
            <label class="form-label">Book Category</label>
            <select name="bcat" class="form-select">
              <option value="">All</option>
              <?php foreach ($bookCategories as $c): ?>
              <option value="<?=$c?>" <?= $bookCat===$c?'selected':'' ?>><?=$c?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($type === 'borrow_records'): ?>
          <div class="col-sm-2">
            <label class="form-label">Borrower Category</label>
            <select name="pcat" class="form-select">
              <option value="">All</option>
              <?php foreach(['Student','Teacher','Staff'] as $c): ?>
              <option value="<?=$c?>" <?= $borrowCat===$c?'selected':'' ?>><?=$c?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="Not Returned" <?= $status==='Not Returned'?'selected':'' ?>>Not Returned</option>
              <option value="Returned"     <?= $status==='Returned'?'selected':'' ?>>Returned</option>
            </select>
          </div>
          <?php endif; ?>
          <?php if ($type === 'all_books'): ?>
          <div class="col-sm-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="Available" <?= $status==='Available'?'selected':'' ?>>Available</option>
              <option value="Borrowed"  <?= $status==='Borrowed'?'selected':'' ?>>Borrowed</option>
              <option value="Missing"   <?= $status==='Missing'?'selected':'' ?>>Missing</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-auto">
            <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
            <a href="?type=<?=$type?>" class="btn btn-outline-secondary ms-1"><i class="bi bi-x-lg"></i></a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Print header -->
    <div class="d-none d-print-block mb-4 text-center">
      <h4 style="font-family:'Fraunces',serif"><?= APP_NAME ?> — <?= $reportTitle ?></h4>
      <p class="text-muted small">Generated: <?= date('d F Y H:i') ?></p>
      <hr>
    </div>

    <!-- Report table -->
    <div class="card">
      <div class="card-header">
        <i class="bi bi-table"></i> <?= $reportTitle ?>
        <span class="badge bg-secondary ms-2"><?= count($reportData) ?> records</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <?php if ($type === 'borrow_records'): ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Record ID</th><th>Book</th><th>Book Cat.</th><th>Borrower</th><th>Borrower Cat.</th><th>Borrow Date</th><th>Due Date</th><th>Return Date</th><th>Days</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reportData as $i => $r): ?>
            <tr <?= isOverdue($r['borrow_date'],$r['return_date'],$r['status'])?'class="table-warning"':'' ?>>
              <td><?=$i+1?></td>
              <td><code style="font-size:.75rem"><?= sanitize($r['record_id']) ?></code></td>
              <td><?= sanitize($r['title']) ?><div class="text-muted" style="font-size:.72rem"><?= sanitize($r['book_code']) ?></div></td>
              <td><?= sanitize($r['book_cat']) ?></td>
              <td><?= sanitize($r['borrower_name']) ?><div class="text-muted" style="font-size:.72rem"><?= sanitize($r['borrower_code']) ?></div></td>
              <td><?= badge($r['borrower_cat']) ?></td>
              <td><?= date('d M Y',strtotime($r['borrow_date'])) ?></td>
              <td><?= date('d M Y',strtotime($r['due_date'])) ?></td>
              <td><?= $r['return_date'] ? date('d M Y',strtotime($r['return_date'])) : '—' ?></td>
              <td><?= $r['days_borrowed'] ?></td>
              <td><?= badge($r['status']) ?><?= isOverdue($r['borrow_date'],$r['return_date'],$r['status']) ? ' <span class="badge bg-danger">Overdue</span>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

        <?php elseif ($type === 'all_books'): ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Book ID</th><th>Title</th><th>Author</th><th>Category</th><th>Shelf</th><th>Status</th><th>Condition</th><th>Added</th></tr></thead>
            <tbody>
            <?php foreach ($reportData as $i => $b): ?>
            <tr>
              <td><?=$i+1?></td>
              <td><code><?= sanitize($b['book_id']) ?></code></td>
              <td><?= sanitize($b['title']) ?></td>
              <td class="text-muted"><?= sanitize($b['author']) ?></td>
              <td><?= sanitize($b['category']) ?></td>
              <td><?= sanitize($b['shelf_location']??'—') ?></td>
              <td><?= badge($b['status']) ?></td>
              <td><?= badge($b['condition_status']) ?></td>
              <td><?= date('d M Y',strtotime($b['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

        <?php elseif ($type === 'overdue'): ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Record ID</th><th>Book</th><th>Borrower</th><th>Category</th><th>Phone</th><th>Borrow Date</th><th>Due Date</th><th>Days Out</th><th>Days Overdue</th></tr></thead>
            <tbody>
            <?php foreach ($reportData as $i => $r): ?>
            <tr class="table-danger">
              <td><?=$i+1?></td>
              <td><code><?= sanitize($r['record_id']) ?></code></td>
              <td><?= sanitize($r['title']) ?></td>
              <td><?= sanitize($r['borrower_name']) ?><div class="text-muted" style="font-size:.72rem"><?= sanitize($r['borrower_code']) ?></div></td>
              <td><?= badge($r['borrower_cat']) ?></td>
              <td><?= sanitize($r['phone']??'—') ?></td>
              <td><?= date('d M Y',strtotime($r['borrow_date'])) ?></td>
              <td><?= date('d M Y',strtotime($r['due_date'])) ?></td>
              <td class="fw-bold text-danger"><?= $r['days_borrowed'] ?></td>
              <td><span class="badge bg-danger"><?= $r['days_overdue'] ?> days</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

        <?php elseif ($type === 'top_books'): ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Book ID</th><th>Title</th><th>Author</th><th>Category</th><th>Total Borrows</th><th>Currently Out</th><th>Last Borrowed</th></tr></thead>
            <tbody>
            <?php foreach ($reportData as $i => $b): ?>
            <tr>
              <td><?=$i+1?></td>
              <td><code><?= sanitize($b['book_id']) ?></code></td>
              <td><?= sanitize($b['title']) ?></td>
              <td class="text-muted"><?= sanitize($b['author']) ?></td>
              <td><?= sanitize($b['category']) ?></td>
              <td><strong><?= $b['total_borrows'] ?></strong></td>
              <td><?= $b['currently_out'] ? badge('Borrowed') : '' ?></td>
              <td><?= $b['last_borrowed'] ? date('d M Y',strtotime($b['last_borrowed'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

        <?php elseif ($type === 'active_borrowers'): ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Borrower ID</th><th>Name</th><th>Category</th><th>Department</th><th>Total Borrows</th><th>Active Books</th><th>Last Borrow</th></tr></thead>
            <tbody>
            <?php foreach ($reportData as $i => $b): ?>
            <tr>
              <td><?=$i+1?></td>
              <td><code><?= sanitize($b['borrower_id']) ?></code></td>
              <td><?= sanitize($b['full_name']) ?></td>
              <td><?= badge($b['category']) ?></td>
              <td class="text-muted"><?= sanitize($b['department']??'—') ?></td>
              <td><strong><?= $b['total_borrows'] ?></strong></td>
              <td><?= $b['active_books'] > 0 ? "<span class='badge bg-warning text-dark'>{$b['active_books']}</span>" : '—' ?></td>
              <td><?= $b['last_borrow'] ? date('d M Y',strtotime($b['last_borrow'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if (!$reportData): ?>
          <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No data found for selected filters.</div>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
