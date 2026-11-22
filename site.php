<?php
declare(strict_types=1);
namespace Elephant;
require __DIR__.'/panel-backend/src/bootstrap.php';
require __DIR__.'/panel-backend/src/render.php';
try {
    $path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
    $prefix=config()['base_path']??'';
    if($prefix){if(!str_starts_with($path,$prefix.'/')){http_response_code(404);exit;}$path=substr($path,strlen($prefix));}
    serve_public(ltrim($path,'/')?:'index.html');
}
catch(\Throwable $e){error_log('[Elephant website] '.$e->getMessage());http_response_code(503);echo 'Strona jest chwilowo niedostępna.';}
