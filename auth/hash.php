<?php
/**
 * LibraryMS — Password Hash Generator
 * Visit this file once to generate the correct hash, then delete it.
 * Usage: http://localhost/library-system/auth/hash.php?p=YourPassword
 */
$pass = $_GET['p'] ?? 'Pass123,';
echo '<pre>';
echo "Password: " . htmlspecialchars($pass) . "\n";
echo "Hash:     " . password_hash($pass, PASSWORD_BCRYPT) . "\n";

echo "Hash2:     " . password_hash($pass, PASSWORD_ARGON2ID) . "\n";

echo "Hash3:     " . password_hash($pass, PASSWORD_ARGON2I) . "\n";

echo "\nCopy the hash into your users table INSERT statement in database.sql\n";
echo '</pre>';
echo '<p style="color:red;font-weight:bold">⚠️ Delete this file after use!</p>';
