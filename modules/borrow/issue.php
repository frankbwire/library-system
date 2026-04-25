<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Issue Book';
$pdo = getDB();
$errors = []; $data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId     = (int)($_POST['book_id'] ?? 0);
    $borrowerId = (int)($_POST['borrower_id'] ?? 0);
    $borrowDate = trim($_POST['borrow_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$bookId)     $errors[] = 'Please select a book.';
    if (!$borrowerId) $errors[] = 'Please select a borrower.';
    if (!$borrowDate) $errors[] = 'Borrow date is required.';

    if (!$errors) {
        // Check book status
        $book = $pdo->prepare("SELECT * FROM books WHERE id=?");
        $book->execute([$bookId]);
        $book = $book->fetch();

        if (!$book) {
            $errors[] = 'Book not found.';
        } elseif ($book['status'] !== 'Available') {
            $errors[] = "This book is currently <strong>{$book['status']}</strong> and cannot be borrowed.";
        } else {
            // Check borrower
            $borrower = $pdo->prepare("SELECT * FROM borrowers WHERE id=? AND status='Active'");
            $borrower->execute([$borrowerId]);
            $borrower = $borrower->fetch();
            if (!$borrower) $errors[] = 'Borrower not found or is inactive.';
        }
    }

    if (!$errors) {
        $dueDate  = date('Y-m-d', strtotime($borrowDate . ' +' . BORROW_DAYS . ' days'));
        $recordId = 'REC-' . str_pad((int)$pdo->query("SELECT COUNT(*)+1 FROM borrow_records")->fetchColumn(), 4, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        try {
            // Insert borrow record
            $ins = $pdo->prepare("INSERT INTO borrow_records(record_id,book_id,borrower_id,borrow_date,due_date,notes,status) VALUES(?,?,?,?,?,?,'Not Returned')");
            $ins->execute([$recordId, $bookId, $borrowerId, $borrowDate, $dueDate, $notes]);
            $newId = (int)$pdo->lastInsertId();

            // Update book status
            $pdo->prepare("UPDATE books SET status='Borrowed' WHERE id=?")->execute([$bookId]);

            $pdo->commit();
            auditLog('borrow','borrow_records',$newId,"Issued book ID:{$bookId} to borrower ID:{$borrowerId}");
            flash('success',"Book issued successfully! Record ID: <strong>{$recordId}</strong>. Due: " . date('d M Y', strtotime($dueDate)));
            redirect(BASE_URL.'/modules/borrow/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

// Available books
$availableBooks = $pdo->query("SELECT id, book_id, title, author FROM books WHERE status='Available' ORDER BY title")->fetchAll();
// Active borrowers
$activeBorrowers = $pdo->query("SELECT id, borrower_id, full_name, category, department FROM borrowers WHERE status='Active' ORDER BY full_name")->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Issue Book</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left me-1"></i>Back to Records</a>
  </div>
  <div class="page-content">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><i class="bi bi-book-fill"></i> Issue New Book</div>
          <div class="card-body">
            <form method="POST" id="issueForm">
              <div class="mb-3">
                <label class="form-label">Select Book *</label>
                <select name="book_id" id="bookSelect" class="form-select" required>
                  <option value="">— Choose an available book —</option>
                  <?php foreach ($availableBooks as $b): ?>
                  <option value="<?= $b['id'] ?>"
                          data-id="<?= sanitize($b['book_id']) ?>"
                          data-author="<?= sanitize($b['author']) ?>"
                          <?= isset($_POST['book_id']) && $_POST['book_id'] == $b['id'] ? 'selected' : '' ?>>
                    [<?= sanitize($b['book_id']) ?>] <?= sanitize($b['title']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <div id="bookInfo" class="form-text mt-1"></div>
              </div>
              <div class="mb-3">
                <label class="form-label">Select Borrower *</label>
                <select name="borrower_id" id="borrowerSelect" class="form-select" required>
                  <option value="">— Choose a borrower —</option>
                  <?php foreach ($activeBorrowers as $b): ?>
                  <option value="<?= $b['id'] ?>"
                          data-id="<?= sanitize($b['borrower_id']) ?>"
                          data-cat="<?= sanitize($b['category']) ?>"
                          data-dept="<?= sanitize($b['department'] ?? '') ?>"
                          <?= isset($_POST['borrower_id']) && $_POST['borrower_id'] == $b['id'] ? 'selected' : '' ?>>
                    [<?= sanitize($b['borrower_id']) ?>] <?= sanitize($b['full_name']) ?> (<?= $b['category'] ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <div id="borrowerInfo" class="form-text mt-1"></div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Borrow Date *</label>
                  <input type="date" name="borrow_date" class="form-control" required
                         value="<?= $_POST['borrow_date'] ?? date('Y-m-d') ?>"
                         max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Due Date (auto)</label>
                  <input type="text" id="dueDateDisplay" class="form-control" readonly
                         style="background:#f8f6f2" value="<?= date('d M Y', strtotime('+'.BORROW_DAYS.' days')) ?>">
                </div>
              </div>
              <div class="mb-4">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes…"><?= sanitize($_POST['notes'] ?? '') ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-right-circle me-1"></i>Issue Book
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card mb-3">
          <div class="card-header"><i class="bi bi-info-circle"></i> Loan Policy</div>
          <div class="card-body">
            <ul class="list-unstyled mb-0 small">
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Loan period: <strong><?= BORROW_DAYS ?> days</strong></li>
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Only <strong>Available</strong> books can be issued</li>
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Borrower must be <strong>Active</strong></li>
              <li class="mb-2"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Books not returned after <?= BORROW_DAYS ?> days are <strong>Overdue</strong></li>
            </ul>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><i class="bi bi-bar-chart"></i> Quick Stats</div>
          <div class="card-body">
            <?php
            $qs = $pdo->query("SELECT
                (SELECT COUNT(*) FROM books WHERE status='Available') avail,
                (SELECT COUNT(*) FROM borrowers WHERE status='Active') actBorr,
                (SELECT COUNT(*) FROM borrow_records WHERE status='Not Returned') activeRec,
                (SELECT COUNT(*) FROM borrow_records WHERE status='Not Returned' AND DATEDIFF(CURDATE(),borrow_date)>".BORROW_DAYS.") overdue")->fetch();
            ?>
            <div class="row g-2 text-center small">
              <div class="col-6 p-2 rounded" style="background:#d1f0e0">
                <div class="fw-bold fs-5"><?= $qs['avail'] ?></div><div class="text-muted">Available Books</div>
              </div>
              <div class="col-6 p-2 rounded" style="background:#e8f0f5">
                <div class="fw-bold fs-5"><?= $qs['actBorr'] ?></div><div class="text-muted">Active Borrowers</div>
              </div>
              <div class="col-6 p-2 rounded" style="background:#fef3c7">
                <div class="fw-bold fs-5"><?= $qs['activeRec'] ?></div><div class="text-muted">Books Out</div>
              </div>
              <div class="col-6 p-2 rounded" style="background:#fde8e8">
                <div class="fw-bold fs-5"><?= $qs['overdue'] ?></div><div class="text-muted">Overdue</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
<script>
const BORROW_DAYS = <?= BORROW_DAYS ?>;
// Show book details on selection
document.getElementById('bookSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('bookInfo').innerHTML =
            '<i class="bi bi-book me-1"></i>ID: <strong>'+opt.dataset.id+'</strong> &nbsp;|&nbsp; Author: '+opt.dataset.author;
    } else { document.getElementById('bookInfo').innerHTML = ''; }
});
// Show borrower details
document.getElementById('borrowerSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('borrowerInfo').innerHTML =
            '<i class="bi bi-person me-1"></i>ID: <strong>'+opt.dataset.id+'</strong> | '+opt.dataset.cat+' | '+opt.dataset.dept;
    } else { document.getElementById('borrowerInfo').innerHTML = ''; }
});
// Auto calculate due date
document.querySelector('[name="borrow_date"]').addEventListener('change', function() {
    if (this.value) {
        const d = new Date(this.value);
        d.setDate(d.getDate() + BORROW_DAYS);
        document.getElementById('dueDateDisplay').value = d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
    }
});
</script>
