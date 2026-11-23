<?php
declare(strict_types=1);
namespace Elephant;

const GOOGLE_CALENDAR_SCOPE='https://www.googleapis.com/auth/calendar.app.created';
function google_client(): ?array {
    $path=config()['private_dir'].'/google-client.json';
    if(!is_file($path))return null;
    $data=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$c=$data['web']??$data;
    if(!is_array($c)||!preg_match('/^[a-zA-Z0-9._-]+\.apps\.googleusercontent\.com$/D',$c['client_id']??'')||strlen($c['client_secret']??'')<12)throw new \RuntimeException('Invalid private Google client configuration.');
    return $c;
}
function google_redirect_uri(): string { return url('panel/google.php'); }
function google_dir(): string {
    $dir=config()['private_dir'].'/google';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Cannot create private Google directory.');return $dir;
}
function google_encrypt(array $data): string {
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',hash('sha256',config()['secret'],true),OPENSSL_RAW_DATA,$iv,$tag,'elephant-google-v1');
    if($cipher===false)throw new \RuntimeException('Cannot protect Google connection.');return base64_encode($iv.$tag.$cipher);
}
function google_decrypt(string $data): array {
    $raw=base64_decode($data,true);if($raw===false||strlen($raw)<29)throw new \RuntimeException('Invalid protected Google data.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',config()['secret'],true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'elephant-google-v1');
    if($plain===false)throw new \RuntimeException('Cannot read protected Google data.');return json_decode($plain,true,64,JSON_THROW_ON_ERROR);
}
function google_save(string $path,array $data): void {
    $tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';if(file_put_contents($tmp,google_encrypt($data),LOCK_EX)===false)throw new \RuntimeException('Cannot save Google connection.');chmod($tmp,0600);
    if(!rename($tmp,$path)){@unlink($tmp);throw new \RuntimeException('Cannot replace Google connection.');}
}
function google_with_lock(callable $fn): mixed {
    $dir=google_dir();$lock=fopen($dir.'/connection.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Cannot lock Google connection.');
    $path=$dir.'/connection.enc';$data=is_file($path)?google_decrypt(file_get_contents($path)):[];
    try{return $fn($data);}finally{google_save($path,$data);flock($lock,LOCK_UN);fclose($lock);}
}
function google_status(): array {
    $configured=google_client()!==null;
    return google_with_lock(fn(&$d)=>['configured'=>$configured,'connected'=>$configured&&!empty($d['refresh_token']),'email'=>$d['email']??null]);
}
function google_http(string $method,string $url,?array $body=null,?string $token=null,bool $form=false): array {
    if(!preg_match('~^https://(?:oauth2\.googleapis\.com|www\.googleapis\.com)/~D',$url))throw new \RuntimeException('Google endpoint is not allowed.');
    // Only a locally defined test fixture can replace Google; production always uses verified TLS.
    if(config()['environment']==='local'&&function_exists(__NAMESPACE__.'\\google_test_transport'))return google_test_transport($method,$url,$body,$token,$form);
    $headers=['Accept: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;
    $content=null;if($body!==null){$content=$form?http_build_query($body):json_encode($body,JSON_THROW_ON_ERROR);$headers[]='Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json');}
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$content??'','timeout'=>8,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $raw=@file_get_contents($url,false,$context);$status=0;foreach($http_response_header??[] as $h)if(preg_match('~^HTTP/\S+ (\d+)~',$h,$m))$status=(int)$m[1];
    if($raw===false||!$status)throw new InputError('Google chwilowo nie odpowiada. Rezerwacja nie została zmieniona. Spróbuj ponownie.',503);
    return ['status'=>$status,'data'=>$raw===''?[]:(json_decode($raw,true)?:[])];
}
function google_start(array $user): string {
    $client=google_client();if(!$client)invalid('Połączenie Google nie zostało jeszcze skonfigurowane przez wykonawcę.',503);
    $state=bin2hex(random_bytes(32));$browser=bin2hex(random_bytes(32));$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
    $row=query('SELECT password_hash FROM admin_users WHERE email=?',[$user['email']])->fetch();
    google_save(google_dir().'/oauth-'.hash('sha256',$state).'.enc',['expires'=>time()+600,'browser'=>hash('sha256',$browser),'verifier'=>$verifier,'user'=>$user['email'],'credential'=>hash('sha256',$row['password_hash'])]);
    setcookie('elephant_google_oauth',$browser,['expires'=>time()+600,'path'=>base('panel/google.php'),'secure'=>config()['environment']==='production','httponly'=>true,'samesite'=>'Lax']);
    return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$client['client_id'],'redirect_uri'=>google_redirect_uri(),'response_type'=>'code','scope'=>'openid email '.GOOGLE_CALENDAR_SCOPE,'state'=>$state,'access_type'=>'offline','prompt'=>'consent select_account','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256']);
}
function google_finish(array $params,string $browser): void {
    $state=$params['state']??'';if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/D',$state)||!preg_match('/^[a-f0-9]{64}$/D',$browser))invalid('Nieprawidłowa sesja połączenia Google. Wróć do panelu i spróbuj ponownie.',403);
    $path=google_dir().'/oauth-'.hash('sha256',$state).'.enc';$claimed=$path.'.used';
    if(!is_file($path)||!rename($path,$claimed))invalid('Połączenie wygasło lub zostało już użyte. Wróć do panelu i spróbuj ponownie.',403);
    try{$pending=google_decrypt(file_get_contents($claimed));}finally{@unlink($claimed);}
    if(($pending['expires']??0)<time()||!hash_equals($pending['browser']??'',hash('sha256',$browser)))invalid('Połączenie Google wygasło. Spróbuj ponownie z panelu.',403);
    $admin=query('SELECT password_hash FROM admin_users WHERE email=?',[$pending['user']])->fetch();if(!$admin||!hash_equals($pending['credential'],hash('sha256',$admin['password_hash'])))invalid('Zaloguj się ponownie do panelu przed połączeniem Google.',403);
    if(isset($params['error']))invalid('Nie udzielono dostępu do kalendarza. Możesz ponownie połączyć Google w ustawieniach.');
    $code=$params['code']??'';if(!is_string($code)||!$code||strlen($code)>4096)invalid('Brakuje kodu połączenia Google.');
    $c=google_client();$r=google_http('POST','https://oauth2.googleapis.com/token',['code'=>$code,'client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'redirect_uri'=>google_redirect_uri(),'grant_type'=>'authorization_code','code_verifier'=>$pending['verifier']],null,true);$t=$r['data'];
    if($r['status']!==200||empty($t['access_token'])||empty($t['refresh_token']))invalid('Google nie udostępnił trwałego połączenia. Spróbuj ponownie i zaakceptuj dostęp do kalendarza.',502);
    if(!in_array(GOOGLE_CALENDAR_SCOPE,explode(' ',$t['scope']??''),true))invalid('Nie zaakceptowano dostępu do Kalendarza Google. Spróbuj ponownie.');
    $profile=google_http('GET','https://www.googleapis.com/oauth2/v3/userinfo',null,$t['access_token']);$p=$profile['data'];
    if($profile['status']!==200||empty($p['email_verified'])||!filter_var($p['email']??'',FILTER_VALIDATE_EMAIL)||empty($p['sub']))invalid('Nie udało się potwierdzić konta Google.',502);
    google_with_lock(function(&$data)use($t,$p){
        if(!empty($data['sub'])&&!hash_equals($data['sub'],$p['sub']))invalid('Ten panel był połączony z innym kontem Google. Połącz ponownie to samo konto lub skontaktuj się z wykonawcą.');
        $data=array_merge($data,['email'=>$p['email'],'sub'=>$p['sub'],'access_token'=>$t['access_token'],'refresh_token'=>$t['refresh_token'],'expires_at'=>time()+(int)($t['expires_in']??3600),'connected_at'=>gmdate(DATE_ATOM)]);
        if(empty($data['calendar_id'])){
            $calendar=google_http('POST','https://www.googleapis.com/calendar/v3/calendars',['summary'=>'ELEPHANT — konsultacje','description'=>'Konsultacje zarządzane z panelu ELEPHANT Interior Design.','timeZone'=>'Europe/Warsaw'],$data['access_token']);
            if(!in_array($calendar['status'],[200,201],true)||empty($calendar['data']['id'])){unset($data['refresh_token'],$data['access_token']);invalid('Nie udało się utworzyć kalendarza konsultacji. Sprawdź, czy Google Calendar API jest włączone i spróbuj ponownie.',502);}
            $data['calendar_id']=$calendar['data']['id'];
        }
    });
}
function google_access_token(array &$d): string {
    if(empty($d['refresh_token']))invalid('Połącz Kalendarz Google w ustawieniach panelu.',409);
    if(!empty($d['access_token'])&&($d['expires_at']??0)>time()+60)return $d['access_token'];
    $c=google_client();$r=google_http('POST','https://oauth2.googleapis.com/token',['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'refresh_token'=>$d['refresh_token'],'grant_type'=>'refresh_token'],null,true);
    if($r['status']!==200||empty($r['data']['access_token'])){if(($r['data']['error']??'')==='invalid_grant'){unset($d['refresh_token'],$d['access_token']);}invalid('Połączenie z Google wygasło. Połącz konto ponownie w ustawieniach. Rezerwacja nie została zmieniona.',409);}
    $d['access_token']=$r['data']['access_token'];$d['expires_at']=time()+(int)($r['data']['expires_in']??3600);if(!empty($r['data']['refresh_token']))$d['refresh_token']=$r['data']['refresh_token'];return $d['access_token'];
}
function google_event_request(array &$d,string $method,string $suffix,?array $body=null): array {
    if(empty($d['calendar_id']))invalid('Połącz ponownie Google, aby utworzyć kalendarz konsultacji.',409);
    $url='https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($d['calendar_id']).'/events'.$suffix;
    $r=google_http($method,$url,$body,google_access_token($d));
    if($r['status']===401){$d['expires_at']=0;$r=google_http($method,$url,$body,google_access_token($d));}
    return $r;
}
function google_meet_link(array $event): ?string {
    $links=[$event['hangoutLink']??''];foreach($event['conferenceData']['entryPoints']??[] as $e)if(($e['entryPointType']??'')==='video')$links[]=$e['uri']??'';
    foreach($links as $link)if(is_string($link)&&preg_match('~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$~D',$link))return $link;return null;
}
function google_sync_booking(array &$d,array &$b,int $attempt=0): void {
    if($attempt>2)invalid('Nie można ponownie utworzyć wydarzenia Google. Spróbuj ponownie lub skontaktuj się z wykonawcą.',502);
    $record=$d['events'][$b['id']]??['generation'=>0];
    if($b['status']==='cancelled'){
        if(!empty($record['event_id'])&&empty($record['cancelled'])){$r=google_event_request($d,'DELETE','/'.rawurlencode($record['event_id']).'?sendUpdates=none');if(!in_array($r['status'],[204,404,410],true))invalid('Nie udało się anulować wydarzenia Google. Spróbuj ponownie.',502);$record['cancelled']=true;$d['events'][$b['id']]=$record;}
        unset($b['meetingUrl']);return;
    }
    if($b['status']!=='confirmed')return;
    if(!empty($record['cancelled']))$record=['generation'=>(int)$record['generation']+1];
    $eventId=$record['event_id']??'el'.hash('sha256',$d['sub'].'|'.url().'|'.$b['id'].'|'.$record['generation']);
    $existing=google_event_request($d,'GET','/'.$eventId);$event=$existing['data'];
    if(!in_array($existing['status'],[200,404,410],true))invalid('Nie można odczytać wydarzenia Google. Sprawdź połączenie konta i spróbuj ponownie.',502);
    if($existing['status']===200&&($event['extendedProperties']['private']['elephantBookingId']??'')!==$b['id'])invalid('Niezgodne wydarzenie Google. Skontaktuj się z wykonawcą.',409);
    if(($event['status']??'')==='cancelled'||$existing['status']===410){$record=['generation'=>(int)$record['generation']+1];$d['events'][$b['id']]=$record;google_sync_booking($d,$b,$attempt+1);return;}
    $start=local_datetime($b['date'],$b['time']);$payload=['summary'=>'Konsultacja ELEPHANT · '.$b['name'],'description'=>"Konsultacja z rezerwacji na stronie ELEPHANT.\nKlient: {$b['name']}\nE-mail: {$b['email']}\nTelefon: ".($b['phone']??''),'start'=>['dateTime'=>$start->format(DATE_ATOM),'timeZone'=>'Europe/Warsaw'],'end'=>['dateTime'=>$start->modify('+'.($b['duration']??60).' minutes')->format(DATE_ATOM),'timeZone'=>'Europe/Warsaw'],'extendedProperties'=>['private'=>['elephantBookingId'=>$b['id']]],'visibility'=>'private'];
    if(!google_meet_link($event))$payload['conferenceData']=['createRequest'=>['requestId'=>$eventId,'conferenceSolutionKey'=>['type'=>'hangoutsMeet']]];
    $create=$existing['status']!==200;if($create)$payload['id']=$eventId;
    $r=google_event_request($d,$create?'POST':'PATCH',($create?'':'/'.$eventId).'?conferenceDataVersion=1&sendUpdates=none',$payload);
    if($r['status']===409&&$create)$r=google_event_request($d,'GET','/'.$eventId);
    if(!in_array($r['status'],[200,201],true))invalid('Google nie utworzył spotkania. Sprawdź dostęp do Kalendarza i Google Meet. Rezerwacja nie została potwierdzona.',502);
    $d['events'][$b['id']]=['generation'=>$record['generation'],'event_id'=>$eventId];$event=$r['data'];$link=google_meet_link($event);
    for($i=0;!$link&&$i<3;$i++){if(config()['environment']!=='local')usleep(500000);$r=google_event_request($d,'GET','/'.$eventId);if($r['status']!==200)break;$event=$r['data'];$link=google_meet_link($event);}
    if(!$link)invalid('Google przygotowuje link Meet lub konto nie obsługuje tworzenia Meet. Rezerwacja nie została jeszcze potwierdzona. Spróbuj zapisać ją ponownie za chwilę.',503);
    $b['meetingUrl']=$link;
}
function google_sync_bookings(array &$d,array &$next,array $old): void {
    if(!google_client())return;
    $previous=array_column($old['bookings'],null,'id');
    foreach($next['bookings'] as &$b){$p=$previous[$b['id']]??null;$keys=['status','date','time','email','name','duration'];$changed=!$p;foreach($keys as $k)if(($b[$k]??null)!==($p[$k]??null))$changed=true;
        if($changed&&$b['status']==='confirmed'&&empty($d['refresh_token']))invalid('Połącz Kalendarz Google w ustawieniach przed potwierdzeniem konsultacji.',409);
        if($changed&&!empty($d['refresh_token'])&&in_array($b['status'],['confirmed','cancelled'],true))google_sync_booking($d,$b);
    }unset($b);
}
function google_save_panel_state(array $input,int $revision): array {
    return google_with_lock(function(&$connection)use($input,$revision){
        $row=state_row();if($row['revision']!==$revision)invalid('Dane zmieniły się w innej karcie lub wpłynęło nowe zgłoszenie. Odśwież panel przed zapisem.',409);
        $next=validate_state($input,$row['state']);google_sync_bookings($connection,$next,$row['state']);
        return transaction(function()use($next,$row,$revision){$fresh=state_row(true);if($fresh['revision']!==$revision)invalid('W trakcie zapisu wpłynęły nowe dane. Odśwież panel i zapisz ponownie. Utworzone wydarzenie Google zostanie użyte ponownie.',409);booking_notifications($next,$row['state']);write_state($next,$revision);return ['ok'=>true,'state'=>$next,'revision'=>$revision+1];});
    });
}
