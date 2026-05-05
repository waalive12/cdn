<?php
$db_conf = require 'inc/db.php';
$mysqli = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['pass'], $db_conf['name']);
$mysqli->query("DROP TABLE IF EXISTS users, cloudflare_accounts, domains, subdomains, custom_hostnames, settings");
echo "Dropped. Now you can re-install or I can create them.\n";
