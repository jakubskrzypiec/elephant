<?php
declare(strict_types=1);
namespace Elephant;
require __DIR__.'/panel-backend/src/bootstrap.php';
require __DIR__.'/panel-backend/src/render.php';
try {
    $path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
    $prefix=config()['base_path']??'';
    if($prefix){if(!str_starts_with($path,$prefix.'/')){http_response_code(404);exit;}$path=substr($path,strlen($prefix));}
    // Preserve public URLs from the WordPress site during the domain migration.
    $legacy=['contact'=>'kontakt.html','oferta2'=>'oferty.html','services'=>'projekty.html','about'=>'index.html',
        'en/contact-2'=>'kontakt.html','de/kontakt-d'=>'kontakt.html','en/offer'=>'oferty.html','de/angebot'=>'oferty.html',
        'en/portfolio-2'=>'projekty.html','de/portfolio'=>'projekty.html','en/story-en'=>'index.html','de/geschichte-de'=>'index.html',
        'en/start-en'=>'index.html','de/startseite-de'=>'index.html','en'=>'index.html','de'=>'index.html',
        'wp-sitemap.xml'=>'sitemap.xml','sitemap_index.xml'=>'sitemap.xml','post-type-page-sitemap-1.xml'=>'sitemap.xml'];
    $key=trim($path,'/');if(isset($legacy[$key])){header('Location: '.base($legacy[$key]),true,301);return;}
    serve_public(ltrim($path,'/')?:'index.html');
}
catch(\Throwable $e){error_log('[Elephant website] '.$e->getMessage());http_response_code(503);echo 'Strona jest chwilowo niedostępna.';}
