<?php
declare(strict_types=1);
namespace Elephant;
// No real Google calls or email: an isolated SQLite database and a local provider fixture.
ob_start();$argv[1]=sys_get_temp_dir().'/elephant-google-test-'.bin2hex(random_bytes(8));$argv[2]='4187';require dirname(__DIR__).'/setup-local.php';ob_end_clean();
$GLOBALS['google_fixture']=['events'=>[],'insertions'=>0,'refreshes'=>0,'calls'=>0,'pending'=>false,'revoked'=>false,'conflict'=>false,'partial'=>false];
function check(bool $value,string $message): void { if(!$value)throw new \RuntimeException($message); }
function fails(callable $fn,int $status): void {try{$fn();}catch(InputError $e){check($e->getCode()===$status,'Unexpected failure: '.$e->getMessage());return;}throw new \RuntimeException('Expected a controlled failure.');}
function google_test_transport(string $method,string $url,?array $body,?string $token,bool $form): array {
    $f=&$GLOBALS['google_fixture'];$f['calls']++;
    if($url==='https://oauth2.googleapis.com/token'){
        if(($body['grant_type']??'')==='refresh_token'){$f['refreshes']++;if($f['revoked'])return ['status'=>400,'data'=>['error'=>'invalid_grant']];}
        else check(strlen($body['code_verifier']??'')>=43,'PKCE verifier missing.');
        return ['status'=>200,'data'=>['access_token'=>'fake-local-access-token','refresh_token'=>'fake-local-refresh-token','expires_in'=>3600,'scope'=>$f['partial']?'openid email':'openid email '.GOOGLE_CALENDAR_SCOPE]];
    }
    if(str_ends_with($url,'/userinfo'))return ['status'=>200,'data'=>['sub'=>'local-google-account','email'=>'calendar@example.com','email_verified'=>true]];
    if($url==='https://www.googleapis.com/calendar/v3/calendars'&&$method==='POST')return ['status'=>200,'data'=>['id'=>'elephant-local@group.calendar.google.com']];
    $id=basename(parse_url($url,PHP_URL_PATH));
    if($method==='POST'){
        $id=$body['id'];if(isset($f['events'][$id]))return ['status'=>409,'data'=>[]];$f['insertions']++;
        $f['events'][$id]=$body;
    }elseif($method==='PATCH'){
        check(isset($f['events'][$id]),'Cannot patch missing event.');$f['events'][$id]=array_replace($f['events'][$id],$body);
    }elseif($method==='DELETE'){
        if(!isset($f['events'][$id]))return ['status'=>404,'data'=>[]];$f['events'][$id]['status']='cancelled';return ['status'=>204,'data'=>[]];
    }elseif(!isset($f['events'][$id]))return ['status'=>404,'data'=>[]];
    if(!$f['pending']&&($f['events'][$id]['status']??'')!=='cancelled')$f['events'][$id]['hangoutLink']='https://meet.google.com/abc-defg-hij';
    if($f['conflict']){$f['conflict']=false;transaction(function(){$r=state_row(true);write_state($r['state'],$r['revision']);});}
    return ['status'=>200,'data'=>$f['events'][$id]];
}
function flow(bool $finish=true): array {
    parse_str(parse_url(google_start(['email'=>'admin@elephant.local']),PHP_URL_QUERY),$args);
    check($args['access_type']==='offline'&&$args['code_challenge_method']==='S256','Offline PKCE flow missing.');
    check($args['redirect_uri']==='http://127.0.0.1:4187/panel/google.php','Unexpected redirect URI.');
    $path=google_dir().'/oauth-'.hash('sha256',$args['state']).'.enc';$pending=google_decrypt(file_get_contents($path));$cookie=str_repeat('a',64);$pending['browser']=hash('sha256',$cookie);google_save($path,$pending);
    $params=['state'=>$args['state'],'code'=>'local-test-code'];if($finish)google_finish($params,$cookie);return [$params,$cookie];
}
check(google_status()['configured']===false,'Connection should start unconfigured.');
file_put_contents(config()['private_dir'].'/google-client.json',json_encode(['web'=>['client_id'=>'local-test.apps.googleusercontent.com','client_secret'=>'local-test-client-secret']]));
$sealed=google_encrypt(['secret'=>'local-only']);check(google_decrypt($sealed)['secret']==='local-only','Encrypted store round trip failed.');
$tampered=base64_decode($sealed);$tampered[30]=chr(ord($tampered[30])^1);try{google_decrypt(base64_encode($tampered));throw new \RuntimeException('Tampered ciphertext was accepted.');}catch(\RuntimeException $e){check($e->getMessage()!=='Tampered ciphertext was accepted.','Authentication tag was not checked.');}
fails(fn()=>google_finish(['state'=>str_repeat('0',64),'code'=>'x'],str_repeat('a',64)),403);
[$params,$cookie]=flow(false);fails(fn()=>google_finish($params,str_repeat('b',64)),403);
$GLOBALS['google_fixture']['partial']=true;[$params,$cookie]=flow(false);fails(fn()=>google_finish($params,$cookie),422);$GLOBALS['google_fixture']['partial']=false;
[$params,$cookie]=flow();fails(fn()=>google_finish($params,$cookie),403);
check(google_status()['connected']&&google_status()['email']==='calendar@example.com','OAuth account not connected.');
check(!str_contains(file_get_contents(google_dir().'/connection.enc'),'fake-local-refresh-token'),'Refresh token stored in plaintext.');
$date=(new \DateTimeImmutable('+14 days',zone()))->modify('next monday')->format('Y-m-d');$snapshot=state_row();$snapshot['state']['bookings'][]=['id'=>'google-booking-test','name'=>'Test Google','email'=>'client@example.com','phone'=>'123456789','date'=>$date,'time'=>'10:00','status'=>'pending','notes'=>'Private note never sent to Google'];
$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);$snapshot['state']['bookings'][0]['status']='confirmed';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);
$booking=$snapshot['state']['bookings'][0];check($booking['meetingUrl']==='https://meet.google.com/abc-defg-hij','Meet URL not saved.');check($GLOBALS['google_fixture']['insertions']===1,'Event was duplicated.');
$event=array_values($GLOBALS['google_fixture']['events'])[0];check(!str_contains($event['description'],'Private note'),'Private notes sent to provider.');check($event['start']['timeZone']==='Europe/Warsaw','Timezone missing.');
$mail=query("SELECT body FROM mail_outbox WHERE subject=? ORDER BY available_at DESC",['Potwierdzenie konsultacji ELEPHANT'])->fetch();check(str_contains($mail['body'],$booking['meetingUrl']),'Confirmation email has no Meet link.');
$beforeMails=(int)query('SELECT COUNT(*) AS n FROM mail_outbox')->fetch()['n'];$snapshot['state']['bookings'][0]['notes']='Changed internal note';$snapshot['state']['bookings'][0]['meetingUrl']='https://evil.invalid';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check($snapshot['state']['bookings'][0]['meetingUrl']===$booking['meetingUrl'],'Browser forged a Meet URL.');check((int)query('SELECT COUNT(*) AS n FROM mail_outbox')->fetch()['n']===$beforeMails,'Internal note sent duplicate confirmation.');
$snapshot['state']['bookings'][0]['time']='11:00';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check($GLOBALS['google_fixture']['insertions']===1,'Rescheduling duplicated the event.');check(str_contains(array_values($GLOBALS['google_fixture']['events'])[0]['start']['dateTime'],'11:00'),'Google event not rescheduled.');
$snapshot['state']['bookings'][0]['status']='cancelled';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check(!isset($snapshot['state']['bookings'][0]['meetingUrl']),'Cancelled booking kept an active link.');check(array_values($GLOBALS['google_fixture']['events'])[0]['status']==='cancelled','Google event was not cancelled.');
$snapshot['state']['bookings'][0]['status']='confirmed';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check($GLOBALS['google_fixture']['insertions']===2,'Restored booking must use a fresh event id.');
$snapshot['state']['bookings'][]=['id'=>'google-pending-link','name'=>'Meet pending','email'=>'pending@example.com','date'=>$date,'time'=>'13:00','status'=>'confirmed','notes'=>''];$GLOBALS['google_fixture']['pending']=true;$oldRevision=$snapshot['revision'];$beforeMails=(int)query('SELECT COUNT(*) AS n FROM mail_outbox')->fetch()['n'];fails(fn()=>google_save_panel_state($snapshot['state'],$snapshot['revision']),503);check(state_row()['revision']===$oldRevision,'Failed conferencing changed the booking.');check((int)query('SELECT COUNT(*) AS n FROM mail_outbox')->fetch()['n']===$beforeMails,'A confirmation without Meet was queued.');$inserts=$GLOBALS['google_fixture']['insertions'];$GLOBALS['google_fixture']['pending']=false;$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check($GLOBALS['google_fixture']['insertions']===$inserts,'Retry created a duplicate event.');
$GLOBALS['google_fixture']['conflict']=true;$snapshot['state']['bookings'][0]['time']='09:00';fails(fn()=>google_save_panel_state($snapshot['state'],$snapshot['revision']),409);check(state_row()['state']['bookings'][0]['time']==='11:00','Concurrent update overwritten.');$snapshot=state_row();
google_with_lock(function(&$d){$d['expires_at']=0;});$snapshot['state']['bookings'][0]['time']='10:00';$snapshot=google_save_panel_state($snapshot['state'],$snapshot['revision']);check($GLOBALS['google_fixture']['refreshes']===1,'Access token was not refreshed.');
google_with_lock(function(&$d){$d['expires_at']=0;});$GLOBALS['google_fixture']['revoked']=true;$snapshot['state']['bookings'][0]['time']='09:00';fails(fn()=>google_save_panel_state($snapshot['state'],$snapshot['revision']),409);check(!google_status()['connected'],'Revoked Google grant remains connected.');
echo "PASS Google Calendar fixture: OAuth state/browser binding/PKCE/replay/scope, encrypted tokens, refresh/revocation, Meet confirmation mail, no forged links/private notes, no duplicate events, reschedule/cancel, pending conference rollback and revision conflict. No real Google requests or email.\n";
