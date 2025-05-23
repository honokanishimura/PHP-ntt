<?php
require_once 'common.php';

$common = new Common();
$db = $common->loadDb();

$pagetype = isset($_GET['type']) ? (int)$_GET['type'] : 1;

$services = $common->getMasterData($db, 'services', $pagetype);
$vendors  = $common->getMasterData($db, 'vendors', $pagetype);
$scales   = $common->getMasterData($db, 'scales', $pagetype);
$issues   = $common->getMasterData($db, 'issues', $pagetype);

// フィルター取得関数
function getFilterNames($key) {
    if (!isset($_GET[$key])) return [];
    $value = $_GET[$key];
    if (is_array($value)) return $value;
    if (is_string($value)) return explode('|', $value);
    return [];
}

$serviceNames = getFilterNames('sv');
$vendorNames  = getFilterNames('v');
$scaleNames   = getFilterNames('sc');
$issueNames   = getFilterNames('i');

// ✅ キーワード処理（全角/半角スペース統一）
$keyword = isset($_GET['k']) ? $_GET['k'] : '';
$keyword = preg_replace('/[ 　]+/u', ' ', $keyword); // ← 全角・半角スペースを半角スペースに統一
$keyword = trim($keyword);


$page = isset($_GET['p']) ? max((int)$_GET['p'], 1) : 1;
$perPage = 2;
$offset = ($page - 1) * $perPage;

$params = [];
if (!empty($serviceNames) || !empty($vendorNames) || !empty($scaleNames) || !empty($issueNames) || !empty($keyword)) {
    $where = $common->buildSearchWhereOnly([
        's.name' => $serviceNames,
        'v.name' => $vendorNames,
        'sc.name' => $scaleNames,
        'i.name' => $issueNames,
    ], $keyword, $params);

    $where = trim($where) ? "a.page_type = ? AND $where" : "a.page_type = ?";
    array_unshift($params, $pagetype);
} else {
    $where = "a.page_type = ?";
    $params = [$pagetype];
}



$query = "
    SELECT 
        a.mt_entry_id,
        a.title,
        a.contents,
        (
            SELECT GROUP_CONCAT(DISTINCT s2.name)
            FROM articles_services asv2
            JOIN services s2 ON asv2.service_id = s2.id AND s2.page_type = a.page_type
            WHERE asv2.mt_entry_id = a.mt_entry_id AND asv2.page_type = a.page_type
        ) AS services,
        (
            SELECT GROUP_CONCAT(DISTINCT v2.name)
            FROM articles_vendors av2
            JOIN vendors v2 ON av2.vendor_id = v2.id AND v2.page_type = a.page_type
            WHERE av2.mt_entry_id = a.mt_entry_id AND av2.page_type = a.page_type
        ) AS vendors,
        (
            SELECT GROUP_CONCAT(DISTINCT i2.name)
            FROM articles_issues ai2
            JOIN issues i2 ON ai2.issue_id = i2.id AND i2.page_type = a.page_type
            WHERE ai2.mt_entry_id = a.mt_entry_id AND ai2.page_type = a.page_type
        ) AS issues,
        (
            SELECT GROUP_CONCAT(DISTINCT sc2.name)
            FROM articles_scales asc2
            JOIN scales sc2 ON asc2.scale_id = sc2.id AND sc2.page_type = a.page_type
            WHERE asc2.mt_entry_id = a.mt_entry_id AND asc2.page_type = a.page_type
        ) AS scales
    FROM articles a
    LEFT JOIN articles_vendors av ON a.mt_entry_id = av.mt_entry_id AND a.page_type = av.page_type
    LEFT JOIN vendors v ON av.vendor_id = v.id AND v.page_type = a.page_type
    LEFT JOIN articles_services asv ON a.mt_entry_id = asv.mt_entry_id AND a.page_type = asv.page_type
    LEFT JOIN services s ON asv.service_id = s.id AND s.page_type = a.page_type
    LEFT JOIN articles_issues ai ON a.mt_entry_id = ai.mt_entry_id AND a.page_type = ai.page_type
    LEFT JOIN issues i ON ai.issue_id = i.id AND i.page_type = a.page_type
    LEFT JOIN articles_scales ascx ON a.mt_entry_id = ascx.mt_entry_id AND a.page_type = ascx.page_type
    LEFT JOIN scales sc ON ascx.scale_id = sc.id AND sc.page_type = a.page_type
    WHERE $where
    GROUP BY a.mt_entry_id
    ORDER BY a.mt_entry_id ASC
    LIMIT $perPage OFFSET $offset
";

$stmt = $db->prepare($query);


$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 件数取得
$countQuery = "
    SELECT COUNT(DISTINCT a.mt_entry_id)
    FROM articles a
    LEFT JOIN articles_vendors av ON a.mt_entry_id = av.mt_entry_id AND a.page_type = av.page_type
    LEFT JOIN vendors v ON av.vendor_id = v.id AND v.page_type = a.page_type
    LEFT JOIN articles_services asv ON a.mt_entry_id = asv.mt_entry_id AND a.page_type = asv.page_type
    LEFT JOIN services s ON asv.service_id = s.id AND s.page_type = a.page_type
    LEFT JOIN articles_issues ai ON a.mt_entry_id = ai.mt_entry_id AND a.page_type = ai.page_type
    LEFT JOIN issues i ON ai.issue_id = i.id AND i.page_type = a.page_type
    LEFT JOIN articles_scales ascx ON a.mt_entry_id = ascx.mt_entry_id AND a.page_type = ascx.page_type
    LEFT JOIN scales sc ON ascx.scale_id = sc.id AND sc.page_type = a.page_type
    WHERE $where
";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>検索フォーム</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script>
    function prepareForm() {
        // ✅ ユーザーが入力したキーワードを取得
        const keywordInput = document.getElementById('keyword_input');
        const hiddenField = document.getElementById('k_hidden');

        if (keywordInput && hiddenField) {
            const cleaned = keywordInput.value.trim().replace(/[\u3000\s]+/g, ' '); // 全角・半角スペース統一
            hiddenField.value = cleaned;
        }

        // ✅ 各マスタ項目のチェックボックス値をhiddenに渡す
        ['sv', 'v', 'sc', 'i'].forEach(function(key) {
            const boxes = document.querySelectorAll(`input[data-name="${key}"]`);
            const selected = Array.from(boxes).filter(b => b.checked).map(b => b.value);
            const hidden = document.getElementById(`${key}_input`);
            if (hidden) {
                if (selected.length > 0) {
                    hidden.name = key;
                    hidden.value = selected.join('|');
                } else {
                    hidden.removeAttribute('name');
                }
            }
        });
    }
    </script>
</head>
<body>
<div class="container mt-4">
    <h1>検索フォーム</h1>

    <div class="border rounded p-3 mb-4">
        <form action="index.php" method="get" onsubmit="prepareForm()">
            <!-- ✅ キーワード入力とhiddenフィールド -->
            <div class="form-group">
                <label>キーワード</label>
                <input type="text" id="keyword_input" class="form-control" placeholder="例: 電話 光回線">
                <input type="hidden" name="k" id="k_hidden" value="<?php echo htmlspecialchars($keyword); ?>">
            </div>

            <!-- Hidden for master filters -->
            <input type="hidden" id="sv_input">
            <input type="hidden" id="v_input">
            <input type="hidden" id="sc_input">
            <input type="hidden" id="i_input">

            <!-- サービス -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">サービス</div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($services as $service): ?>
                            <div class="col-md-3">
                                <label>
                                    <input type="checkbox" data-name="sv" value="<?php echo $service['name']; ?>" <?php if (in_array($service['name'], $serviceNames)) echo 'checked'; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ベンダー -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">ベンダー</div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($vendors as $vendor): ?>
                            <div class="col-md-3">
                                <label>
                                    <input type="checkbox" data-name="v" value="<?php echo $vendor['name']; ?>" <?php if (in_array($vendor['name'], $vendorNames)) echo 'checked'; ?>>
                                    <?php echo htmlspecialchars($vendor['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- スケール -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">スケール</div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($scales as $scale): ?>
                            <div class="col-md-3">
                                <label>
                                    <input type="checkbox" data-name="sc" value="<?php echo $scale['name']; ?>" <?php if (in_array($scale['name'], $scaleNames)) echo 'checked'; ?>>
                                    <?php echo htmlspecialchars($scale['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 課題 -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">課題</div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($issues as $issue): ?>
                            <div class="col-md-3">
                                <label>
                                    <input type="checkbox" data-name="i" value="<?php echo $issue['name']; ?>" <?php if (in_array($issue['name'], $issueNames)) echo 'checked'; ?>>
                                    <?php echo htmlspecialchars($issue['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <input type="submit" class="btn btn-primary" value="検索">
        </form>
    </div>

    <!-- 検索結果 -->
    <p>取得された記事数：<?php echo $totalCount; ?></p>

    <?php if ($articles): ?>
        <?php foreach ($articles as $article): ?>
            <div class="border rounded p-3 mb-3">
                <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($article['mt_entry_id']); ?></p>
                <p><strong>コンテンツ:</strong> <?php echo htmlspecialchars($article['contents']); ?></p>
                <p><strong>サービス:</strong> <?php echo htmlspecialchars($article['services']); ?></p>
                <p><strong>ベンダー:</strong> <?php echo htmlspecialchars($article['vendors']); ?></p>
                <p><strong>課題:</strong> <?php echo htmlspecialchars($article['issues']); ?></p>
                <p><strong>スケール:</strong> <?php echo htmlspecialchars($article['scales']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>該当する記事はありませんでした。</p>
    <?php endif; ?>

    <!-- ページネーション -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="ページネーション">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page - 1])); ?>">◀前のページ</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page + 1])); ?>">次のページ▶</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>



<script>
window.addEventListener('DOMContentLoaded', () => {
    const url = new URL(window.location.href);
    const params = url.searchParams;

    if (params.has('k')) {
        // パラメータをデコードして再セット
        const raw = params.get('k');
        const clean = decodeURIComponent(raw);
        params.set('k', clean);

        // URLだけ書き換える（ページリロードしない）
        history.replaceState(null, '', url.pathname + '?' + params.toString());
    }
});
</script>

</body>
</html>
