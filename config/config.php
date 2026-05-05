<?php
/**
 * 系统配置文件
 */

// DNS 中继服务器
define('DNS_RELAY_SERVER', 'dns.ptdns360.com');
define('DNS_RELAY_TTL', 3600);

// 系统配置
define('SYSTEM_TITLE', 'Cloudflare DNS 管理系统');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_URL', 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']);

// Session 配置
define('SESSION_TIMEOUT', 3600); // 1 小时
define('SESSION_PREFIX', 'cf_dns_');

// 安全配置
define('SECURE_MODE', false); // 生产环境建议改为 true
define('IP_WHITELIST', array()); // 留空表示不限制，格式: array('127.0.0.1', '192.168.1.0/24')

// 密钥配置
define('ENCRYPTION_KEY', 'your-secret-key-change-this');

// 任务配置
define('AUTO_CHECK_INTERVAL', 3600); // 1 小时检查一次
define('AUTO_CHECK_ENABLED', true);

// DNS 检查配置
define('DNS_CHECK_TIMEOUT', 5); // 秒
define('DNS_CHECK_RETRIES', 3);

// 限制配置
define('MAX_DOMAINS_PER_BATCH', 100);
define('MAX_HOSTNAMES_PER_ACCOUNT', 50);

// 日志配置
define('LOG_ENABLED', true);
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'info'); // debug, info, warning, error

// 创建必要的目录
@mkdir(LOG_PATH, 0755, true);
@mkdir(__DIR__ . '/../temp/', 0755, true);
@mkdir(__DIR__ . '/../cache/', 0755, true);

?>