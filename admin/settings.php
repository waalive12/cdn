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

// 保存Cloudflare API Token和Zone ID
$msg = '';
if (isset($_POST['cf_token'], $_POST['cf_zone'])) {
    $token = trim($_POST['cf_token']);
    $zone = trim($_POST['cf_zone']);
    $mysqli->query("REPLACE INTO settings (`key`,`value`) VALUES ('cf_token','".$mysqli->real_escape_string($token)."')");
    $mysqli->query("REPLACE INTO settings (`key`,`value`) VALUES ('cf_zone','".$mysqli->real_escape_string($zone)."')");
    $msg = '保存成功';
}
// 读取配置
$res = $mysqli->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('cf_token','cf_zone')");
$cf_token = $cf_zone = '';
while($row = $res->fetch_assoc()){
    if($row['key']=='cf_token') $cf_token = $row['value'];
    if($row['key']=='cf_zone') $cf_zone = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>系统设置 - Cloudflare解析系统</title>
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
                <div class="card-header">Cloudflare API 配置</div>
                <div class="card-body">
                    <?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">API Token</label>
                            <input type="text" name="cf_token" class="form-control" value="<?php echo htmlspecialchars($cf_token); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Zone ID</label>
                            <input type="text" name="cf_zone" class="form-control" value="<?php echo htmlspecialchars($cf_zone); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </form>
                    <div class="mt-3 text-muted">
                        <small>请在Cloudflare后台获取API Token（需有自定义主机名权限）和Zone ID。</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
