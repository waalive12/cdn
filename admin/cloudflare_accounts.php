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

// 添加Cloudflare账号
$msg = '';
if (isset($_POST['name'], $_POST['api_token'], $_POST['zone_id'])) {
    $name = trim($_POST['name']);
    $api_token = trim($_POST['api_token']);
    $zone_id = trim($_POST['zone_id']);
    if ($name && $api_token && $zone_id) {
        $stmt = $mysqli->prepare("INSERT INTO cloudflare_accounts (name, api_token, zone_id) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $api_token, $zone_id);
        $stmt->execute();
        $msg = '添加成功';
    }
}
// 获取账号列表
$res = $mysqli->query("SELECT * FROM cloudflare_accounts ORDER BY id DESC");
$accounts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>Cloudflare账号管理</title>
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
            <div class="card mb-3">
                <div class="card-header">添加Cloudflare账号</div>
                <div class="card-body">
                    <?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
                    <form class="row g-3" method="post">
                        <div class="col-md-3">
                            <input type="text" name="name" class="form-control" placeholder="账号备注" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="api_token" class="form-control" placeholder="API Token" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="zone_id" class="form-control" placeholder="Zone ID" required>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary">添加</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header">已绑定Cloudflare账号</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead><tr><th>ID</th><th>备注</th><th>API Token</th><th>Zone ID</th><th>添加时间</th></tr></thead>
                        <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td><?php echo $a['id']; ?></td>
                                <td><?php echo htmlspecialchars($a['name']); ?></td>
                                <td><?php echo htmlspecialchars($a['api_token']); ?></td>
                                <td><?php echo htmlspecialchars($a['zone_id']); ?></td>
                                <td><?php echo $a['created_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
