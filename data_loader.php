<?php
// JSONファイルのフルパスを指定（ファイル名修正）
$jsonFile = __DIR__ . "/examples (3).json";

// ファイルが存在しない場合はエラー
if (!file_exists($jsonFile)) {
    die("Error: File not found at: " . $jsonFile);
}

// ファイル内容を読み込む
$fileContents = file_get_contents($jsonFile);
if ($fileContents === false) {
    die("Error: Could not read file contents");
}

// JSONを配列に変換
$jsonData = json_decode($fileContents, true);
if ($jsonData === null) {
    die("Error: JSON decode failed: " . json_last_error_msg());
}

// ここで使える状態にする（確認用に var_dump はあとで！）
