<?php
declare(strict_types=1);
namespace Elephant;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/src/bootstrap.php';
require __DIR__.'/src/migration.php';
migrate_database();
echo "Database ready (".driver().").\n";
