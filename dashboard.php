<?php
require_once __DIR__ . '/config/app.php';
requireLogin();

$pdo = getDB();
$pageTitle = 'Dashboard';

// ── Stats ────────────────────────────────────────────────────
$stats = $pdo->query("SELECT
    COUNT(*) total,
    SUM(status='Available')  available,
    SUM(status='Borrowed')   borrowed,
    SUM(status='Missing')    missing
FROM books")->fetch();

$totalBorrowers = $pdo->query("SELECT COUNT(*) FROM borrowers WHERE status='Active'")->fetchColumn();
$totalRecords   = $pdo->query("SELECT COUNT(*) FROM borrow_records")->fetchColumn();
$overdueCount   = $pdo->query("SELECT COUNT(*) FROM borrow_records
    WHERE status='Not Returned' AND DATEDIFF(CURDATE(), borrow_date) > " . BORROW_DAYS)->fetchColumn();

// ── Top 10 most borrowed ──────────────────────────────────────
$topBooks = $pdo->query("SELECT b.title, b.author, COUNT(br.id) cnt
    FROM borrow_records br JOIN books b ON br.book_id = b.id
    GROUP BY br.book_id ORDER BY cnt DESC LIMIT 10")->fetchAll();

// ── Monthly borrow trend (last 6 months) ─────────────────────
$trend = $pdo->query("SELECT DATE_FORMAT(borrow_date,'%b %Y') lbl, COUNT(*) cnt
    FROM borrow_records
    WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(borrow_date), MONTH(borrow_date)
    ORDER BY borrow_date")->fetchAll();

// ── Books by category ────────────────────────────────────────
$catData = $pdo->query("SELECT category, COUNT(*) cnt FROM books GROUP BY category ORDER BY cnt DESC LIMIT 8")->fetchAll();

// ── Recent borrow records ────────────────────────────────────
$recent = $pdo->query("SELECT br.*, b.title, b.book_id book_code, bo.full_name borrower_name,
        bo.category borrower_cat,
        DATEDIFF(CURDATE(), br.borrow_date) days_borrowed
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN borrowers bo ON br.borrower_id = bo.id
    ORDER BY br.created_at DESC LIMIT 8")->fetchAll();

// ── Overdue list ─────────────────────────────────────────────
$overdueList = $pdo->query("SELECT br.*, b.title, b.book_id book_code, bo.full_name borrower_name,
        DATEDIFF(CURDATE(), br.borrow_date) days_borrowed
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN borrowers bo ON br.borrower_id = bo.id
    WHERE br.status='Not Returned' AND DATEDIFF(CURDATE(), br.borrow_date) > " . BORROW_DAYS . "
    ORDER BY days_borrowed DESC LIMIT 5")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div id="main">
  <!-- Topbar -->
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Dashboard</span>
    <span class="text-muted small ms-auto d-none d-md-inline"><?= date('l, d F Y') ?></span>
  </div>

  <div class="page-content">
    <?= renderFlash() ?>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Books',       'value'=>$stats['total'],   'icon'=>'book',           'color'=>'#1a3a4a','bg'=>'#e8f0f5'],
        ['label'=>'Available',         'value'=>$stats['available'],'icon'=>'check-circle',  'color'=>'#198754','bg'=>'#d1f0e0'],
        ['label'=>'Borrowed',          'value'=>$stats['borrowed'], 'icon'=>'arrow-right-circle','color'=>'#f59e0b','bg'=>'#fef3c7'],
        ['label'=>'Missing',           'value'=>$stats['missing'],  'icon'=>'exclamation-circle','color'=>'#dc3545','bg'=>'#fde8e8'],
        ['label'=>'Active Borrowers',  'value'=>$totalBorrowers,   'icon'=>'people',         'color'=>'#6f42c1','bg'=>'#ede9f6'],
        ['label'=>'Total Records',     'value'=>$totalRecords,      'icon'=>'journal-text',   'color'=>'#0dcaf0','bg'=>'#d0f4fb'],
        ['label'=>'Overdue Books',     'value'=>$overdueCount,      'icon'=>'clock-history',  'color'=>'#fd7e14','bg'=>'#fde8d3'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-sm-4 col-lg-3 col-xl-auto flex-xl-fill">
        <div class="stat-card" style="--stat-color:<?= $c['color'] ?>;--stat-bg:<?= $c['bg'] ?>">
          <div class="stat-icon"><i class="bi bi-<?= $c['icon'] ?>"></i></div>
          <div>
            <div class="stat-num"><?= number_format((int)$c['value']) ?></div>
            <div class="stat-label"><?= $c['label'] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
      <!-- Borrow trend chart -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-graph-up text-accent"></i> Borrow Trend (Last 6 Months)</div>
          <div class="card-body"><div class="chart-wrap"><canvas id="trendChart"></canvas></div></div>
        </div>
      </div>
      <!-- Books by category pie -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-pie-chart text-accent"></i> Books by Category</div>
          <div class="card-body"><div class="chart-wrap"><canvas id="catChart"></canvas></div></div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Top borrowed books -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-trophy"></i> Top Borrowed Books</div>
          <div class="card-body p-0">
            <table class="table mb-0">
              <thead><tr><th>#</th><th>Title</th><th>Author</th><th class="text-end">Borrows</th></tr></thead>
              <tbody>
              <?php foreach ($topBooks as $i => $b): ?>
              <tr>
                <td><span class="badge" style="background:var(--brand);color:#fff"><?= $i+1 ?></span></td>
                <td><?= sanitize($b['title']) ?></td>
                <td class="text-muted"><?= sanitize($b['author']) ?></td>
                <td class="text-end"><strong><?= $b['cnt'] ?></strong></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$topBooks): ?><tr><td colspan="4" class="text-center text-muted py-3">No data yet</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Overdue alert -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header text-danger"><i class="bi bi-clock-history"></i> Overdue Books (Top 5)</div>
          <div class="card-body p-0">
            <table class="table mb-0">
              <thead><tr><th>Book</th><th>Borrower</th><th class="text-end">Days</th></tr></thead>
              <tbody>
              <?php foreach ($overdueList as $o): ?>
              <tr>
                <td><a href="<?= BASE_URL ?>/modules/borrow/index.php"><?= sanitize($o['title']) ?></a></td>
                <td><?= sanitize($o['borrower_name']) ?></td>
                <td class="text-end"><span class="badge bg-danger"><?= $o['days_borrowed'] ?> days</span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$overdueList): ?><tr><td colspan="3" class="text-center text-muted py-3">No overdue books 🎉</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($overdueCount > 5): ?>
          <div class="card-footer text-center">
            <a href="<?= BASE_URL ?>/modules/reports/index.php?type=overdue" class="small">View all <?= $overdueCount ?> overdue &rarr;</a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent borrow records -->
      <div class="col-12">
        <div class="card">
          <div class="card-header"><i class="bi bi-clock"></i> Recent Transactions</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table mb-0">
                <thead><tr><th>Record ID</th><th>Book</th><th>Borrower</th><th>Category</th><th>Borrow Date</th><th>Due Date</th><th>Days</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                  <td><code><?= sanitize($r['record_id']) ?></code></td>
                  <td><?= sanitize($r['title']) ?></td>
                  <td><?= sanitize($r['borrower_name']) ?></td>
                  <td><?= badge($r['borrower_cat']) ?></td>
                  <td><?= date('d M Y', strtotime($r['borrow_date'])) ?></td>
                  <td><?= date('d M Y', strtotime($r['due_date'])) ?></td>
                  <td><?= $r['days_borrowed'] ?></td>
                  <td><?= badge($r['status']) ?><?php if (isOverdue($r['borrow_date'], $r['return_date'], $r['status'])): ?> <span class="badge bg-danger">Overdue</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer text-end">
            <a href="<?= BASE_URL ?>/modules/borrow/index.php" class="small">View all records &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /.page-content -->
</div><!-- /#main -->

<?php require_once 'includes/footer.php'; ?>
<script>
// Trend chart
const trendLabels = <?= json_encode(array_column($trend,'lbl')) ?>;
const trendData   = <?= json_encode(array_column($trend,'cnt')) ?>;
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: trendLabels,
    datasets: [{
      label: 'Books Borrowed',
      data: trendData,
      backgroundColor: 'rgba(26,58,74,.7)',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

// Category pie
const catLabels = <?= json_encode(array_column($catData,'category')) ?>;
const catCounts = <?= json_encode(array_column($catData,'cnt')) ?>;
const catColors = ['#1a3a4a','#c8873a','#198754','#6f42c1','#0dcaf0','#fd7e14','#dc3545','#20c997'];
new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: {
    labels: catLabels,
    datasets: [{ data: catCounts, backgroundColor: catColors, borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
  }
});
</script>
