<?php
session_start();
if (!isset($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}
$db_conf = require __DIR__ . '/../inc/db.php';
$mysqli = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['pass'], $db_conf['name']);
if ($mysqli->connect_errno) {
    die('数据库连接失败: ' . $mysqli->connect_error);
}
require_once __DIR__ . '/../inc/functions.php';

// 获取Cloudflare账号列表
$res_acc = $mysqli->query("SELECT * FROM cloudflare_accounts ORDER BY id DESC");
$accounts = $res_acc ? $res_acc->fetch_all(MYSQLI_ASSOC) : [];

// 添加主域名
if (isset($_POST['domain'], $_POST['cf_account_id'])) {
    $domain = trim($_POST['domain']);
    $cf_account_id = intval($_POST['cf_account_id']);
    if ($domain && $cf_account_id) {
        $stmt = $mysqli->prepare("INSERT INTO domains (user_id, cf_account_id, domain) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $_SESSION['uid'], $cf_account_id, $domain);
        $stmt->execute();
    }
}

// 全部清理缓存
if (isset($_POST['clear_cache_all'])) {
    $res = $mysqli->query("SELECT * FROM domains");
    while ($d = $res->fetch_assoc()) {
        $res2 = $mysqli->query("SELECT c.api_token, c.zone_id FROM cloudflare_accounts c WHERE c.id=" . intval($d['cf_account_id']));
        $row = $res2 ? $res2->fetch_assoc() : null;
        if ($row) {
            cf_api_request('POST', "zones/{$row['zone_id']}/purge_cache", ["purge_everything"=>true], $row['api_token']);
        }
    }
    echo '<div class="alert alert-success">已全部清理缓存！</div>';
}

// 单个域名清理缓存
if (isset($_POST['clear_cache_domain'])) {
    $did = intval($_POST['clear_cache_domain']);
    $res = $mysqli->query("SELECT d.cf_account_id, c.api_token, c.zone_id FROM domains d LEFT JOIN cloudflare_accounts c ON d.cf_account_id = c.id WHERE d.id=$did");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        cf_api_request('POST', "zones/{$row['zone_id']}/purge_cache", ["purge_everything"=>true], $row['api_token']);
        echo '<div class="alert alert-success">已清理该域名缓存！</div>';
    }
}

// NS检测逻辑（用Cloudflare API）
if (isset($_GET['checkns']) && is_numeric($_GET['checkns'])) {
    $did = intval($_GET['checkns']);
    $res = $mysqli->query("SELECT d.domain, d.cf_account_id, c.api_token, c.zone_id FROM domains d LEFT JOIN cloudflare_accounts c ON d.cf_account_id = c.id WHERE d.id = $did");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $cf_token = $row['api_token'];
        $cf_zone = $row['zone_id'];
        $cf_info = cf_api_request('GET', "zones/$cf_zone", null, $cf_token);
        $cf_ns = [];
        if (!empty($cf_info['result']['name_servers'])) {
            $cf_ns = array_map('strtolower', $cf_info['result']['name_servers']);
        }
        $ok = !empty($cf_ns);
        $mysqli->query("UPDATE domains SET ns_status=".($ok?1:0)." WHERE id=$did");
    }
    header('Location: domains.php');
    exit;
}

// 搜索、筛选
$where = [];
if (!empty($_GET['cf_account_id'])) {
    $where[] = 'cf_account_id=' . intval($_GET['cf_account_id']);
}
if (!empty($_GET['q'])) {
    $q = $mysqli->real_escape_string($_GET['q']);
    $where[] = "domain LIKE '%$q%'";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$res = $mysqli->query("SELECT * FROM domains $where_sql ORDER BY id DESC");
$domains = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>域名管理 - Cloudflare解析系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include 'menu.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">主域名管理</div>
                <div class="card-body">
                    <form class="row g-3 mb-3" method="post">
                        <div class="col-auto">
                            <select name="cf_account_id" class="form-select" required>
                                <option value="">选择Cloudflare账号</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <input type="text" name="domain" class="form-control" placeholder="输入主域名" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">添加域名</button>
                        </div>
                    </form>
                    <form class="mb-3" method="post" style="display:inline-block">
                        <input type="hidden" name="clear_cache_all" value="1">
                        <button type="submit" class="btn btn-danger">全部清理缓存</button>
                    </form>
                    <form class="row g-3 mb-3" method="get">
                        <div class="col-auto">
                            <select name="cf_account_id" class="form-select">
                                <option value="">全部账号</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo $a['id']; ?>"<?php if(!empty($_GET['cf_account_id'])&&$_GET['cf_account_id']==$a['id'])echo' selected';?>><?php echo htmlspecialchars($a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <input type="text" name="q" class="form-control" placeholder="搜索域名" value="<?php echo htmlspecialchars($_GET['q']??''); ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-secondary">筛选/搜索</button>
                        </div>
                    </form>
                    <table class="table table-bordered table-hover">
                        <thead><tr><th>ID</th><th>域名</th><th>NS状态</th><th>操作</th><th>添加时间</th></tr></thead>
                        <tbody>
                        <?php foreach ($domains as $d): ?>
                            <tr>
                                <td><?php echo $d['id']; ?></td>
                                <td><?php echo htmlspecialchars($d['domain']); ?></td>
                                <td><?php echo $d['ns_status'] ? '<span class="text-success">已通过</span>' : '<span class="text-danger">未通过</span>'; ?></td>
                                <td>
                                    <a href="?checkns=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-primary">检测NS</a>
                                    <a href="domains.php?view解析=<?php echo $d['id']; ?>" class="btn btn-sm btn-info">查看解析</a>
                                    <a href="domains.php?add解析=<?php echo $d['id']; ?>" class="btn btn-sm btn-success">手动添加解析</a>
                                    <a href="custom_hostnames.php?domain_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-warning">SaaS自定义主机名</a>
                                    <form method="post" style="display:inline-block">
                                        <input type="hidden" name="clear_cache_domain" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">清理缓存</button>
                                    </form>
                                </td>
                                <td><?php echo $d['created_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    // 查看解析记录弹窗/区块
                    if (isset($_GET['view解析']) && is_numeric($_GET['view解析'])) {
                        $did = intval($_GET['view解析']);
                        $res = $mysqli->query("SELECT * FROM subdomains WHERE domain_id=$did ORDER BY id DESC");
                        echo '<div class="alert alert-info"><b>解析记录：</b><ul>';
                        while($row = $res->fetch_assoc()) {
                            echo '<li>' . htmlspecialchars($row['subdomain']) . ' [' . htmlspecialchars($row['type']) . '] ' . htmlspecialchars($row['value']) . '</li>';
                        }
                        echo '</ul><a href="domains.php" class="btn btn-sm btn-secondary">关闭</a></div>';
                    }
                    // 手动添加解析表单
                    if (isset($_GET['add解析']) && is_numeric($_GET['add解析'])) {
                        $did = intval($_GET['add解析']);
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subdomain'], $_POST['type'], $_POST['value'])) {
                            $subdomain = trim($_POST['subdomain']);
                            $type = strtoupper(trim($_POST['type']));
                            $value = trim($_POST['value']);
                            $stmt = $mysqli->prepare("INSERT INTO subdomains (domain_id, subdomain, type, value) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param('isss', $did, $subdomain, $type, $value);
                            if (!$stmt->execute()) {
                                echo '<div class="alert alert-danger">数据库错误：' . $stmt->error . '</div>';
                            } else {
                                echo '<div class="alert alert-success">添加成功！<a href="domains.php?view解析=' . $did . '" class="btn btn-sm btn-info">查看解析</a></div>';
                            }
                        }
                        echo '<form method="post" class="alert alert-warning"><b>手动添加解析</b><br>主机记录：<input name="subdomain" required> 类型：<select name="type"><option>CNAME</option><option>TXT</option><option>A</option><option>@</option></select> 记录值：<input name="value" required> <button class="btn btn-sm btn-success">添加</button> <a href="domains.php" class="btn btn-sm btn-secondary">取消</a></form>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>