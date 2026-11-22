<?php
declare(strict_types=1);
namespace Elephant;
function migrate_database(): void {
// Portable schema used by the application; JSON state is locked as a single transaction unit.
$text=driver()==='mysql'?'LONGTEXT':'TEXT';
$engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
db()->exec("CREATE TABLE IF NOT EXISTS elephant_state(id INTEGER PRIMARY KEY, revision INTEGER NOT NULL, body $text NOT NULL)".$engine);
db()->exec('CREATE TABLE IF NOT EXISTS admin_users(email VARCHAR(254) PRIMARY KEY,password_hash VARCHAR(255) NOT NULL)'.$engine);
db()->exec('CREATE TABLE IF NOT EXISTS rate_limits(bucket_key VARCHAR(128) PRIMARY KEY,hits INTEGER NOT NULL,expires_at BIGINT NOT NULL)'.$engine);
db()->exec("CREATE TABLE IF NOT EXISTS mail_outbox(id VARCHAR(32) PRIMARY KEY,deduplication_key VARCHAR(190) NOT NULL UNIQUE,recipient VARCHAR(254) NOT NULL,subject VARCHAR(255) NOT NULL,body $text NOT NULL,status VARCHAR(20) NOT NULL,attempts INTEGER NOT NULL DEFAULT 0,available_at BIGINT NOT NULL,claimed_at BIGINT NULL,sent_at VARCHAR(30) NULL,last_error TEXT NULL)".$engine);
if(!query('SELECT id FROM elephant_state WHERE id=1')->fetch()){
    $seed=json_decode(file_get_contents(dirname(__DIR__).'/seed.json'),true,512,JSON_THROW_ON_ERROR);
    $seed=validate_state($seed,$seed);query('INSERT INTO elephant_state(id,revision,body) VALUES(1,1,?)',[json_encode($seed,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);

}


}
