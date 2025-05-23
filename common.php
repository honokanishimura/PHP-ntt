<?php

class Common
{
    public const APP_PATH = __DIR__;
    public const DB_PATH = 'C:/Users/西村保乃加/Downloads/sqlite-tools-win-x64-3490100/ntt_east.db';
    public const MECAB_PATH = 'C:/Program Files/MeCab/bin/mecab.exe';

    public const PAGE_TYPES = [
        1 => ['name' => '事例', 'json_path' => self::APP_PATH . '/_apps/jsons/cases.json'],
        2 => ['name' => 'コラム', 'json_path' => self::APP_PATH . '/_apps/jsons/columns.json'],
        3 => ['name' => '動画', 'json_path' => self::APP_PATH . '/_apps/jsons/movies.json'],
        4 => ['name' => '資料', 'json_path' => self::APP_PATH . '/_apps/jsons/resources.json'],
    ];

    public function loadDb(): ?PDO
    {
        try {
            $db = new PDO('sqlite:' . self::DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $db;
        } catch (PDOException $e) {
            die("データベース接続エラー: " . $e->getMessage());
        }
    }

    public function getMasterData($db, $tableName, $pageType = null)
    {
        $query = "SELECT * FROM {$tableName}";
        $params = [];

        if ($pageType !== null) {
            $query .= " WHERE page_type = :page_type";
            $params = [':page_type' => $pageType];
        }

        $query .= " ORDER BY sort_order ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function buildSearchWhereOnly($filters, $keyword, &$params): string
{
    $andClauses = [];

    // 🔹 各カテゴリをANDで分け、その中でOR条件
    foreach ($filters as $column => $values) {
        if (!empty($values)) {
            $orClauses = [];
            foreach ($values as $value) {
                $orClauses[] = "$column = ?";
                $params[] = $value;
            }
            $andClauses[] = '(' . implode(' OR ', $orClauses) . ')';
        }
    }

    // 🔹 キーワード（MeCab分かち書き + FTS検索）
    if (!empty($keyword)) {
        $wakati = $this->wakati($keyword);
        if (!empty($wakati)) {
            $terms = array_filter(explode(' ', $wakati));
            $ftsQuery = implode(' OR ', $terms); 

            

            $ftsClause = "(a.mt_entry_id IN (SELECT rowid FROM articles_fts WHERE search_text MATCH ?) 
                          OR a.mt_entry_id IN (SELECT rowid FROM articles_fts WHERE extra_text MATCH ?))";

            $andClauses[] = $ftsClause;
            $params[] = $ftsQuery;
            $params[] = $ftsQuery;
        }
    }

    return implode(' AND ', $andClauses);
}



    public function wakati($text): string
{
    // スペース整形
    $text = preg_replace('/[　\s]+/u', ' ', $text);
    $text = str_replace(['〜', '～'], '-', $text);
    $text = trim($text);

    // 一時ファイル作成
    $tempInput = tempnam(sys_get_temp_dir(), 'mecab_input_');
    $tempOutput = tempnam(sys_get_temp_dir(), 'mecab_output_');

    file_put_contents($tempInput, $text);

    // MeCabコマンド（パスはダブルクォートで囲む！）
    $mecab = '"' . self::MECAB_PATH . '"';
    $cmd = "$mecab -Owakati < \"$tempInput\" > \"$tempOutput\"";

    shell_exec($cmd);

    // 出力確認
    $output = file_exists($tempOutput) ? file_get_contents($tempOutput) : '';

    var_dump("🔍 MeCab分かち書き:", $output);


    // 後処理
    @unlink($tempInput);
    @unlink($tempOutput);

    return trim($output);
}



}
