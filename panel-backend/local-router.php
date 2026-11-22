<?php
declare(strict_types=1);
// Only for php -S 127.0.0.1:4181 -t REPO_ROOT panel-backend/local-router.php
$root=dirname(__DIR__);$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if(str_contains($path,"\0")||str_contains($path,'\\')||preg_match('~(?:^|/)\.~',$path)||str_contains($path,'..')){http_response_code(404);exit;}
if($path==='/api.php'){require $root.'/api.php';return;}
if($path==='/panel'){header('Location: /panel/',true,301);return;}
if($path==='/panel/'||$path==='/panel/index.php'){require $root.'/panel/index.php';return;}
if($path==='/panel/login.php'){require $root.'/panel/login.php';return;}
if($path==='/panel/login.js')return false;
if(preg_match('~^/panel-backend/public/[a-z-]+\.(?:css|js)$~D',$path)&&is_file($root.$path))return false;
if($path==='/admin-preview/'||$path==='/admin-preview/index.html')return false;
if(preg_match('~^/(?:assets|admin-preview)/.+\.(?:css|js|webp|png|jpe?g|svg|woff2?)$~i',$path)&&is_file($root.$path))return false;
if(preg_match('~^/[a-zA-Z0-9_-]+\.(?:css|js|webp|png|jpe?g|svg)$~',$path)&&is_file($root.$path))return false;
if($path==='/robots.txt'){header('Content-Type: text/plain');echo "User-agent: *\nDisallow: /\n";return;}
require $root.'/site.php';
