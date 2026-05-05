<?php
session_start();
if (!isset($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}
$db_conf = require __DIR__ . '/../inc/db.php';
$mysqli = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['pass'], $db_conf['name']);
require_once __DIR__ . '/../inc/functions.php';

// 读取 SaaS 配置
$res = $mysqli->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('saas_api_token','saas_zone_id', 'target_cname')");
$settings = ['saas_api_token' => '', 'saas_zone_id' => '', 'target_cname' => ''];
while ($row = $res->fetch_assoc()) {
    $settings[$row['key']] = $row['value'];
}

// 获取主域名列表 (包含 token 和 zone_id 以便操作 DNS)
$res_d = $mysqli->query("SELECT d.*, c.api_token FROM domains d LEFT JOIN cloudflare_accounts c ON d.cf_account_id = c.id WHERE d.zone_id IS NOT NULL AND d.zone_id != '' ORDER BY d.id DESC");
$domains = $res_d ? $res_d->fetch_all(MYSQLI_ASSOC) : [];

$gen_msg = '';
$err_msg = '';
$generated_list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    if (empty($settings['saas_api_token']) || empty($settings['saas_zone_id']) || empty($settings['target_cname'])) {
        $err_msg = "请先在系统设置中配置 SaaS 相关的 API Token、Zone ID 和 目标 CNAME。";
    } else {
        $domain_id = intval($_POST['domain_id']);
        $prefix_type = $_POST['prefix_type'] ?? 'random';
        $prefix = trim($_POST['prefix'] ?? '');
        $count = ($prefix_type === 'fixed') ? 1 : max(1, min(50, intval($_POST['count'] ?? 1)));
        $proxied = isset($_POST['proxied']) && $_POST['proxied'] === '1' ? true : false;
        
        $target_domain = null;
        foreach ($domains as $d) {
            if ($d['id'] == $domain_id) {
                $target_domain = $d;
                break;
            }
        }
        
        if ($target_domain) {
            $success_count = 0;
            for ($i = 0; $i < $count; $i++) {
                if ($prefix_type === 'random') {
                    $sub = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);
                } else {
                    $sub = $prefix;
                }
                if (empty($sub)) continue;
                
                $full_hostname = $sub . '.' . $target_domain['domain'];
                
                // 1. 在 SaaS Zone 中创建 Custom Hostname
                $ch_data = [
                    "hostname" => $full_hostname,
                    "ssl" => [
                        "method" => "txt",
                        "type" => "dv"
                    ]
                ];
                $ch_res = cf_api_request('POST', "zones/{$settings['saas_zone_id']}/custom_hostnames", $ch_data, $settings['saas_api_token']);
                
                if (!empty($ch_res['success'])) {
                    $ch_id = $ch_res['result']['id'];
                    $status = $ch_res['result']['status'] ?? 'pending';
                    
                    // 获取 Ownership TXT
                    $own_txt_name = $ch_res['result']['ownership_verification']['name'] ?? '';
                    $own_txt_val = $ch_res['result']['ownership_verification']['value'] ?? '';
                    
                    // 获取 SSL Validation TXT (目前 CF 通常返回 TXT 用于验证，如果是 CNAME 也会转成类似结构)
                    $ssl_txt_name = '';
                    $ssl_txt_val = '';
                    if (!empty($ch_res['result']['ssl']['validation_records'])) {
                        foreach ($ch_res['result']['ssl']['validation_records'] as $vr) {
                            if (isset($vr['txt_name'])) {
                                $ssl_txt_name = $vr['txt_name'];
                                $ssl_txt_val = $vr['txt_value'];
                            } elseif (isset($vr['cname_name'])) {
                                $ssl_txt_name = $vr['cname_name'];
                                $ssl_txt_val = $vr['cname_target'];
                            }
                        }
                    }
                    
                    // 2. 解析记录入库
                    $stmt = $mysqli->prepare("INSERT INTO custom_hostnames (domain_id, hostname, cf_custom_hostname_id, target_cname, status, ownership_txt_name, ownership_txt_value, ssl_txt_name, ssl_txt_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('issssssss', $domain_id, $full_hostname, $ch_id, $settings['target_cname'], $status, $own_txt_name, $own_txt_val, $ssl_txt_name, $ssl_txt_val);
                    $stmt->execute();
                    
                    // 3. 为主域名添加 CNAME 记录，指向 Target CNAME
                    $dns_cname = [
                        "type" => "CNAME",
                        "name" => $sub,
                        "content" => $settings['target_cname'],
                        "ttl" => 1,
                        "proxied" => $proxied
                    ];
                    cf_api_request('POST', "zones/{$target_domain['zone_id']}/dns_records", $dns_cname, $target_domain['api_token']);
                    
                    // 4. 为主域名添加 TXT Ownership Verification 记录
                    if (!empty($ch_res['result']['ownership_verification']['name'])) {
                        $dns_txt = [
                            "type" => "TXT",
                            "name" => $ch_res['result']['ownership_verification']['name'],
                            "content" => $ch_res['result']['ownership_verification']['value'],
                            "ttl" => 1
                        ];
                        cf_api_request('POST', "zones/{$target_domain['zone_id']}/dns_records", $dns_txt, $target_domain['api_token']);
                    }
                    
                    // 5. 为主域名添加 SSL Validation 记录 (TXT/CNAME)
                    if (!empty($ch_res['result']['ssl']['validation_records'])) {
                        foreach ($ch_res['result']['ssl']['validation_records'] as $vr) {
                            $v_type = isset($vr['txt_name']) ? 'TXT' : 'CNAME';
                            $v_name = $vr['txt_name'] ?? $vr['cname_name'] ?? '';
                            $v_content = $vr['txt_value'] ?? $vr['cname_target'] ?? '';
                            if ($v_name && $v_content) {
                                $dns_ssl = [
                                    "type" => $v_type,
                                    "name" => $v_name,
                                    "content" => $v_content,
                                    "ttl" => 1,
                                    "proxied" => false
                                ];
                                cf_api_request('POST', "zones/{$target_domain['zone_id']}/dns_records", $dns_ssl, $target_domain['api_token']);
                            }
                        }
                    }
                    
                    $success_count++;
                    $generated_list[] = $full_hostname;
                } else {
                    $err_msg .= "生成 $full_hostname 失败: " . ($ch_res['errors'][0]['message'] ?? '未知错误') . "<br>";
                }
            }
            if ($success_count > 0) {
                $gen_msg = "成功生成并解析 $success_count 个自定义主机名！";
            }
        }
    }
}

// 刷新状态
if (isset($_GET['refresh']) && is_numeric($_GET['refresh'])) {
    $cid = intval($_GET['refresh']);
    if (!empty($settings['saas_zone_id']) && !empty($settings['saas_api_token'])) {
        $q = $mysqli->query("SELECT * FROM custom_hostnames WHERE id = $cid");
        if ($row = $q->fetch_assoc()) {
            $ch_id = $row['cf_custom_hostname_id'];
            $res_api = cf_api_request('GET', "zones/{$settings['saas_zone_id']}/custom_hostnames/$ch_id", null, $settings['saas_api_token']);
            if (!empty($res_api['success'])) {
                $status = $mysqli->real_escape_string($res_api['result']['status']);
                
                // 更新 Ownership TXT
                $own_txt_name = $mysqli->real_escape_string($res_api['result']['ownership_verification']['name'] ?? '');
                $own_txt_val = $mysqli->real_escape_string($res_api['result']['ownership_verification']['value'] ?? '');
                
                // 更新 SSL Validation TXT
                $ssl_txt_name = '';
                $ssl_txt_val = '';
                if (!empty($res_api['result']['ssl']['validation_records'])) {
                    foreach ($res_api['result']['ssl']['validation_records'] as $vr) {
                        if (isset($vr['txt_name'])) {
                            $ssl_txt_name = $mysqli->real_escape_string($vr['txt_name']);
                            $ssl_txt_val = $mysqli->real_escape_string($vr['txt_value']);
                        } elseif (isset($vr['cname_name'])) {
                            $ssl_txt_name = $mysqli->real_escape_string($vr['cname_name']);
                            $ssl_txt_val = $mysqli->real_escape_string($vr['cname_target']);
                        }
                    }
                }
                
                // 本地检测实际 DNS
                $dns_ok = 1;
                $hostname = $res_api['result']['hostname'];
                $target_cname = $row['target_cname']; // 我们需要获取 target_cname 用于检测
                
                // 检测 CNAME
                $cname_records = @dns_get_record($hostname, DNS_CNAME);
                $cname_match = false;
                if ($cname_records) {
                    foreach ($cname_records as $rec) {
                        if (isset($rec['target']) && $rec['target'] === $target_cname) {
                            $cname_match = true;
                            break;
                        }
                    }
                }
                if (!$cname_match) $dns_ok = 0;
                
                // 检测 Ownership TXT
                if ($own_txt_name && $own_txt_val) {
                    $txt_records = @dns_get_record($own_txt_name, DNS_TXT);
                    $txt_match = false;
                    if ($txt_records) {
                        foreach ($txt_records as $rec) {
                            if (isset($rec['txt']) && $rec['txt'] === $own_txt_val) {
                                $txt_match = true;
                                break;
                            }
                        }
                    }
                    if (!$txt_match) $dns_ok = 0;
                }
                
                // 检测 SSL TXT/CNAME
                if ($ssl_txt_name && $ssl_txt_val) {
                    // 由于 validation_records 可能是 TXT 也可能是 CNAME，我们都尝试获取
                    $ssl_match = false;
                    $ssl_txt_records = @dns_get_record($ssl_txt_name, DNS_TXT);
                    if ($ssl_txt_records) {
                        foreach ($ssl_txt_records as $rec) {
                            if (isset($rec['txt']) && $rec['txt'] === $ssl_txt_val) {
                                $ssl_match = true;
                                break;
                            }
                        }
                    }
                    if (!$ssl_match) {
                        $ssl_cname_records = @dns_get_record($ssl_txt_name, DNS_CNAME);
                        if ($ssl_cname_records) {
                            foreach ($ssl_cname_records as $rec) {
                                if (isset($rec['target']) && $rec['target'] === $ssl_txt_val) {
                                    $ssl_match = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (!$ssl_match) $dns_ok = 0;
                }

                $mysqli->query("UPDATE custom_hostnames SET status = '$status', ownership_txt_name = '$own_txt_name', ownership_txt_value = '$own_txt_val', ssl_txt_name = '$ssl_txt_name', ssl_txt_value = '$ssl_txt_val', dns_check_status = $dns_ok WHERE id = $cid");
            }
        }
    }
    header('Location: custom_hostnames.php?msg=refreshed');
    exit;
}

// 删除主机名
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $cid = intval($_GET['delete']);
    if (!empty($settings['saas_zone_id']) && !empty($settings['saas_api_token'])) {
        $q = $mysqli->query("SELECT cf_custom_hostname_id FROM custom_hostnames WHERE id = $cid");
        if ($row = $q->fetch_assoc()) {
            $ch_id = $row['cf_custom_hostname_id'];
            cf_api_request('DELETE', "zones/{$settings['saas_zone_id']}/custom_hostnames/$ch_id", null, $settings['saas_api_token']);
        }
    }
    $mysqli->query("DELETE FROM custom_hostnames WHERE id = $cid");
    header('Location: custom_hostnames.php?msg=deleted');
    exit;
}

// 获取列表
$filter_domain = intval($_GET['domain_id'] ?? 0);
$where = "";
if ($filter_domain) {
    $where = "WHERE c.domain_id = $filter_domain";
}
$res_list = $mysqli->query("SELECT c.*, d.domain FROM custom_hostnames c LEFT JOIN domains d ON c.domain_id = d.id $where ORDER BY c.id DESC LIMIT 200");
$list = $res_list ? $res_list->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <title>SaaS自定义主机名管理 - 解析系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include 'menu.php'; ?>
        </div>
        <div class="col-md-9">
            
            <?php if (empty($settings['saas_zone_id'])): ?>
                <div class="alert alert-warning">请先在【系统设置】中配置 SaaS API 信息，否则无法生成自定义主机名！</div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">批量生成 SaaS 自定义主机名</div>
                <div class="card-body">
                    <?php if ($err_msg): ?><div class="alert alert-danger"><?php echo $err_msg; ?></div><?php endif; ?>
                    <?php if ($gen_msg): ?>
                        <div class="alert alert-success">
                            <?php echo $gen_msg; ?>
                            <div class="mt-2">
                                <textarea id="generatedList" class="form-control" rows="3" readonly><?php echo implode("\n", $generated_list); ?></textarea>
                                <button class="btn btn-sm btn-outline-success mt-2" onclick="copyList()">一键复制域名</button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form class="row g-3" method="post">
                        <input type="hidden" name="action" value="generate">
                        <div class="col-md-4">
                            <label class="form-label">选择目标主域名</label>
                            <select name="domain_id" id="domain_select" class="form-select" required>
                                <option value="">-- 搜索或选择域名 --</option>
                                <?php foreach ($domains as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $filter_domain == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['domain']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">生成模式</label>
                            <select name="prefix_type" class="form-select" onchange="toggleMode(this.value)">
                                <option value="random">随机子域名</option>
                                <option value="fixed">固定子域名</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">前缀 / 数量</label>
                            <div class="input-group">
                                <input type="text" name="prefix" id="prefix" class="form-control" placeholder="前缀" disabled>
                                <input type="number" name="count" id="count" class="form-control" value="5" min="1" max="50">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">代理状态 (橙云)</label>
                            <select name="proxied" class="form-select">
                                <option value="0" selected>仅 DNS (不开启)</option>
                                <option value="1">已代理 (开启)</option>
                            </select>
                        </div>
                        <div class="col-md-12 mt-3 text-end">
                            <button type="submit" class="btn btn-primary" <?php echo empty($settings['saas_zone_id']) ? 'disabled' : ''; ?>><i class="bi bi-lightning-charge"></i> 立即生成</button>
                        </div>
                    </form>
                    <div class="form-text mt-3">
                        <i class="bi bi-info-circle"></i> 系统会自动：1. 在 SaaS 主域添加 Custom Hostname；2. 在您选择的域名中添加对应的 CNAME 及 TXT 验证记录。
                    </div>
                </div>
            </div>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'refreshed'): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">状态已刷新<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">主机名已删除<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold">自定义主机名列表 (最近200条)</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-bordered mb-0 align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>自定义主机名 / 目标解析</th>
                                <th>Ownership TXT 验证</th>
                                <th>SSL 证书验证</th>
                                <th>状态 / 检测</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="6" class="text-muted py-4">暂无记录</td></tr>
                        <?php else: ?>
                            <?php foreach ($list as $s): ?>
                                <tr>
                                    <td><?php echo $s['id']; ?></td>
                                    <td class="text-start">
                                        <div class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($s['hostname']); ?></div>
                                        <div><span class="badge bg-secondary">CNAME: <?php echo htmlspecialchars($s['target_cname']); ?></span></div>
                                    </td>
                                    <td class="text-start" style="max-width: 250px; word-break: break-all;">
                                        <?php if($s['ownership_txt_name'] && $s['ownership_txt_value']): ?>
                                            <small class="d-block text-muted">Name:</small>
                                            <code><?php echo htmlspecialchars($s['ownership_txt_name']); ?></code>
                                            <small class="d-block text-muted mt-1">Value:</small>
                                            <code><?php echo htmlspecialchars($s['ownership_txt_value']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-start" style="max-width: 250px; word-break: break-all;">
                                        <?php if($s['ssl_txt_name'] && $s['ssl_txt_value']): ?>
                                            <small class="d-block text-muted">Name:</small>
                                            <code><?php echo htmlspecialchars($s['ssl_txt_name']); ?></code>
                                            <small class="d-block text-muted mt-1">Value:</small>
                                            <code><?php echo htmlspecialchars($s['ssl_txt_value']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mb-2">
                                            <?php if ($s['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($s['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars($s['status']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($s['dns_check_status']): ?>
                                                <span class="badge bg-info text-dark"><i class="bi bi-check-circle"></i> 解析正常</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> 解析未生效</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="?refresh=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info mb-1" title="重新获取验证记录并检测DNS"><i class="bi bi-arrow-clockwise"></i> 检测</a><br>
                                        <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定删除？将同时从 Cloudflare SaaS 中删除。');" title="删除"><i class="bi bi-trash"></i> 删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleMode(val) {
    if (val === 'random') {
        document.getElementById('prefix').disabled = true;
        document.getElementById('count').disabled = false;
    } else {
        document.getElementById('prefix').disabled = false;
        document.getElementById('count').disabled = true;
    }
}
function copyList() {
    const list = document.getElementById('generatedList');
    list.select();
    document.execCommand('copy');
    alert('已复制到剪贴板');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#domain_select').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '-- 搜索或选择域名 --',
        allowClear: true
    });
});
</script>
</body>
</html>