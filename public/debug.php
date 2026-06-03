<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
echo "Laravel 环境加载成功！当前路径：" . getcwd();
?>