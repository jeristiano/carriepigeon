<?php


$url = 'https://hp.911250.com/bwmodel/api/sunwin?';

$json['key'] = 't9OL52ATHN63Qun';
$json['vendor'] = '500';
$json['user'] = 312888;
$json['cmd'] = '1';
$json['lang'] =1;
$json['nickname'] = 'kk';
$json['sign'] = md5($json['cmd'].$json['user'].$json['vendor'].$json['key']);
$url.=http_build_query($json);
$curl = curl_init($url); // 启动一个CURL会话

$headerArray = ["Content-type:application/json;","Accept:application/json"];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch,CURLOPT_HTTPHEADER,$headerArray);
$output = curl_exec($ch);
curl_close($ch);
$output = json_decode($output,true);

if (curl_errno($curl)) {
    print_r(curl_error($curl));
}
curl_close($curl); // 关键CURL会话

var_dump('=====================');
var_dump($output);
var_dump('=====================');