<?php
// Cloudflare API 封装
function cf_api_request($method, $endpoint, $data = null, $token = null) {
    $url = 'https://api.cloudflare.com/client/v4/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['success' => false, 'error' => $err];
    return json_decode($resp, true);
}

// 获取Cloudflare API Token和Zone ID（可在 settings.php 配置）
function cf_get_token_zone() {
    global $mysqli;
    $res = $mysqli->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('cf_token','cf_zone')");
    $arr = ['cf_token'=>'','cf_zone'=>''];
    while($row = $res->fetch_assoc()){
        $arr[$row['key']] = $row['value'];
    }
    return $arr;
}
