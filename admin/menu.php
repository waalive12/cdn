<?php
// 左侧菜单
?>
<div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php')echo' active'; ?>">仪表盘</a>
    <a href="domains.php" class="list-group-item list-group-item-action<?php if(basename($_SERVER['PHP_SELF'])=='domains.php')echo' active'; ?>">域名管理</a>
    <a href="subdomains.php" class="list-group-item list-group-item-action<?php if(basename($_SERVER['PHP_SELF'])=='subdomains.php')echo' active'; ?>">二级域名管理</a>
    <a href="settings.php" class="list-group-item list-group-item-action<?php if(basename($_SERVER['PHP_SELF'])=='settings.php')echo' active'; ?>">系统设置</a>
</div>
