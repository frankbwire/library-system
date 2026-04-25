<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM borrowers WHERE id=?");
$stmt->execute([$id]);
$borrower = $stmt->fetch();
if (!$borrower) { flash('error','Borrower not found.'); redirect(BASE_URL.'/modules/borrowers/index.php'); }

$pageTitle = 'History: '.$borrower['full_name'];

$records = $pdo->prepare("SELECT br.*, b.title, b.book_id book_code, b.author,
    DATEDIFF(COALESCE(br.return_date, CURDATE()), br.borrow_date) days_borrowed
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE br.borrower_id = ?
    ORDER BY br.borrow_date DESC");
$records->execute([$id]);
$records = $records->fetchAll();

$totalBorrows  = count($records);
$totalReturned = count(array_filter($records, fn($r) => $r['status'] === 'Returned'));
$activeNow     = $totalBorrows - $totalReturned;
$overdueCount  = count(array_filter($records, fn($r) => isOverdue($r['borrow_date'], $r['return_date'], $r['status'])));

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Borrower History</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="page-content">
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="mb-3" style="font-family:'Fraunces',serif;font-size:1.1rem"><?= sanitize($borrower['full_name']) ?></h6>
            <div class="row g-2 text-sm">
              <div class="col-6"><span class="text-muted">ID:</span> <code><?= sanitize($borrower['borrower_id']) ?></code></div>
              <div class="col-6"><span class="text-muted">Category:</span> <?= badge($borrower['category']) ?></div>
              <div class="col-6"><span class="text-muted">Department:</span> <?= sanitize($borrower['department']??'—') ?></div>
              <div class="col-6"><span class="text-muted">Status:</span> <?= badge($borrower['status']) ?></div>
              <div class="col-6"><span class="text-muted">Phone:</span> <?= sanitize($borrower['phone']??'—') ?></div>
              <div class="col-6"><span class="text-muted">Email:</span> <?= sanitize($borrower['email']??'—') ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="row g-3 h-100">
          <?php
          $miniCards = [
            ['Total Borrows','bi-journal-text',$totalBorrows,'#1a3a4a','#e8f0f5'],
            ['Returned',     'bi-check-circle',$totalReturned,'#198754','#d1f0e0'],
            ['Active Now',   'bi-arrow-right-circle',$activeNow,'#f59e0b','#fef3c7'],
            ['Overdue',      'bi-clock-history',$overdueCount,'#dc3545','#fde8e8'],
          ];
          foreach ($miniCards as [$lbl,$ico,$val,$col,$bg]): ?>
          <div class="col-6">
            <div class="stat-card py-3" style="--stat-color:<?=$col?>;--stat-bg:<?=$bg?>">
              <div class="stat-icon" style="width:40px;height:40px;font-size:1.1rem"><i class="bi <?=$ico?>"></i></div>
              <div><div class="stat-num" style="font-size:1.4rem"><?=$val?></div><div class="stat-label"><?=$lbl?></div></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history"></i> Borrow History</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>#</th><th>Record ID</th><th>Book</th><th>Borrow Date</th><th>Due Date</th><th>Return Date</th><th>Days</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if ($records): ?>
            <?php foreach ($records as $i => $r): ?>
            <tr <?= isOverdue($r['borrow_date'],$r['return_date'],$r['status']) ? 'class="table-danger"' : '' ?>>
              <td><?= $i+1 ?></td>
              <td><code><?= sanitize($r['record_id']) ?></code></td>
              <td>
                <strong><?= sanitize($r['title']) ?></strong>
                <div class="text-muted" style="font-size:.75rem"><?= sanitize($r['author']) ?></div>
              </td>
              <td><?= date('d M Y',strtotime($r['borrow_date'])) ?></td>
              <td><?= date('d M Y',strtotime($r['due_date'])) ?></td>
              <td><?= $r['return_date'] ? date('d M Y',strtotime($r['return_date'])) : '—' ?></td>
              <td><?= $r['days_borrowed'] ?></td>
              <td>
                <?= badge($r['status']) ?>
                <?php if (isOverdue($r['borrow_date'],$r['return_date'],$r['status'])): ?>
                  <span class="badge bg-danger">Overdue</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No borrow history found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
