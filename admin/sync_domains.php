<?php
session_start();
if (!isset($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}
$db_conf = require __DIR__ . '/../inc/db.php';
$mysqli = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['pass'], $db_conf['name']);
require __DIR__ . '/../inc/functions.php';

$cf_account_id = intval($_GET['cf_account_id'] ?? 0);
if (!$cf_account_id) {
    die("未提供账号ID");
}

$res = $mysqli->query("SELECT * FROM cloudflare_accounts WHERE id = $cf_account_id");
$account = $res->fetch_assoc();
if (!$account) {
    die("账号不存在");
}

// 循环获取所有 zones (处理分页)
$page = 1;
$synced = 0;
while (true) {
    $api_resp = cf_api_request('GET', "zones?per_page=50&page={$page}", null, $account['api_token']);
    if (empty($api_resp['success'])) {
        die("API 请求失败: " . ($api_resp['error'] ?? '未知错误'));
    }
    
    $zones = $api_resp['result'] ?? [];
    if (empty($zones)) {
        break;
    }
    
    foreach ($zones as $zone) {
        $domain = $zone['name'];
        $zone_id = $zone['id'];
        $package = $zone['plan']['name'] ?? 'free';
        $ns_status = ($zone['status'] === 'active') ? 1 : 0;
        $ns_servers = $zone['name_servers'] ?? [];
        $expected_ns = !empty($ns_servers) ? implode(',', $ns_servers) : '';
        
        // 检查是否存在
        $chk = $mysqli->query("SELECT id FROM domains WHERE domain = '{$mysqli->real_escape_string($domain)}' AND cf_account_id = $cf_account_id");
        if ($chk && $chk->num_rows > 0) {
            // 更新
            $mysqli->query("UPDATE domains SET zone_id = '{$mysqli->real_escape_string($zone_id)}', ns_status = $ns_status, expected_ns = '{$mysqli->real_escape_string($expected_ns)}', package = '{$mysqli->real_escape_string($package)}' WHERE domain = '{$mysqli->real_escape_string($domain)}' AND cf_account_id = $cf_account_id");
        } else {
            // 插入
            $stmt = $mysqli->prepare("INSERT INTO domains (user_id, cf_account_id, domain, zone_id, ns_status, expected_ns, package) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisssis', $_SESSION['uid'], $cf_account_id, $domain, $zone_id, $ns_status, $expected_ns, $package);
            $stmt->execute();
        }
        $synced++;
    }
    
    $total_pages = $api_resp['result_info']['total_pages'] ?? 1;
    if ($page >= $total_pages) {
        break;
    }
    $page++;
}

header("Location: domains.php?cf_account_id=$cf_account_id&msg=synced&count=$synced");
exit;
