<?php
// 絶対パスを指定
$dbFilePath = 'C:/Users/西村保乃加/sqlite-tools-win-x64-3490100/ntt_east.db';

try {
    // SQLite接続
    $pdo = new PDO('sqlite:' . $dbFilePath);
    // エラーモード設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
?>
