<?php
declare(strict_types=1);
namespace Elephant;
require __DIR__.'/panel-backend/src/bootstrap.php';
try {
    $action=$_GET['action']??'';
    if($action==='image'){
        method('GET');$file=$_GET['file']??'';if(!is_string($file)||!preg_match('/^[a-f0-9]{32}\.(?:webp|png|jpg)$/D',$file))problem('Nie znaleziono zdjęcia.',404);
        session_start_safe();$src='../api.php?action=image&file='.$file;$public=false;
        foreach(state_row()['state']['projects'] as $p)if($p['status']==='published')foreach($p['photos'] as $photo)if($photo['src']===$src)$public=true;
        if(!$public&&!isset($_SESSION['user']))problem('Nie znaleziono zdjęcia.',404);
        $path=config()['private_dir'].'/uploads/'.$file;if(!is_file($path))problem('Nie znaleziono zdjęcia.',404);
        session_write_close();header('Content-Type: '.(new \finfo(FILEINFO_MIME_TYPE))->file($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: '.($public?'public, max-age=3600':'private, no-store'));readfile($path);exit;
    }
    session_start_safe();
    if($action==='session') { method('GET');json_response(['ok'=>true,'authenticated'=>isset($_SESSION['user']),'csrf'=>$_SESSION['csrf'],'user'=>$_SESSION['user']??null,'environment'=>config()['environment']]); }
    if($action==='public') { method('GET');$state=state_row()['state'];json_response(['ok'=>true,'csrf'=>$_SESSION['csrf'],'availability'=>$state['availability'],'email'=>$state['settings']['email']]); }
    if($action==='login'){
        method('POST');require_csrf();rate_limit('login',8,900);$d=request_data();$address=strtolower(email($d['email']??''));$password=text($d['password']??'',200,true);
        $row=query('SELECT email,password_hash FROM admin_users WHERE email=?',[$address])->fetch();
        $hash=$row['password_hash']??'$2y$12$ImiTXuWVxfMxfq/qkzBF5OUlpwJRhE4E4hiJcJIJDfCtCDOzh4PzG';
        $valid=password_verify($password,$hash);if(!$row||!$valid)problem('Nieprawidłowy e-mail lub hasło.',401);
        session_regenerate_id(true);$_SESSION=['user'=>['email'=>$row['email']],'credential_version'=>hash('sha256',$row['password_hash']),'csrf'=>bin2hex(random_bytes(32)),'last_seen'=>time(),'signed_in'=>time()];json_response(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    if($action==='change-password'){
        method('POST');$user=require_user();require_csrf();rate_limit('change-password',8,900);$d=request_data();
        $current=$d['currentPassword']??null;$next=$d['newPassword']??null;$confirm=$d['confirmPassword']??null;
        if(!is_string($current)||!is_string($next)||!is_string($confirm))invalid('Uzupełnij wszystkie pola hasła.');
        if(strlen($next)<14||strlen($next)>72)invalid('Nowe hasło musi mieć od 14 do 72 bajtów.');
        if($next!==$confirm)invalid('Nowe hasła nie są takie same.');
        $row=query('SELECT password_hash FROM admin_users WHERE email=?',[$user['email']])->fetch();
        if(!$row||!password_verify($current,$row['password_hash']))problem('Obecne hasło jest nieprawidłowe.',403);
        if(password_verify($next,$row['password_hash']))invalid('Nowe hasło musi różnić się od obecnego.');
        $hash=password_hash($next,PASSWORD_DEFAULT);
        $updated=query('UPDATE admin_users SET password_hash=? WHERE email=? AND password_hash=?',[$hash,$user['email'],$row['password_hash']]);
        if($updated->rowCount()!==1)problem('Hasło zostało już zmienione. Zaloguj się ponownie.',409);
        $_SESSION=[];session_regenerate_id(true);session_destroy();json_response(['ok'=>true]);
    }
    if($action==='logout'){method('POST');require_csrf();$_SESSION=[];session_regenerate_id(true);session_destroy();json_response(['ok'=>true]);}
    if($action==='slots'){
        method('GET');rate_limit('slots',120,60);$date=iso_date($_GET['date']??'');$state=state_row()['state'];$out=slots($date,$state);json_response(['ok'=>true,'date'=>$date,'timezone'=>'Europe/Warsaw','duration'=>$state['availability']['duration'],'slots'=>$out]);
    }
    if($action==='contact'||$action==='reserve'){
        method('POST');require_csrf();rate_limit('public-submit',8,3600);$d=request_data();
        if(!empty($d['_honey']))json_response(['ok'=>true,'message'=>'Dziękujemy za wiadomość.']);
        if(!in_array($d['consent']??null,[true,'on','1',1],true))invalid('Zaznacz zgodę na kontakt.');
        $name=text(trim(($d['name']??'').' '.($d['surname']??'')),300,true);$address=email($d['email']??'');$phone=text($d['phone']??'',80);
        $result=transaction(function()use($action,$d,$name,$address,$phone){
            $row=state_row(true);$old=$row['state'];$state=$old;
            if($action==='contact'){
                $details=[];foreach(['oferta','typ','metraz','city','condition','market','start','source'] as $key)$details[$key]=text((string)($d[$key]??''),1000);
                $message=text($d['message']??'',20000);if(!$message&&!$details['oferta'])invalid('Opisz projekt lub wybierz zakres współpracy.');
                $entry=['id'=>id(),'name'=>$name,'email'=>$address,'phone'=>$phone,'topic'=>$details['oferta']?:'Wiadomość z formularza','message'=>$message,'details'=>$details,'status'=>'new','date'=>gmdate(DATE_ATOM),'consentAt'=>gmdate(DATE_ATOM),'notes'=>''];
                if(count($state['inquiries'])>=10000)throw new \RuntimeException('Limit zgłoszeń. Skontaktuj się z pracownią e-mailem.');
                array_unshift($state['inquiries'],$entry);
                queue_mail($state['settings']['email'],'Nowe zapytanie ze strony ELEPHANT',"Osoba: $name\nE-mail: $address\nTelefon: $phone\n\n".$message."\n\n".json_encode($details,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),'inquiry:'.$entry['id']);
                $response=['ok'=>true,'message'=>'Dziękujemy. Twoje zapytanie zostało zapisane i przekazane do pracowni.'];
            }else{
                $entry=['id'=>id(),'name'=>$name,'email'=>$address,'phone'=>$phone,'date'=>iso_date($d['date']??''),'time'=>time_value($d['time']??''),'status'=>'pending','notes'=>text($d['message']??'',5000),'duration'=>$state['availability']['duration'],'buffer'=>$state['availability']['buffer'],'createdAt'=>gmdate(DATE_ATOM),'consentAt'=>gmdate(DATE_ATOM)];
                check_booking($entry,$state);$start=local_datetime($entry['date'],$entry['time'])->setTimezone(new \DateTimeZone('UTC'));$entry['startsAt']=$start->format(DATE_ATOM);$entry['endsAt']=$start->modify('+'.$entry['duration'].' minutes')->format(DATE_ATOM);if(count($state['bookings'])>=10000)throw new \RuntimeException('Limit rezerwacji. Skontaktuj się z pracownią.');
                $state['bookings'][]=$entry;booking_notifications($state,$old);
                queue_mail($state['settings']['email'],'Nowa rezerwacja konsultacji ELEPHANT',booking_details($entry)."\nE-mail: $address\n\nOtwórz panel, aby potwierdzić termin.",'booking-admin:'.$entry['id']);
                $response=['ok'=>true,'message'=>'Termin został zarezerwowany i oczekuje na potwierdzenie pracowni.'];
            }
            write_state($state,$row['revision']);return $response;
        });respond_after_mail($result,201);
    }
    require_user();
    if($action==='delete-inquiry'){
        method('POST');require_csrf();$d=request_data();$id=item_id($d['id']??'');$revision=integer($d['revision']??null,1,PHP_INT_MAX);
        $result=transaction(function()use($id,$revision){
            $row=state_row(true);if($row['revision']!==$revision)invalid('Dane zmieniły się w innej karcie lub wpłynęło nowe zgłoszenie. Odśwież panel przed usunięciem.',409);
            $next=$row['state'];$next['inquiries']=array_values(array_filter($next['inquiries'],fn($inquiry)=>$inquiry['id']!==$id));
            if(count($next['inquiries'])===count($row['state']['inquiries']))problem('Nie znaleziono zapytania.',404);
            write_state($next,$revision);return ['ok'=>true,'state'=>$next,'revision'=>$revision+1];
        });json_response($result);
    }
    if($action==='state'){
        if($_SERVER['REQUEST_METHOD']==='GET'){$row=state_row();json_response(['ok'=>true]+$row+['delivery'=>mail_health(),'environment'=>config()['environment']]);}
        method('PUT');require_csrf();$d=request_data();$revision=integer($d['revision']??null,1,PHP_INT_MAX);if(!is_array($d['state']??null))invalid('Nieprawidłowy zapis panelu.');
        $result=transaction(function()use($d,$revision){$row=state_row(true);if($row['revision']!==$revision)invalid('Dane zmieniły się w innej karcie lub wpłynęło nowe zgłoszenie. Odśwież panel przed zapisem.',409);$next=validate_state($d['state'],$row['state']);booking_notifications($next,$row['state']);write_state($next,$revision);return ['ok'=>true,'state'=>$next,'revision'=>$revision+1];});respond_after_mail($result);
    }
    if($action==='upload'){
        method('POST');require_csrf();rate_limit('upload',100,3600);$f=$_FILES['photo']??null;
        if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>2*1024*1024)invalid('Wybierz zdjęcie JPG, PNG lub WebP do 2 MB.');
        $type=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($type,['image/jpeg','image/png','image/webp'],true))invalid('Nieprawidłowy format zdjęcia.');
        $size=getimagesize($f['tmp_name']);if(!$size||$size[0]*$size[1]>16000000)invalid('Zdjęcie ma zbyt duże wymiary.');
        $image=imagecreatefromstring(file_get_contents($f['tmp_name']));if(!$image)invalid('Nie można odczytać zdjęcia.');
        $dir=config()['private_dir'].'/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$file=id().'.webp';$written=imagewebp($image,$dir.'/'.$file,88);imagedestroy($image);if(!$written)throw new \RuntimeException('Nie udało się zapisać zdjęcia.');
        json_response(['ok'=>true,'photo'=>['src'=>'../api.php?action=image&file='.$file,'alt'=>text(pathinfo($f['name'],PATHINFO_FILENAME),500)]],201);
    }
    if($action==='delivery'){method('GET');json_response(['ok'=>true,'delivery'=>mail_health()]);}
    problem('Nie znaleziono endpointu.',404);
}catch(InputError $e){problem($e->getMessage(),$e->getCode()?:422);}
catch(\Throwable $e){error_log('[Elephant panel] '.$e->getMessage());json_response(['ok'=>false,'error'=>'Nie udało się obsłużyć żądania. Spróbuj ponownie lub skontaktuj się z pracownią.'],503);}
