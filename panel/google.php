<?php
declare(strict_types=1);
namespace Elephant;
require dirname(__DIR__).'/panel-backend/src/bootstrap.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');header('X-Frame-Options: DENY');
$nonce=base64_encode(random_bytes(24));header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$nonce'; script-src 'nonce-$nonce'; base-uri 'none'; frame-ancestors 'none'");
$success=false;
try{method('GET');google_finish($_GET,$_COOKIE['elephant_google_oauth']??'');$success=true;$message='Kalendarz Google został połączony. Wracamy do panelu.';}
catch(InputError $e){http_response_code(400);$message=$e->getMessage();}
catch(\Throwable $e){http_response_code(503);error_log('[Elephant Google] Connection failed.');$message='Nie udało się połączyć Google. Wróć do ustawień panelu i spróbuj ponownie.';}
setcookie('elephant_google_oauth','',['expires'=>time()-3600,'path'=>base('panel/google.php'),'secure'=>config()['environment']==='production','httponly'=>true,'samesite'=>'Lax']);
$back=base('panel/').'?google='.($success?'connected':'error').'#ustawienia';
?><!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Połączenie Google · ELEPHANT</title><style nonce="<?=html($nonce)?>">body{font:16px/1.8 system-ui;background:#f6f4f1;color:#2a2421;max-width:650px;margin:15vh auto;padding:24px}a{color:inherit}</style><h1><?= $success?'Połączono Google':'Połączenie Google' ?></h1><p><?=html($message)?></p><a href="<?=html($back)?>">Wróć do panelu</a><?php if($success):?><script nonce="<?=html($nonce)?>">location.replace(<?=json_encode($back,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>);</script><?php endif;?></html>
