<?php
declare(strict_types=1);
namespace Elephant;

final class InputError extends \RuntimeException {}
function invalid(string $message,int $code=422): never { throw new InputError($message,$code); }
function text(mixed $value,int $max=200,bool $required=false): string {
    if(!is_string($value)||!preg_match('//u',$value))invalid('Nieprawidłowy tekst.');
    $value=trim($value);if(strlen($value)>$max||($required&&$value==='')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value))invalid('Uzupełnij wymagane pola i sprawdź długość tekstu.');return $value;
}
function email(mixed $value): string { $s=text($value,254,true);if(!filter_var($s,FILTER_VALIDATE_EMAIL)||str_contains($s,"\n")||str_contains($s,"\r"))invalid('Podaj poprawny adres e-mail.');return $s; }
function integer(mixed $value,int $min,int $max): int { if(!is_int($value)||$value<$min||$value>$max)invalid('Nieprawidłowa wartość liczbowa.');return $value; }
function choice(mixed $value,array $values): string { if(!is_string($value)||!in_array($value,$values,true))invalid('Nieprawidłowy status.');return $value; }
function item_id(mixed $value): string { $s=text($value,80,true);if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$s))invalid('Nieprawidłowy identyfikator.');return $s; }
function iso_date(mixed $value): string { $s=text($value,10,true);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)invalid('Nieprawidłowa data.');return $s; }
function time_value(mixed $value): string { $s=text($value,5,true);if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$s))invalid('Nieprawidłowa godzina.');return $s; }
function list_value(mixed $value,int $max): array { if(!is_array($value)||!array_is_list($value)||count($value)>$max)invalid('Nieprawidłowa lista danych.');return $value; }
function slug(mixed $value): string {
    $s=text($value,190,true);if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$s)||in_array($s,['index','api','site','panel','admin-preview','assets','media','oferty','kontakt','projekty','artykuly','sitemap','robots','llms'],true))invalid('Wybierz unikalny adres: małe litery, cyfry i myślniki.');return $s;
}
function photo(mixed $p): array {
    if(!is_array($p))invalid('Nieprawidłowe zdjęcie.');$src=text($p['src']??'',255,true);
    $asset=preg_match('~^\.\./assets/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\.(?:webp|png|jpe?g)$~D',$src)&&is_file(ROOT.'/'.substr($src,3));
    $upload=preg_match('~^\.\./api\.php\?action=image&file=([a-f0-9]{32}\.(?:webp|png|jpg))$~D',$src,$m)&&is_file(config()['private_dir'].'/uploads/'.($m[1]??''));
    if(!$asset&&!$upload)invalid('Zdjęcie musi być przesłane przez panel.');
    return ['src'=>$src,'alt'=>text($p['alt']??'',1000)];
}
function availability_value(mixed $a): array {
    if(!is_array($a))invalid('Nieprawidłowa dostępność.');$days=list_value($a['weekly']??null,7);if(count($days)!==7)invalid('Uzupełnij siedem dni tygodnia.');
    $duration=integer($a['duration']??null,15,180);if(!in_array($duration,[30,60,90],true))invalid('Nieprawidłowy czas konsultacji.');
    $weekly=[];foreach($days as $d){if(!is_array($d)||!is_bool($d['enabled']??null))invalid('Nieprawidłowy dzień tygodnia.');$start=time_value($d['start']??'');$end=time_value($d['end']??'');if($d['enabled']&&minutes($end)-minutes($start)<$duration)invalid('Godziny pracy muszą pomieścić całą konsultację.');$weekly[]=['enabled'=>$d['enabled'],'start'=>$start,'end'=>$end];}
    return ['weekly'=>$weekly,'duration'=>$duration,'buffer'=>integer($a['buffer']??null,0,120),'notice'=>integer($a['notice']??null,0,168),'blockedDates'=>array_values(array_unique(array_map(__NAMESPACE__.'\\iso_date',list_value($a['blockedDates']??null,3650))))];
}
function validate_state(array $input,array $old): array {
    $out=['version'=>1,'projects'=>[],'articles'=>[],'inquiries'=>[],'bookings'=>[],'redirects'=>$old['redirects']??[]];$seen=[];$ids=[];
    foreach(list_value($input['projects']??null,250) as $p){
        if(!is_array($p))invalid('Nieprawidłowa realizacja.');$id=item_id($p['id']??'');$slug=slug($p['slug']??'');if(isset($ids[$id])||isset($seen[$slug]))invalid('Powtórzony adres lub identyfikator.');$ids[$id]=true;$seen[$slug]=true;
        $photos=array_map(__NAMESPACE__.'\\photo',list_value($p['photos']??null,60));if(!$photos)invalid('Realizacja musi zawierać zdjęcie.');
        $out['projects'][]=['id'=>$id,'slug'=>$slug,'title'=>text($p['title']??'',600,true),'category'=>text($p['category']??'Projekt wnętrza',200),'description'=>text($p['description']??'',20000,true),'status'=>choice($p['status']??'', ['draft','published']),'featured'=>($p['featured']??false)===true,'order'=>integer($p['order']??null,1,999),'photos'=>$photos];
        foreach($old['projects'] as $previous)if($previous['id']===$id&&$previous['slug']!==$slug)$out['redirects'][$previous['slug']]=$slug;
    }
    foreach(list_value($input['articles']??null,500) as $a){
        if(!is_array($a))invalid('Nieprawidłowy artykuł.');$id=item_id($a['id']??'');$slug=slug($a['slug']??'');if(isset($ids[$id])||isset($seen[$slug]))invalid('Powtórzony adres lub identyfikator.');$ids[$id]=true;$seen[$slug]=true;
        $out['articles'][]=['id'=>$id,'slug'=>$slug,'title'=>text($a['title']??'',1000,true),'excerpt'=>text($a['excerpt']??'',3000,true),'body'=>text($a['body']??'',150000,true),'seoTitle'=>text($a['seoTitle']??'',1000),'seoDescription'=>text($a['seoDescription']??'',3000),'status'=>choice($a['status']??'',['draft','published'])];
        foreach($old['articles'] as $previous)if($previous['id']===$id&&$previous['slug']!==$slug)$out['redirects'][$previous['slug']]=$slug;
    }
    $out['availability']=availability_value($input['availability']??null);
    $settings=$input['settings']??[];$site=text($settings['url']??'',500,true);if(!filter_var($site,FILTER_VALIDATE_URL)||!in_array(parse_url($site,PHP_URL_SCHEME),['https','http'],true))invalid('Nieprawidłowy adres strony.');
    $out['settings']=['name'=>text($settings['name']??'',300,true),'email'=>email($settings['email']??''),'phone'=>text($settings['phone']??'',80),'url'=>$site];
    $oldInquiries=array_column($old['inquiries'],'id');$inquiries=list_value($input['inquiries']??null,10000);if(count($inquiries)!==count($oldInquiries))invalid('Zapytania można archiwizować, ale nie usuwać.');$handled=[];
    foreach($inquiries as $i){$id=item_id($i['id']??'');if(!in_array($id,$oldInquiries,true)||isset($handled[$id]))invalid('Nieprawidłowe zapytanie.');$handled[$id]=true;$previous=$old['inquiries'][array_search($id,$oldInquiries,true)];$previous['status']=choice($i['status']??'',['new','read','replied','archived']);$previous['notes']=text($i['notes']??'',20000);$out['inquiries'][]=$previous;}
    $oldBookings=array_column($old['bookings'],null,'id');$handled=[];
    foreach(list_value($input['bookings']??null,10000) as $b){
        $id=item_id($b['id']??'');if(isset($handled[$id]))invalid('Powtórzone spotkanie.');$handled[$id]=true;$previous=$oldBookings[$id]??null;
        $booking=['id'=>$id,'name'=>text($b['name']??'',300,true),'email'=>email($b['email']??''),'date'=>iso_date($b['date']??''),'time'=>time_value($b['time']??''),'status'=>choice($b['status']??'',['pending','confirmed','cancelled']),'notes'=>text($b['notes']??'',20000),'duration'=>$previous['duration']??$out['availability']['duration'],'buffer'=>$previous['buffer']??$out['availability']['buffer'],'createdAt'=>$previous['createdAt']??gmdate(DATE_ATOM)];
        $start=local_datetime($booking['date'],$booking['time'])->setTimezone(new \DateTimeZone('UTC'));$booking['startsAt']=$start->format(DATE_ATOM);$booking['endsAt']=$start->modify('+'.$booking['duration'].' minutes')->format(DATE_ATOM);$out['bookings'][]=$booking;
    }
    foreach($oldBookings as $id=>$b)if(!isset($handled[$id]))invalid('Spotkania należy anulować, nie usuwać.');
    foreach($out['bookings'] as $b){$p=$oldBookings[$b['id']]??null;$changed=!$p||$p['date']!==$b['date']||$p['time']!==$b['time']||($p['status']==='cancelled'&&$b['status']!=='cancelled');if($changed&&$b['status']!=='cancelled')check_booking($b,$out,$b['id']);}
    return $out;
}
