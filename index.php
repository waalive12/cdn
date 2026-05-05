<?php

session_start();
$db_conf = require __DIR__ . '/inc/db.php';
$mysqli = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['pass'], $db_conf['name']);
if ($mysqli->connect_errno) {
    die('数据库连接失败: ' . $mysqli->connect_error);
}

// 登录处理
if (isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($uid, $hash);
        $stmt->fetch();
        if (password_verify($password, $hash)) {
            $_SESSION['uid'] = $uid;
            $_SESSION['username'] = $username;
            header('Location: admin/dashboard.php');
            exit;
        }
    }
    $err = '账号或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>Cloudflare解析系统登录</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header text-center">系统登录</div>
                <div class="card-body">
                    <?php if (!empty($err)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">账号</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">密码</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">登录</button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3 text-muted">© 2026 Cloudflare解析系统</div>
        </div>
    </div>
</div>
</body>
</html>
