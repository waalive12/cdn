<?php
session_start();
// 优化：无论 inc/db.php 是否存在，始终允许填写数据库信息并覆盖安装

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = trim($_POST['db_pass'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = trim($_POST['admin_pass'] ?? '');

    if (!$db_host || !$db_user || !$db_name || !$admin_user || !$admin_pass) {
        $err = '请填写所有信息';
    } else {
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($mysqli->connect_errno) {
            $err = '数据库连接失败: ' . $mysqli->connect_error;
        } else {
            // 创建表结构
            $sqls = [
                // 管理员表
                "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                // Cloudflare账号表
                "CREATE TABLE IF NOT EXISTS cloudflare_accounts (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), api_token VARCHAR(255), zone_id VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                // 域名表，支持多账号
                "CREATE TABLE IF NOT EXISTS domains (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, cf_account_id INT, domain VARCHAR(255), ns_status TINYINT DEFAULT 0, package VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                // 解析记录表，支持多类型
                "CREATE TABLE IF NOT EXISTS subdomains (id INT AUTO_INCREMENT PRIMARY KEY, domain_id INT, subdomain VARCHAR(255), type VARCHAR(10), value VARCHAR(255), status TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                // 系统设置
                "CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(50) UNIQUE, `value` TEXT)"
            ];
            foreach ($sqls as $sql) {
                if (!$mysqli->query($sql)) {
                    $err = '创建表失败: ' . $mysqli->error;
                    break;
                }
            }
            if (!$err) {
                // 插入管理员账号
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param('ss', $admin_user, $hash);
                if (!$stmt->execute()) {
                    $err = '管理员账号创建失败: ' . $stmt->error;
                } else {
                    // 写入数据库配置文件
                    $db_conf = "<?php\nreturn [\n    'host' => '" . addslashes($db_host) . "',\n    'user' => '" . addslashes($db_user) . "',\n    'pass' => '" . addslashes($db_pass) . "',\n    'name' => '" . addslashes($db_name) . "'\n];\n";
                    if (!is_dir('inc')) mkdir('inc');
                    file_put_contents('inc/db.php', $db_conf);
                    header('Location: index.php?installed=1');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>Cloudflare解析系统安装</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-center">Cloudflare解析系统安装</div>
                <div class="card-body">
                    <?php if ($err): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">数据库地址</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">数据库用户名</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">数据库密码</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">数据库名称</label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">后台账号</label>
                            <input type="text" name="admin_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">后台密码</label>
                            <input type="password" name="admin_pass" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">安装</button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3 text-muted">© 2026 Cloudflare解析系统</div>
        </div>
    </div>
</div>
</body>
</html>
