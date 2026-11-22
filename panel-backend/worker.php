<?php
declare(strict_types=1);
namespace Elephant;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/src/bootstrap.php';
$max=max(1,min(100,(int)($argv[1]??20)));$count=0;while($count<$max&&deliver_one())$count++;
echo "Processed $count queued messages (".config()['mail_transport'].").\n";
