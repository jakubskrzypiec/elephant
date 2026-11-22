<?php
declare(strict_types=1);
namespace Elephant;

function replace_literal(string $pattern,string $replacement,string $html,int $limit=-1): string { return preg_replace_callback($pattern,fn()=>$replacement,$html,$limit); }
function element_span(string $html,string $tag,string $marker): ?array {
    preg_match_all('~</?'.preg_quote($tag,'~').'\b[^>]*>~i',$html,$matches,PREG_OFFSET_CAPTURE);$start=null;$depth=0;$openEnd=0;
    foreach($matches[0] as [$token,$offset]){
        if($start===null){if(!str_starts_with($token,'</')&&str_contains($token,$marker)){$start=$offset;$openEnd=$offset+strlen($token);$depth=1;}continue;}
        $depth+=str_starts_with($token,'</')?-1:1;if($depth===0)return ['start'=>$start,'openEnd'=>$openEnd,'closeStart'=>$offset,'end'=>$offset+strlen($token)];
    }return null;
}
function replace_element(string $html,string $tag,string $marker,string $replacement,bool $inner=false): string {
    $span=element_span($html,$tag,$marker);if(!$span)throw new \RuntimeException('Template marker missing: '.$marker);
    $start=$inner?$span['openEnd']:$span['start'];$end=$inner?$span['closeStart']:$span['end'];return substr_replace($html,$replacement,$start,$end-$start);
}
function image_url(string $src): string { return base(preg_replace('~^\.\./~','',$src)); }
function image_dimensions(string $src): string {
    $file=ROOT.'/'.preg_replace('~^\.\./~','',$src);
    if(preg_match('~^\.\./api\.php\?action=image&file=([a-f0-9]{32}\.webp)$~D',$src,$m))$file=config()['private_dir'].'/uploads/'.$m[1];
    $size=@getimagesize($file);return $size?'width="'.$size[0].'" height="'.$size[1].'"':'width="1600" height="1600"';
}
function project_link(array $p): string { return base($p['slug'].'.html'); }
function public_projects(array $state): array { $items=array_values(array_filter($state['projects'],fn($p)=>$p['status']==='published'));usort($items,fn($a,$b)=>$a['order']<=>$b['order']);return $items; }
function public_articles(array $state): array { return array_values(array_filter($state['articles'],fn($p)=>$p['status']==='published')); }
function project_card(array $p,string $overlay): string {
    $photo=$p['photos'][0];return '<article class="project-card"><a href="'.html(project_link($p)).'"><div class="project-card-media"><img class="project-card-photo" src="'.html(image_url($photo['src'])).'" alt="'.html($photo['alt']?:$p['title']).'" '.image_dimensions($photo['src']).' loading="lazy">'.$overlay.'</div><div class="project-meta"><h3>'.html($p['title']).'</h3><p>'.html($p['category']??'Projekt wnętrza').'</p></div></a></article>';
}
function portfolio_card(array $p): string {
    $photo=$p['photos'][0];return '<article class="portfolio-item portfolio-item-wide portfolio-realization" data-category="'.html($p['category']??'Projekt wnętrza').'"><a aria-label="Zobacz projekt: '.html($p['title']).'" href="'.html(project_link($p)).'"><div class="portfolio-image"><img src="'.html(image_url($photo['src'])).'" alt="'.html($photo['alt']?:$p['title']).'" '.image_dimensions($photo['src']).' loading="lazy"></div><div class="portfolio-meta"><h2>'.html($p['title']).'</h2><p class="portfolio-description">'.html($p['description']).'</p><span class="portfolio-room">'.html($p['category']??'Projekt wnętrza').'</span></div></a></article>';
}
function gallery(array $p): string {
    $html='';foreach($p['photos'] as $i=>$photo){$src=html(image_url($photo['src']));$caption=html($photo['alt']?:$p['title']);$html.='<button type="button" class="project-photo" data-photo="'.$src.'" data-caption="'.$caption.'" aria-label="Powiększ zdjęcie: '.$caption.'"><img src="'.$src.'" alt="'.$caption.'" '.image_dimensions($photo['src']).' loading="'.($i?'lazy':'eager').'"><span class="project-photo-symbol" aria-hidden="true"><svg class="line-arrow" viewBox="0 0 24 16" focusable="false" aria-hidden="true"><path d="M7 13 17 3m-7 0h7v7"/></svg></span></button>'; }return $html;
}
function article_content(string $body): array {
    $html='';$toc='';$i=0;$inSection=false;
    foreach(preg_split('/\R\s*\R/u',trim($body)) as $part){$part=trim($part);if(str_starts_with($part,'## ')){if($inSection)$html.='</section>';$i++;$title=trim(substr($part,3));$html.='<section id="czytaj-'.$i.'"><h2>'.html($title).'</h2>';$toc.='<a href="#czytaj-'.$i.'"><small>'.sprintf('%02d',$i).'</small>'.html($title).'</a>';$inSection=true;}else{$html.='<p>'.nl2br(html($part),false).'</p>';}}
    if($inSection)$html.='</section>';return [$html,$toc];
}
function detail_head(string $html,string $title,string $description,string $canonical,?string $image,string $type,array $state): string {
    $pageTitle=str_contains($title,'ELEPHANT')?$title:$title.' | ELEPHANT Interior Design';
    $html=replace_literal('~<title>.*?</title>~s','<title>'.html($pageTitle).'</title>',$html,1);
    $values=['name="description"'=>$description,'property="og:title"'=>$title,'property="og:description"'=>$description,'property="og:url"'=>$canonical,'name="twitter:title"'=>$title,'name="twitter:description"'=>$description];if($image)$values['property="og:image"']=$image;
    if($image)$values['name="twitter:image"']=$image;
    foreach($values as $marker=>$value)$html=preg_replace_callback('~<meta\b[^>]*'.preg_quote($marker,'~').'[^>]*>~',fn($m)=>replace_literal('~content="[^"]*"~','content="'.html($value).'"',$m[0]),$html);
    $html=replace_literal('~<link rel="canonical" href="[^"]*">~','<link rel="canonical" href="'.html($canonical).'">',$html);
    $org=['@type'=>'Organization','@id'=>url().'#organization','name'=>$state['settings']['name'],'url'=>url(),'email'=>$state['settings']['email'],'telephone'=>$state['settings']['phone']];
    $page=['@type'=>$type,'url'=>$canonical,'name'=>$title,'description'=>$description,'inLanguage'=>'pl-PL','publisher'=>['@id'=>url().'#organization']];if($type==='Article'){$page['headline']=$title;$page['author']=['@id'=>url().'#organization'];}if($image)$page['image']=$image;
    $json=json_encode(['@context'=>'https://schema.org','@graph'=>[$org,$page]],JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return replace_literal('~<script type="application/ld\+json">.*?</script>~s','<script type="application/ld+json">'.$json.'</script>',$html);
}
function public_calendar(): string {
    return '<section class="consultation-calendar native-booking" id="kalendarz-konsultacji" data-native-booking aria-labelledby="calendar-title"><div class="calendar-intro"><span class="eyebrow">Konsultacja online</span><h2 id="calendar-title">Wybierz preferowany termin</h2><p>Wybierz dostępny dzień i godzinę. Po zapisaniu rezerwacji pracownia potwierdzi termin spotkania e-mailem.</p></div><div class="calendar-layout"><div class="calendar-month"><div class="calendar-toolbar"><button type="button" data-native-prev aria-label="Poprzedni miesiąc">←</button><h3 data-native-month aria-live="polite"></h3><button type="button" data-native-next aria-label="Następny miesiąc">→</button></div><div class="calendar-weekdays" aria-hidden="true"><span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>So</span><span>Nd</span></div><div class="calendar-days" data-native-days></div></div><div class="calendar-preference"><p data-native-selection aria-live="polite">Wybierz dzień w kalendarzu.</p><div class="native-slots" data-native-slots></div><form data-native-form hidden><input type="text" name="_honey" hidden tabindex="-1" autocomplete="off"><input name="date" type="hidden"><input name="time" type="hidden"><label class="field"><small>Imię i nazwisko</small><input name="name" autocomplete="name" required maxlength="200"></label><label class="field"><small>E-mail</small><input name="email" type="email" autocomplete="email" required maxlength="254"></label><label class="field"><small>Telefon</small><input name="phone" type="tel" autocomplete="tel" maxlength="50"></label><label class="consent"><input name="consent" type="checkbox" required><span>Wyrażam zgodę na kontakt w sprawie konsultacji.</span></label><button class="calendar-apply" type="submit">Zarezerwuj termin</button></form><p data-native-status role="status" aria-live="polite"></p></div></div></section>';
}
function inject_runtime(string $html,string $path): string {
    if(in_array($path,['oferty.html','kontakt.html'],true))$html=replace_element($html,'section','class="consultation-calendar',public_calendar());
    $html=preg_replace_callback('~<form\b[^>]*data-contact-form[^>]*>~',function($m){$tag=preg_replace('~\saction="[^"]*"~','',$m[0]);return substr($tag,0,-1).' action="'.html(base('api.php?action=contact')).'">';},$html);
    $html=preg_replace('~<input type="hidden" name="_(?:subject|template)"[^>]*>~','',$html);
    $html=preg_replace('~<p class="form-note">.*?</p>~s','<p class="form-note" role="status">Po wysłaniu zapytanie trafi bezpośrednio do pracowni.</p>',$html);
    if(in_array($path,['oferty.html','kontakt.html','index.html'],true))$html=str_replace('</body>','<script src="'.html(base('panel-backend/public/runtime.js?v=1')).'" data-elephant-api="'.html(base('api.php')).'"></script></body>',$html);
    $html=str_replace('</head>','<link rel="stylesheet" href="'.html(base('panel-backend/public/runtime.css?v=2')).'"></head>',$html);
    return $html;
}
function public_metadata(string $html,string $path,array $state): string {
    $settings=$state['settings'];
    $html=preg_replace_callback('~<a\b[^>]*href="mailto:elephant\.kontakt@gmail\.com"[^>]*>.*?</a>~s',fn($m)=>str_replace('elephant.kontakt@gmail.com',html($settings['email']),$m[0]),$html);
    $phone=preg_replace('/[^+0-9]/','',$settings['phone']);
    $html=preg_replace_callback('~<a\b[^>]*href="tel:\+48573720580"[^>]*>.*?</a>~s',fn($m)=>str_replace(['tel:+48573720580','+48 573 720 580'],['tel:'.html($phone),html($settings['phone'])],$m[0]),$html);
    return preg_replace_callback('~<script type="application/ld\+json">(.*?)</script>~s',function($m)use($path,$state,$settings){
        $data=json_decode($m[1],true,512,JSON_THROW_ON_ERROR);
        foreach($data['@graph'] as &$item){
            if(($item['@type']??'')==='Organization'){$item['name']=$settings['name'];$item['email']=$settings['email'];$item['telephone']=$settings['phone'];}
            if(($item['@type']??'')==='Article')foreach(public_articles($state) as $a)if($a['slug'].'.html'===$path)$item['headline']=$a['title'];
            if(($item['@type']??'')==='ItemList'&&in_array($path,['projekty.html','artykuly.html'],true)){
                $items=$path==='projekty.html'?public_projects($state):public_articles($state);$item['itemListElement']=[];
                foreach($items as $i=>$entry)$item['itemListElement'][]=['@type'=>'ListItem','position'=>$i+1,'name'=>$entry['title'],'url'=>url($entry['slug'].'.html')];
            }
        }unset($item);
        return '<script type="application/ld+json">'.json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    },$html);
}
function serve_public(string $path): void {
    if(!preg_match('~^(?:[a-z0-9-]+\.html|sitemap\.xml|llms\.txt)$~D',$path)){http_response_code(404);echo 'Nie znaleziono strony.';return;}
    $state=state_row()['state'];$projects=public_projects($state);$articles=public_articles($state);
    if($path==='sitemap.xml'){
        header('Content-Type: application/xml; charset=utf-8');$paths=['','oferty.html','kontakt.html','projekty.html','artykuly.html'];foreach(array_merge($projects,$articles) as $item)$paths[]=$item['slug'].'.html';echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($paths as $p)echo '<url><loc>'.html(url($p)).'</loc></url>';echo '</urlset>';return;
    }
    if($path==='llms.txt'){header('Content-Type: text/plain; charset=utf-8');echo '# '.$state['settings']['name']."\n\nProjekty wnętrz: Wrocław, Kraków, Poznań, Warszawa i online w całej Polsce.\n\nKontakt: ".$state['settings']['email']."\n\n";foreach(array_merge($projects,$articles) as $item)echo '- ['.$item['title'].']('.url($item['slug'].'.html').")\n";return;}
    $slug=substr($path,0,-5);$project=null;$article=null;foreach($projects as $p)if($p['slug']===$slug)$project=$p;foreach($articles as $a)if($a['slug']===$slug)$article=$a;
    $standard=in_array($path,['index.html','oferty.html','kontakt.html','projekty.html','artykuly.html'],true);
    if(!$standard&&!$project&&!$article){
        $target=$slug;$visited=[];for($i=0;$i<20&&isset($state['redirects'][$target])&&!isset($visited[$target]);$i++){$visited[$target]=true;$target=$state['redirects'][$target];}
        if($target!==$slug&&in_array($target,array_column(array_merge($projects,$articles),'slug'),true)){header('Location: '.base($target.'.html'),true,301);return;}
        http_response_code(404);header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="pl"><meta charset="utf-8"><title>Nie znaleziono strony</title><h1>Nie znaleziono strony</h1><a href="'.html(base()).'">Wróć do ELEPHANT</a></html>';return;
    }
    $templates=['projekt-lazienka.html','projekt-sypialnia.html','projekt-kuchnia.html','projekt-salon.html','projekt-wc.html','artykul-maly-metraz.html','artykul-materialy.html','artykul-polski-design.html','artykul-projektowanie-online.html','artykul-swiatlo.html','artykul-autentyczne-wnetrze.html'];
    $source=$standard||in_array($path,$templates,true)?$path:($project?'projekt-kuchnia.html':'artykul-swiatlo.html');$html=file_get_contents(ROOT.'/'.$source);
    if($path==='index.html'){
        $span=element_span($html,'span','class="project-card-overlay"');$overlay=$span?substr($html,$span['start'],$span['end']-$span['start']):'';$items=array_filter($projects,fn($p)=>$p['featured']);$cards='';foreach($items as $p)$cards.=project_card($p,$overlay);$html=replace_element($html,'div','data-track',$cards,true);
    }
    if($path==='projekty.html'){
        $cards='';$links='';foreach($projects as $p){$cards.=portfolio_card($p);$links.='<a href="'.html(project_link($p)).'">'.html($p['title']).'</a>';}$html=replace_element($html,'div','class="portfolio-grid"',$cards,true);
        $html=preg_replace_callback('~(<span class="collection-count"[^>]*>).*?(</span>)~s',fn($m)=>$m[1].count($projects).' projektów'.$m[2],$html);
        $span=element_span($html,'dialog','class="project-list-overlay"');if($span){$dialog=substr($html,$span['start'],$span['end']-$span['start']);$dialog=preg_replace('~<a\b[^>]*href="projekt-[^>]*>.*?</a>~s','',$dialog);$dialog=str_replace('</dialog>',$links.'</dialog>',$dialog);$html=substr_replace($html,$dialog,$span['start'],$span['end']-$span['start']);}
    }
    if($project){
        $html=replace_literal('~<h1\b[^>]*>.*?</h1>~s','<h1>'.html($project['title']).'</h1>',$html,1);
        // The description sits immediately before the image gallery in current project templates.
        $html=replace_literal('~<p class="project-summary">.*?</p>~s','<p class="project-summary">'.html($project['description']).'</p>',$html);
        $gallerySpan=element_span($html,'div','class="project-photo-grid');if(!$gallerySpan)throw new \RuntimeException('Missing gallery.');
        $prefix=substr($html,0,$gallerySpan['start']);$position=strrpos($prefix,'<p');$end=$position!==false?strpos($prefix,'</p>',$position):false;
        if($position!==false&&$end!==false&&trim(substr($prefix,$end+4))==='')$html=substr_replace($html,'<p class="project-description">'.html($project['description']).'</p>'."\n",$position,$gallerySpan['start']-$position);
        $html=replace_element($html,'div','class="project-photo-grid',gallery($project),true);
        $index=array_search($project['id'],array_column($projects,'id'),true);$next=$projects[($index+1)%max(1,count($projects))]??null;
        if($next)$html=replace_element($html,'nav','class="project-next"','<nav class="project-next" aria-label="Kolejny projekt"><span class="eyebrow">Kolejny projekt</span><a href="'.html(project_link($next)).'">'.html($next['title']).' <svg class="line-arrow" viewBox="0 0 24 16" focusable="false" aria-hidden="true"><path d="M7 13 17 3m-7 0h7v7"/></svg></a></nav>');
        $html=detail_head($html,$project['title'],$project['description'],url($path),url(ltrim(preg_replace('~^\.\./~','',$project['photos'][0]['src']),'/')),'WebPage',$state);
    }
    if($path==='artykuly.html'){
        $cards='';foreach($articles as $a)$cards.='<article class="journal-card"><a href="'.html(base($a['slug'].'.html')).'"><span class="eyebrow">Poradnik</span><h2>'.html($a['title']).'</h2><p>'.html($a['excerpt']).'</p><span class="text-link">Czytaj dalej</span></a></article>';
        $html=replace_element($html,'div','class="journal-grid"',$cards,true);
    }
    if($article){
        $html=replace_literal('~<h1 class="article-reading-title">.*?</h1>~s','<h1 class="article-reading-title">'.html($article['title']).'</h1>',$html);
        $html=replace_literal('~<p class="article-lead">.*?</p>~s','<p class="article-lead">'.html($article['excerpt']).'</p>',$html);
        $html=preg_replace('~<p class="banner-topic">.*?</p>~s','<p class="banner-topic">Poradnik</p>',$html);
        [$body,$toc]=article_content($article['body']);$html=replace_element($html,'div','class="article-body"',$body,true);
        $html=replace_element($html,'aside','class="article-toc"',$toc?'<aside class="article-toc"><span class="eyebrow">W tym tekście</span><nav aria-label="Spis treści">'.$toc.'</nav></aside>':'');
        if(!$toc)$html=str_replace('<div class="reading-layout">','<div class="reading-layout" style="display:block;max-width:780px;margin:auto">',$html);
        $html=detail_head($html,$article['seoTitle']?:$article['title'],$article['seoDescription']?:$article['excerpt'],url($path),null,'Article',$state);
    }
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-cache');echo inject_runtime(public_metadata($html,$path,$state),$path);
}
