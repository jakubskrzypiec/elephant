<?php
declare(strict_types=1);
namespace Elephant;

function zone(): \DateTimeZone { return new \DateTimeZone('Europe/Warsaw'); }
function minutes(string $time): int { [$h,$m]=array_map('intval',explode(':',$time));return $h*60+$m; }
function local_datetime(string $date,string $time): \DateTimeImmutable {
    $d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,zone());if(!$d||$d->format('Y-m-d H:i')!==$date.' '.$time)invalid('Wybrana data lub godzina nie istnieje w strefie Europe/Warsaw.');return $d;
}
function day_rule(string $date,array $state): array { $d=new \DateTimeImmutable($date.' 12:00:00',zone());return $state['availability']['weekly'][(int)$d->format('N')-1]; }
function check_booking(array $b,array $state,?string $exclude=null): void {
    $a=$state['availability'];$d=day_rule($b['date'],$state);
    if(!$d['enabled']||in_array($b['date'],$a['blockedDates'],true))invalid('Ten dzień jest niedostępny.',409);
    $start=minutes($b['time']);$duration=$b['duration']??$a['duration'];$buffer=$b['buffer']??$a['buffer'];
    if($start<minutes($d['start'])||$start+$duration>minutes($d['end']))invalid('Termin jest poza godzinami dostępności.',409);
    $when=local_datetime($b['date'],$b['time']);
    if($when->getTimestamp()<time()+$a['notice']*3600)invalid('Termin jest zbyt bliski. Wybierz późniejszy dzień.',409);
    if($when->getTimestamp()>time()+365*86400)invalid('Rezerwacje są dostępne do roku naprzód.');
    foreach($state['bookings'] as $other){
        if($other['id']===$exclude||$other['status']==='cancelled'||$other['date']!==$b['date'])continue;
        $otherStart=minutes($other['time']);$otherEnd=$otherStart+($other['duration']??$a['duration'])+($other['buffer']??$a['buffer']);
        if($start<$otherEnd&&$start+$duration+$buffer>$otherStart)invalid('Ten termin został już zajęty. Wybierz inną godzinę.',409);
    }
}
function slots(string $date,array $state): array {
    $rule=day_rule($date,$state);$a=$state['availability'];if(!$rule['enabled']||in_array($date,$a['blockedDates'],true))return [];
    $out=[];for($m=minutes($rule['start']);$m+$a['duration']<=minutes($rule['end']);$m+=15){$time=sprintf('%02d:%02d',intdiv($m,60),$m%60);try{check_booking(['date'=>$date,'time'=>$time],$state);$out[]=$time;}catch(InputError){}}
    return $out;
}
function booking_notifications(array $new,array $old): void {
    $previous=array_column($old['bookings'],null,'id');
    foreach($new['bookings'] as $b){$p=$previous[$b['id']]??null;if($p&&$p['status']===$b['status']&&$p['date']===$b['date']&&$p['time']===$b['time']&&$p['email']===$b['email'])continue;
        $details=booking_details($b);$key=$b['id'].':'.id();
        if($b['status']==='cancelled')queue_mail($b['email'],'Anulowanie konsultacji ELEPHANT',"Twoja konsultacja została anulowana.\n\n".$details,$key.':cancelled');
        elseif($b['status']==='confirmed')queue_mail($b['email'],'Potwierdzenie konsultacji ELEPHANT',"Potwierdzamy termin konsultacji. ".(!empty($b['meetingUrl'])?"Dołącz do spotkania Google Meet:\n".$b['meetingUrl']:"Szczegóły spotkania przekaże pracownia.")."\n\n".$details,$key.':confirmed');
        else queue_mail($b['email'],'Zgłoszenie konsultacji ELEPHANT',"Otrzymaliśmy Twoją rezerwację. Termin oczekuje na potwierdzenie pracowni.\n\n".$details,$key.':pending');
    }
}
function booking_details(array $b): string { return "Osoba: {$b['name']}\nData: {$b['date']}\nGodzina: {$b['time']} (Europe/Warsaw)\nCzas: ".($b['duration']??60).' minut'; }
