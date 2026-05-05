<?php
// 顶部导航栏
$username = $_SESSION['username'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Cloudflare解析系统</a>
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
