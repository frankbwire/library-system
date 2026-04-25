<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Return Book';
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

// Load record
$stmt = $pdo->prepare("SELECT br.*, b.title, b.book_id book_code, b.id bid,
    bo.full_name borrower_name, bo.borrower_id borrower_code, bo.category borrower_cat
    FROM borrow_records br
    JOIN books b ON br.book_id=b.id
    JOIN borrowers bo ON br.borrower_id=bo.id
    WHERE br.id=?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) { flash('error','Record not found.'); redirect(BASE_URL.'/modules/borrow/index.php'); }
if ($record['status'] === 'Returned') { flash('error','This book has already been returned.'); redirect(BASE_URL.'/modules/borrow/index.php'); }

$errors = [];
$daysBorrowed = daysBorrowed($record['borrow_date'], null);
$isOverdueNow = isOverdue($record['borrow_date'], null, 'Not Returned');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnDate = trim($_POST['return_date'] ?? date('Y-m-d'));
    $notes      = trim($_POST['notes'] ?? '');

    if (!$returnDate) $errors[] = 'Return date is required.';
    if ($returnDate < $record['borrow_date']) $errors[] = 'Return date cannot be before borrow date.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Update record
            $upd = $pdo->prepare("UPDATE borrow_records SET status='Returned', return_date=?, notes=? WHERE id=?");
            $upd->execute([$returnDate, $notes, $id]);

            // Update book status back to Available
            $pdo->prepare("UPDATE books SET status='Available' WHERE id=?")->execute([$record['bid']]);

            $pdo->commit();
            auditLog('return','borrow_records',$id,"Returned book: ".$record['book_code']);
            flash('success',"Book <strong>{$record['title']}</strong> returned successfully on " . date('d M Y', strtotime($returnDate)) . ".");
            redirect(BASE_URL.'/modules/borrow/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Return Book</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="page-content">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
      <div class="col-lg-7">

        <?php if ($isOverdueNow): ?>
        <div class="alert alert-danger d-flex gap-2 align-items-center mb-4">
          <i class="bi bi-clock-history fs-4"></i>
          <div>
            <strong>Overdue Notice</strong><br>
            This book was due on <strong><?= date('d M Y',strtotime($record['due_date'])) ?></strong>.
            It has been out for <strong><?= $daysBorrowed ?> days</strong>
            (<?= $daysBorrowed - BORROW_DAYS ?> days overdue).
          </div>
        </div>
        <?php endif; ?>

        <!-- Record summary -->
        <div class="card mb-4">
          <div class="card-header"><i class="bi bi-receipt"></i> Borrow Details</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label">Book</label>
                <div class="fw-semibold"><?= sanitize($record['title']) ?></div>
                <div class="text-muted small"><code><?= sanitize($record['book_code']) ?></code></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label">Borrower</label>
                <div class="fw-semibold"><?= sanitize($record['borrower_name']) ?></div>
                <div class="text-muted small"><code><?= sanitize($record['borrower_code']) ?></code> — <?= sanitize($record['borrower_cat']) ?></div>
              </div>
              <div class="col-sm-4">
                <label class="form-label">Record ID</label>
                <div><code><?= sanitize($record['record_id']) ?></code></div>
              </div>
              <div class="col-sm-4">
                <label class="form-label">Borrow Date</label>
                <div><?= date('d M Y',strtotime($record['borrow_date'])) ?></div>
              </div>
              <div class="col-sm-4">
                <label class="form-label">Due Date</label>
                <div class="<?= $isOverdueNow ? 'text-danger fw-bold' : '' ?>">
                  <?= date('d M Y',strtotime($record['due_date'])) ?>
                  <?= $isOverdueNow ? '<span class="badge bg-danger ms-1">Overdue</span>' : '' ?>
                </div>
              </div>
              <div class="col-sm-4">
                <label class="form-label">Days Borrowed</label>
                <div class="<?= $isOverdueNow ? 'text-danger fw-bold' : '' ?>"><?= $daysBorrowed ?> days</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Return form -->
        <div class="card">
          <div class="card-header text-success"><i class="bi bi-arrow-return-left"></i> Process Return</div>
          <div class="card-body">
            <form method="POST">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Return Date *</label>
                  <input type="date" name="return_date" class="form-control" required
                         value="<?= $_POST['return_date'] ?? date('Y-m-d') ?>"
                         min="<?= $record['borrow_date'] ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Notes (optional)</label>
                  <textarea name="notes" class="form-control" rows="2"
                            placeholder="Condition on return, damages, etc."><?= sanitize($record['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                  <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>Confirm Return
                  </button>
                  <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
