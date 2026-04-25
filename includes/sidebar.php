<?php
$cp  = basename($_SERVER['PHP_SELF']);
$cd  = basename(dirname($_SERVER['PHP_SELF']));
function navCls(array $pages, string $dir=''): string {
    global $cp,$cd;
    foreach($pages as $p) if($cp===$p||$cd===$p) return 'active';
    return '';
}
?>
<nav id="sidebar">
  <div class="sidebar-brand">
    <a href="<?=BASE_URL?>/dashboard.php">
      <i class="bi bi-book-half"></i>
      <span><?=APP_NAME?></span>
    </a>
    <button class="d-lg-none btn btn-sm ms-auto" id="sidebarClose" style="color:#fff;background:transparent;border:none">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="sidebar-user">
    <div class="s-avatar"><i class="bi bi-person-circle"></i></div>
    <div>
      <div class="s-name"><?=sanitize(currentUser()['full_name']??'User')?></div>
      <div class="s-role"><?=ucfirst(currentUser()['role']??'')?></div>
    </div>
  </div>

  <div class="sidebar-section">Main</div>
  <a href="<?=BASE_URL?>/dashboard.php" class="s-link <?=navCls(['dashboard.php'])?>">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>

  <div class="sidebar-section">Library</div>
  <a href="<?=BASE_URL?>/modules/books/index.php" class="s-link <?=navCls(['index.php','add.php','edit.php'],'books')?>">
    <i class="bi bi-book"></i> Books
  </a>
  <a href="<?=BASE_URL?>/modules/borrowers/index.php" class="s-link <?=navCls(['index.php','add.php','edit.php'],'borrowers')?>">
    <i class="bi bi-people"></i> Borrowers
  </a>
  <a href="<?=BASE_URL?>/modules/borrow/index.php" class="s-link <?=navCls(['index.php','issue.php','return.php'],'borrow')?>">
    <i class="bi bi-arrow-left-right"></i> Borrow & Return
  </a>

  <div class="sidebar-section">Analytics</div>
  <a href="<?=BASE_URL?>/modules/reports/index.php" class="s-link <?=navCls(['index.php'],'reports')?>">
    <i class="bi bi-bar-chart-line"></i> Reports
  </a>

  <div class="sidebar-section">System</div>
  <a href="<?=BASE_URL?>/auth/logout.php" class="s-link">
    <i class="bi bi-box-arrow-right"></i> Logout
  </a>
</nav>
<div id="sidebarOverlay" class="sidebar-overlay d-lg-none"></div>
