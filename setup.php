<?php
declare(strict_types=1);
namespace Elephant;
// One-use installation for FTP-only hosting. The random token stays outside the web root.
require __DIR__.'/panel-backend/src/bootstrap.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(404);exit;}
    $c=config();$marker=$c['private_dir'].'/.installed';$tokenFile=$c['private_dir'].'/install-token.txt';
    if(is_file($marker)||!is_file($tokenFile)){http_response_code(404);exit;}
    $expected=trim(file_get_contents($tokenFile));$provided=$_SERVER['HTTP_X_ELEPHANT_INSTALL']??'';
    if(!is_string($provided)||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($expected,$provided)){http_response_code(404);exit;}
    $lock=fopen($c['private_dir'].'/.installation.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))problem('Instalacja jest w toku.',409);
    if(is_file($marker)){http_response_code(404);exit;}
    require __DIR__.'/panel-backend/src/migration.php';migrate_database();
    if(query('SELECT email FROM admin_users LIMIT 1')->fetch())problem('Konto administratora już istnieje. Instalator nie zmienia haseł.',409);
    $password=trim(file_get_contents($c['private_dir'].'/admin-password.txt'));
    if(strlen($password)<14||strlen($password)>72)throw new \RuntimeException('Invalid administrator password file.');
    $address=state_row()['state']['settings']['email'];
    query('INSERT INTO admin_users(email,password_hash) VALUES(?,?)',[$address,password_hash($password,PASSWORD_DEFAULT)]);
    if(file_put_contents($marker,gmdate(DATE_ATOM),LOCK_EX)===false)throw new \RuntimeException('Cannot close installer.');
    chmod($marker,0600);
    json_response(['ok'=>true,'installed'=>true,'php'=>PHP_VERSION,'driver'=>driver()]);
}catch(\Throwable $e){error_log('[Elephant installation] '.$e->getMessage());json_response(['ok'=>false,'error'=>'Instalacja nie powiodła się. Sprawdź prywatny log serwera.'],503);}
