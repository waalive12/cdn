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

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>管理后台 - Cloudflare解析系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Cloudflare解析系统</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">管理员：<?php echo htmlspecialchars($username); ?></span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">退出</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action active">仪表盘</a>
                <a href="domains.php" class="list-group-item list-group-item-action">域名管理</a>
                <a href="subdomains.php" class="list-group-item list-group-item-action">二级域名管理</a>
                <a href="settings.php" class="list-group-item list-group-item-action">系统设置</a>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">欢迎使用 Cloudflare 解析系统</div>
                <div class="card-body">
                    <h5>功能总览</h5>
                    <ul>
                        <li>批量添加主域名、二级域名</li>
                        <li>Cloudflare自定义主机名自动解析</li>
                        <li>批量/随机/固定前缀二级域名生成</li>
                        <li>NS、CNAME、TXT解析检测</li>
                        <li>套餐管理、系统设置</li>
                    </ul>
                    <p class="text-muted">请通过左侧菜单进入各功能页面。</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
