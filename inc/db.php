<?php
// 数据库配置
$config = [
    'host' => 'localhost', // 为修改你的数据库主机
    'user' => 'root',      // 修改为你的数据库用户名
    'pass' => '',          // 修改为你的数据库密码
    'name' => 'test'       // 修改为你的数据库名
];

// 创建数据库连接
$mysqli = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
if ($mysqli->connect_errno) {
    die('数据库连接失败: ' . $mysqli->connect_error);
}
return $mysqli;