<?php
declare(strict_types=1);
namespace Elephant;

const ROOT = __DIR__ . '/../..';
function config(): array {
    static $config;
    if (isset($config)) return $config;
    $file = getenv('ELEPHANT_CONFIG') ?: dirname(realpath(ROOT)) . '/elephant-private/config.php';
    // Staging lives inside public_html; configuration stays outside the entire public directory.
    if(!getenv('ELEPHANT_CONFIG')&&!is_file($file)&&basename(dirname(realpath(ROOT)))==='public_html')$file=dirname(realpath(ROOT),2).'/elephant-private/config.php';
    if (!is_file($file)) throw new \RuntimeException('Panel nie jest jeszcze skonfigurowany na tym serwerze.');
    $value = require $file;
    if (!is_array($value) || strlen($value['secret'] ?? '') < 32) throw new \RuntimeException('Nieprawidłowa konfiguracja panelu.');
    if (!in_array($value['environment'] ?? '', ['local','production'], true)) throw new \RuntimeException('Nieprawidłowe środowisko.');
    $root = str_replace('\\','/',realpath(ROOT));
    $private = str_replace('\\','/',realpath($value['private_dir'] ?? '') ?: '');
    $publicRoot=basename(dirname($root))==='public_html'?dirname($root):$root;
    if (!$private || str_starts_with(strtolower($private . '/'), strtolower($publicRoot . '/'))) throw new \RuntimeException('Dane prywatne muszą znajdować się poza katalogiem strony.');
    if ($value['environment'] === 'production' && !str_starts_with($value['dsn'] ?? '', 'mysql:')) throw new \RuntimeException('Produkcja wymaga skonfigurowanej bazy MySQL.');
    if (!preg_match('~^(/[a-zA-Z0-9_-]+)*$~', $value['base_path'] ?? '')) throw new \RuntimeException('Nieprawidłowy katalog strony.');
    return $config = $value;
}
function db(): \PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c=config();
    $pdo=new \PDO($c['dsn'],$c['db_user']??null,$c['db_password']??null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);
    if (driver()==='sqlite') { $pdo->exec('PRAGMA busy_timeout=10000'); $pdo->exec('PRAGMA journal_mode=WAL'); }
    else { $pdo->exec("SET time_zone = '+00:00'"); }
    return $pdo;
}
function driver(): string { return str_starts_with(config()['dsn'],'sqlite:') ? 'sqlite':'mysql'; }
function query(string $sql,array $params=[]): \PDOStatement { $q=db()->prepare($sql);$q->execute($params);return $q; }
function transaction(callable $fn): mixed {
    $p=db();if(driver()==='sqlite')$p->exec('BEGIN IMMEDIATE');else $p->beginTransaction();
    try { $result=$fn();if(driver()==='sqlite')$p->exec('COMMIT');else $p->commit();return $result; }
    catch (\Throwable $e) { if(driver()==='sqlite')$p->exec('ROLLBACK');elseif($p->inTransaction())$p->rollBack();throw $e; }
}
function state_row(bool $lock=false): array {
    $row=query('SELECT revision, body FROM elephant_state WHERE id=1'.($lock&&driver()==='mysql'?' FOR UPDATE':''))->fetch();
    if (!$row) throw new \RuntimeException('Najpierw uruchom instalację panelu przez CLI.');
    return ['revision'=>(int)$row['revision'],'state'=>json_decode($row['body'],true,512,JSON_THROW_ON_ERROR)];
}
function write_state(array $state,int $revision): void { query('UPDATE elephant_state SET body=?, revision=? WHERE id=1',[json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$revision+1]); }
function utc_now(): string { return gmdate('Y-m-d H:i:s'); }
function json_response(array $data,int $status=200): never {
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
function problem(string $message,int $status=422): never { json_response(['ok'=>false,'error'=>$message],$status); }
function session_start_safe(): void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    $c=config();$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    if($c['environment']==='production'&&!$https)problem('Panel wymaga połączenia HTTPS.',503);
    if($c['environment']==='local'&&!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&PHP_SAPI!=='cli')problem('Środowisko lokalne jest dostępne tylko na tym komputerze.',403);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('elephant_session');session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Strict','path'=>($c['base_path']?:'').'/']);session_start();
    if(isset($_SESSION['user'])){
        $credential=query('SELECT password_hash FROM admin_users WHERE email=?',[$_SESSION['user']['email']])->fetch();
        if(!$credential||!hash_equals(hash('sha256',$credential['password_hash']),$_SESSION['credential_version']??'')){
            $_SESSION=[];session_regenerate_id(true);
        }
    }
    if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
    if(isset($_SESSION['user'])&&(time()-($_SESSION['last_seen']??0)>1800||time()-($_SESSION['signed_in']??0)>28800)) { $_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32)); }
}
function require_user(): array {
    session_start_safe();if(!isset($_SESSION['user']))problem('Zaloguj się ponownie.',401);
    $_SESSION['last_seen']=time();return $_SESSION['user'];
}
function require_csrf(): void {
    session_start_safe();$origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin){$expected=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'');if(!hash_equals($expected,$origin))problem('Nieprawidłowe źródło zapytania.',403);}
    $token=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';
    if(!is_string($token)||!hash_equals($_SESSION['csrf'],$token))problem('Odśwież stronę i spróbuj ponownie.',403);
}
function rate_limit(string $scope,int $max,int $window,string $subject=''): void {
    $key=hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'cli').'|'.$subject,config()['secret']);
    $allowed=transaction(function()use($key,$max,$window){
        query('DELETE FROM rate_limits WHERE expires_at < ?',[time()]);
        $row=query('SELECT hits, expires_at FROM rate_limits WHERE bucket_key=?'.(driver()==='mysql'?' FOR UPDATE':''),[$key])->fetch();
        if(!$row){query('INSERT INTO rate_limits(bucket_key,hits,expires_at) VALUES(?,?,?)',[$key,1,time()+$window]);return true;}
        if((int)$row['hits'] >= $max)return false;query('UPDATE rate_limits SET hits=hits+1 WHERE bucket_key=?',[$key]);return true;
    });
    if(!$allowed){header('Retry-After: '.$window);problem('Zbyt wiele prób. Spróbuj ponownie później.',429);}
}
function request_data(): array {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>8*1024*1024)problem('Przesłane dane są zbyt duże.',413);
    if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) { try { $d=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR); }catch(\JsonException){problem('Nieprawidłowy format danych.');}if(!is_array($d))problem('Nieprawidłowe dane.');return $d; }
    return $_POST;
}
function html(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function method(string ...$allowed): void { if(!in_array($_SERVER['REQUEST_METHOD'],$allowed,true)){header('Allow: '.implode(', ',$allowed));problem('Metoda niedozwolona.',405);} }
function id(): string { return bin2hex(random_bytes(16)); }
function url(string $path=''): string { return rtrim(config()['site_url'],'/').'/'.ltrim($path,'/'); }
function base(string $path=''): string { return (config()['base_path']??'').'/'.ltrim($path,'/'); }
require_once __DIR__.'/validation.php';
require_once __DIR__.'/booking.php';
require_once __DIR__.'/mail.php';
require_once __DIR__.'/google-calendar.php';
