<?php
header("Content-Type: application/vnd.apple.mpegurl");
header("Access-Control-Allow-Origin: *");

$url = "https://live.yallakoragoal.com/hls/mystream1.m3u8";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$data = curl_exec($ch);
curl_close($ch);

echo $data;
?>