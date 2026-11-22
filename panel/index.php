<?php
declare(strict_types=1);
namespace Elephant;
require dirname(__DIR__).'/panel-backend/src/bootstrap.php';
try {
    session_start_safe();if(!isset($_SESSION['user'])){header('Location: login.php');exit;}$_SESSION['last_seen']=time();
    $nonce=base64_encode(random_bytes(24));header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    $html=file_get_contents(dirname(__DIR__).'/admin-preview/index.html');
    $html=str_replace(['href="panel.css?v=2"','src="panel.js"','<script src="seed.js"></script>','Panel ELEPHANT · podgląd','<span class="preview-pill">PODGLĄD</span>'],['href="../admin-preview/panel.css?v=4"','src="../admin-preview/panel.js?v=6"','','Panel ELEPHANT','<div style="display:flex;gap:20px"><button class="text-button" id="refresh" type="button">Odśwież</button><button class="text-button" id="logout" type="button">Wyloguj</button></div>'],$html);
    $notice=config()['environment']==='local'?'Środowisko lokalne · dane zapisują się w bazie. Powiadomienia e-mail trafiają do plików testowych.':'Panel administracyjny ELEPHANT · zmiany opublikowanych treści pojawią się na stronie po zapisaniu.';
    $html=preg_replace('~<div class="preview-note">.*?</div>~s','<div class="preview-note">'.html($notice).'</div>',$html);
    $context='<script nonce="'.$nonce.'">window.ELEPHANT_PANEL_API="../api.php";window.ELEPHANT_PANEL_SESSION='.json_encode(['csrf'=>$_SESSION['csrf'],'environment'=>config()['environment']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>';
    echo str_replace('<script src="../admin-preview/panel.js?v=6"></script>',$context.'<script src="../admin-preview/panel.js?v=6"></script>',$html);
}catch(\Throwable $e){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Panel nie jest jeszcze skonfigurowany na tym serwerze.';}
