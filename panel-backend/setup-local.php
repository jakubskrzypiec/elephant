<?php
declare(strict_types=1);
namespace Elephant;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$private=$argv[1]??'';$absolute=str_starts_with($private,'/')||(preg_match('~^[A-Za-z]:~',$private)&&in_array($private[2]??'',['/',chr(92)],true));if(!$private||!$absolute){fwrite(STDERR,"Usage: php setup-local.php ABSOLUTE_PRIVATE_DIRECTORY [PORT]\n");exit(1);}
if(!is_dir($private))mkdir($private,0700,true);$private=realpath($private);$root=realpath(dirname(__DIR__));if(str_starts_with(strtolower(str_replace('\\','/',$private).'/'),strtolower(str_replace('\\','/',$root).'/')))throw new \RuntimeException('Private directory cannot be inside repository/web root.');
$file=$private.'/config.php';$port=max(1024,min(65535,(int)($argv[2]??4181)));
if(!is_file($file)){
    $config=['environment'=>'local','base_path'=>'','site_url'=>'http://127.0.0.1:'.$port,'private_dir'=>$private,'dsn'=>'sqlite:'.$private.'/elephant.sqlite','db_user'=>null,'db_password'=>null,'secret'=>bin2hex(random_bytes(32)),'mail_transport'=>'log','smtp'=>[]];
    file_put_contents($file,"<?php\nreturn ".var_export($config,true).";\n");chmod($file,0600);
}
putenv('ELEPHANT_CONFIG='.$file);require __DIR__.'/migrate.php';
if(!query('SELECT email FROM admin_users LIMIT 1')->fetch()){
    $password=rtrim(strtr(base64_encode(random_bytes(24)),'+/','_-'),'=');
    query('INSERT INTO admin_users(email,password_hash) VALUES(?,?)',['admin@elephant.local',password_hash($password,PASSWORD_DEFAULT)]);
    file_put_contents($private.'/local-login.txt',"Local administration only\nURL: http://127.0.0.1:$port/panel/\nEmail: admin@elephant.local\nPassword: $password\n");chmod($private.'/local-login.txt',0600);
}
echo "Local configuration ready. Login details: ".$private."/local-login.txt\n";
