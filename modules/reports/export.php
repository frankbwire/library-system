<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$type      = $_GET['type']   ?? 'borrow_records';
$dateFrom  = $_GET['from']   ?? '';
$dateTo    = $_GET['to']     ?? '';
$bookCat   = $_GET['bcat']   ?? '';
$borrowCat = $_GET['pcat']   ?? '';
$status    = $_GET['status'] ?? '';
$search    = $_GET['q']      ?? '';

$pdo = getDB();
$rows = []; $headers = []; $filename = '';

switch ($type) {
    case 'borrow_records': {
        $filename = 'borrow_records_' . date('Ymd');
        $headers  = ['Record ID','Book ID','Title','Book Category','Borrower ID','Borrower','Borrower Category','Borrow Date','Due Date','Return Date','Days Borrowed','Status','Overdue'];
        $where=[]; $params=[];
        if ($dateFrom)  { $where[] = 'br.borrow_date>=?'; $params[] = $dateFrom; }
        if ($dateTo)    { $where[] = 'br.borrow_date<=?'; $params[] = $dateTo; }
        if ($bookCat)   { $where[] = 'b.category=?';      $params[] = $bookCat; }
        if ($borrowCat) { $where[] = 'bo.category=?';     $params[] = $borrowCat; }
        if ($status)    { $where[] = 'br.status=?';       $params[] = $status; }
        if ($search)    { $where[] = '(b.title LIKE ? OR bo.full_name LIKE ?)'; $params=array_merge($params,["%$search%","%$search%"]); }
        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT br.record_id, b.book_id, b.title, b.category bcat,
            bo.borrower_id, bo.full_name, bo.category pcat,
            br.borrow_date, br.due_date, br.return_date,
            DATEDIFF(COALESCE(br.return_date,CURDATE()),br.borrow_date) days_borrowed, br.status
            FROM borrow_records br JOIN books b ON br.book_id=b.id JOIN borrowers bo ON br.borrower_id=bo.id $w ORDER BY br.borrow_date DESC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $overdue = ($r['status']==='Not Returned' && (int)$r['days_borrowed'] > BORROW_DAYS) ? 'Yes' : 'No';
            $rows[] = [$r['record_id'],$r['book_id'],$r['title'],$r['bcat'],$r['borrower_id'],$r['full_name'],
                       $r['pcat'],$r['borrow_date'],$r['due_date'],$r['return_date']??'',$r['days_borrowed'],$r['status'],$overdue];
        }
        break;
    }
    case 'all_books': {
        $filename = 'books_inventory_' . date('Ymd');
        $headers  = ['Book ID','Title','Author','Category','Shelf Location','Status','Condition','Date Added'];
        $where=[]; $params=[];
        if ($bookCat) { $where[] = "category=?"; $params[] = $bookCat; }
        if ($status)  { $where[] = "status=?";   $params[] = $status; }
        if ($search)  { $where[] = "(title LIKE ? OR author LIKE ?)"; $params=array_merge($params,["%$search%","%$search%"]); }
        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT book_id,title,author,category,shelf_location,status,condition_status,DATE(created_at) FROM books $w ORDER BY category,title");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;
    }
    case 'overdue': {
        $filename = 'overdue_books_' . date('Ymd');
        $headers  = ['Record ID','Book ID','Title','Borrower ID','Borrower Name','Category','Phone','Borrow Date','Due Date','Days Out','Days Overdue'];
        $stmt = $pdo->query("SELECT br.record_id,b.book_id,b.title,bo.borrower_id,bo.full_name,bo.category,bo.phone,
            br.borrow_date,br.due_date,
            DATEDIFF(CURDATE(),br.borrow_date) days_borrowed,
            DATEDIFF(CURDATE(),br.due_date) days_overdue
            FROM borrow_records br JOIN books b ON br.book_id=b.id JOIN borrowers bo ON br.borrower_id=bo.id
            WHERE br.status='Not Returned' AND DATEDIFF(CURDATE(),br.borrow_date)>".BORROW_DAYS."
            ORDER BY days_overdue DESC");
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['record_id'],$r['book_id'],$r['title'],$r['borrower_id'],$r['full_name'],
                       $r['category'],$r['phone']??'',$r['borrow_date'],$r['due_date'],$r['days_borrowed'],$r['days_overdue']];
        }
        break;
    }
    case 'top_books': {
        $filename = 'top_borrowed_books_' . date('Ymd');
        $headers  = ['Rank','Book ID','Title','Author','Category','Total Borrows','Currently Out'];
        $stmt = $pdo->query("SELECT b.book_id,b.title,b.author,b.category,COUNT(br.id) total,SUM(br.status='Not Returned') out_now
            FROM books b LEFT JOIN borrow_records br ON b.id=br.book_id
            GROUP BY b.id ORDER BY total DESC LIMIT 100");
        foreach ($stmt->fetchAll() as $i => $r) {
            $rows[] = [$i+1,$r['book_id'],$r['title'],$r['author'],$r['category'],$r['total'],$r['out_now']];
        }
        break;
    }
    case 'active_borrowers': {
        $filename = 'active_borrowers_' . date('Ymd');
        $headers  = ['Rank','Borrower ID','Name','Category','Department','Total Borrows','Active Books'];
        $stmt = $pdo->query("SELECT bo.borrower_id,bo.full_name,bo.category,bo.department,COUNT(br.id) total,SUM(br.status='Not Returned') active_now
            FROM borrowers bo LEFT JOIN borrow_records br ON bo.id=br.borrower_id
            GROUP BY bo.id ORDER BY total DESC LIMIT 100");
        foreach ($stmt->fetchAll() as $i => $r) {
            $rows[] = [$i+1,$r['borrower_id'],$r['full_name'],$r['category'],$r['department']??'',$r['total'],$r['active_now']];
        }
        break;
    }
}

// Stream CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
$out = fopen('php://output','w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
// Title row
fputcsv($out, [APP_NAME . ' — ' . ucwords(str_replace('_',' ',$type)), 'Generated: '.date('d F Y H:i')]);
fputcsv($out, []);
fputcsv($out, $headers);
foreach ($rows as $row) fputcsv($out, $row);
fputcsv($out, []);
fputcsv($out, ['Total Records:', count($rows)]);
fclose($out);
exit;
