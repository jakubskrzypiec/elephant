<?php
declare(strict_types=1);
namespace Elephant;

function queue_mail(string $recipient,string $subject,string $body,string $key): void {
    $q=query('SELECT id FROM mail_outbox WHERE deduplication_key=?',[$key]);if($q->fetch())return;
    query('INSERT INTO mail_outbox(id,deduplication_key,recipient,subject,body,status,attempts,available_at) VALUES(?,?,?,?,?,?,?,?)',[id(),$key,$recipient,$subject,$body,'pending',0,time()]);
}
function mail_health(): array { $rows=query('SELECT status, COUNT(*) AS total FROM mail_outbox GROUP BY status')->fetchAll();$out=['transport'=>config()['mail_transport'],'pending'=>0,'sent'=>0,'simulated'=>0,'failed'=>0];foreach($rows as $r)$out[$r['status']]=(int)$r['total'];return $out; }
function respond_after_mail(array $response,int $status=200): never {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    try{for($i=0;$i<2&&deliver_one();$i++);}catch(\Throwable $e){error_log('[Elephant mail queue] '.$e->getMessage());}
    json_response($response,$status);
}
function deliver_one(): bool {
    $mail=transaction(function(){query("UPDATE mail_outbox SET status='pending' WHERE status='processing' AND claimed_at < ?",[time()-600]);$q=query("SELECT * FROM mail_outbox WHERE status='pending' AND available_at<=? ORDER BY available_at LIMIT 1".(driver()==='mysql'?' FOR UPDATE':''),[time()]);$r=$q->fetch();if(!$r)return null;query("UPDATE mail_outbox SET status='processing',claimed_at=? WHERE id=?",[time(),$r['id']]);return $r;});
    if(!$mail)return false;
    try {
        $c=config();
        if($c['mail_transport']==='log'){
            if($c['environment']!=='local')throw new \RuntimeException('Log transport is restricted to local development.');
            $dir=$c['private_dir'].'/mail';if(!is_dir($dir))mkdir($dir,0700,true);
            $content="To: {$mail['recipient']}\r\nSubject: {$mail['subject']}\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Elephant-Transport: local-simulation\r\n\r\n{$mail['body']}";
            if(file_put_contents($dir.'/'.$mail['id'].'.eml',$content,LOCK_EX)===false)throw new \RuntimeException('Cannot write local mailbox.');
            query("UPDATE mail_outbox SET status='simulated',sent_at=?,last_error=NULL WHERE id=?",[utc_now(),$mail['id']]);
        }else{
            $smtp=$c['smtp']??[];if(empty($smtp['host'])||empty($smtp['from']))throw new \RuntimeException('SMTP is not configured.');
            foreach(['Exception','PHPMailer','SMTP'] as $class)require_once ROOT.'/panel-backend/vendor/PHPMailer/src/'.$class.'.php';
            $m=new \PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=$smtp['host'];$m->Port=(int)($smtp['port']??587);$m->SMTPAuth=true;$m->Username=$smtp['username'];$m->Password=$smtp['password'];$m->SMTPSecure=$smtp['encryption']??'tls';$m->CharSet='UTF-8';$m->Timeout=15;
            if(!in_array($m->SMTPSecure,['tls','ssl'],true))throw new \RuntimeException('SMTP requires TLS.');
            $m->setFrom($smtp['from'],$smtp['from_name']??'ELEPHANT Interior Design');$m->addAddress($mail['recipient']);$m->Subject=$mail['subject'];$m->Body=$mail['body'];$m->send();
            query("UPDATE mail_outbox SET status='sent',sent_at=?,last_error=NULL WHERE id=?",[utc_now(),$mail['id']]);
        }
    }catch(\Throwable $e){$attempts=(int)$mail['attempts']+1;query('UPDATE mail_outbox SET status=?,attempts=?,available_at=?,last_error=? WHERE id=?',[$attempts>=5?'failed':'pending',$attempts,time()+min(3600,60*($attempts**2)),substr($e->getMessage(),0,1000),$mail['id']]);}
    return true;
}
