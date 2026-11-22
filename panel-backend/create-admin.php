<?php
declare(strict_types=1);
namespace Elephant;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/src/bootstrap.php';
$address=$argv[1]??'';$passwordFile=$argv[2]??'';
try {
    $address=email($address);if(!$passwordFile||!is_file($passwordFile))throw new \RuntimeException('Usage: php create-admin.php email /absolute/private/password-file');
    $pass=trim(file_get_contents($passwordFile));if(strlen($pass)<14||strlen($pass)>72)throw new \RuntimeException('Password must have 14–72 bytes.');
    if(query('SELECT email FROM admin_users WHERE email=?',[$address])->fetch())throw new \RuntimeException('Account already exists. No changes made.');
    query('INSERT INTO admin_users(email,password_hash) VALUES(?,?)',[$address,password_hash($pass,PASSWORD_DEFAULT)]);echo "Admin account created. Password not printed.\n";
}catch(\Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
