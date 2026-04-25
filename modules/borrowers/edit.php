<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$pageTitle = 'Edit Borrower';
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM borrowers WHERE id=?");
$stmt->execute([$id]);
$borrower = $stmt->fetch();
if (!$borrower) { flash('error','Borrower not found.'); redirect(BASE_URL.'/modules/borrowers/index.php'); }

$errors = []; $data = $borrower;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'borrower_id' => strtoupper(trim($_POST['borrower_id'] ?? '')),
        'full_name'   => trim($_POST['full_name'] ?? ''),
        'category'    => $_POST['category'] ?? '',
        'department'  => trim($_POST['department'] ?? ''),
        'phone'       => trim($_POST['phone'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'status'      => $_POST['status'] ?? 'Active',
    ];

    if (!$data['borrower_id']) $errors[] = 'Borrower ID is required.';
    if (!$data['full_name'])   $errors[] = 'Full name is required.';
    if (!$data['category'])    $errors[] = 'Category is required.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    $chk = $pdo->prepare("SELECT id FROM borrowers WHERE borrower_id=? AND id!=?");
    $chk->execute([$data['borrower_id'], $id]);
    if ($chk->fetch()) $errors[] = "Borrower ID already in use.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE borrowers SET borrower_id=?,full_name=?,category=?,department=?,phone=?,email=?,status=? WHERE id=?");
        $stmt->execute([...(array_values($data)), $id]);
        auditLog('update','borrowers',$id,'Updated: '.$data['full_name']);
        flash('success','Borrower updated successfully!');
        redirect(BASE_URL.'/modules/borrowers/index.php');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<div id="main">
  <div class="topbar">
    <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
    <span class="topbar-title">Edit Borrower</span>
    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="page-content">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="card" style="max-width:680px;margin:0 auto">
      <div class="card-header"><i class="bi bi-pencil"></i> Edit: <?= sanitize($borrower['full_name']) ?></div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Borrower ID *</label>
              <input type="text" name="borrower_id" class="form-control" required value="<?= sanitize($data['borrower_id']) ?>">
            </div>
            <div class="col-md-7">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" required value="<?= sanitize($data['full_name']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category *</label>
              <select name="category" class="form-select" required>
                <?php foreach(['Student','Teacher','Staff'] as $c): ?>
                <option value="<?=$c?>" <?= $data['category']===$c?'selected':'' ?>><?=$c?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Department</label>
              <input type="text" name="department" class="form-control" value="<?= sanitize($data['department']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= sanitize($data['phone']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']??'') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="Active"   <?= $data['status']==='Active'?'selected':'' ?>>Active</option>
                <option value="Inactive" <?= $data['status']==='Inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
              <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
