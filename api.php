<?php
declare(strict_types=1);
require __DIR__.'/auth.php';
translate_start_session();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$config = translate_config();

function cacheTtl(): int { $s=translate_settings(); return max(1,min(30,(int)($s['cache_days']??7)))*86400; }
function cacheDir(): string {
    $dir=__DIR__.'/storage/cache';
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    return $dir;
}
function cleanupCache(): void {
    $dir=cacheDir(); $now=time();
    foreach(glob($dir.'/*.json') ?: [] as $file){ if(is_file($file) && $now-(int)@filemtime($file)>cacheTtl()) @unlink($file); }
}
function cacheKey(string $text,string $from,string $to,string $provider): string { return hash('sha256',$provider."\0".$from."\0".$to."\0".$text); }
function cacheGet(string $key): ?array {
    $file=cacheDir().'/'.$key.'.json';
    if(!is_file($file) || time()-(int)@filemtime($file)>cacheTtl()){ if(is_file($file)) @unlink($file); return null; }
    $j=json_decode((string)@file_get_contents($file),true); return is_array($j)?$j:null;
}
function cachePut(string $key,array $data): void { @file_put_contents(cacheDir().'/'.$key.'.json',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
cleanupCache();

function out(array $data, int $code=200): never { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if (!translate_logged_in()) out(['ok'=>false,'error'=>'Authentication required'],401);
function body(): array { $raw=file_get_contents('php://input'); $d=json_decode($raw ?: '{}', true); return is_array($d)?$d:[]; }
function httpReq(string $url, ?string $post=null, array $headers=[]): array {
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_USERAGENT=>'Mozilla/5.0 TranslateApp/1.0'];
        if ($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$post;}
        if($headers)$opts[CURLOPT_HTTPHEADER]=$headers;
        curl_setopt_array($ch,$opts); $data=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
        return [$code,$data===false?'':$data,$err];
    }
    $headerLines=array_merge(['User-Agent: Mozilla/5.0 TranslateApp/1.0'],$headers);
    $context=stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>implode("\r\n",$headerLines),'content'=>$post??'','timeout'=>6,'ignore_errors'=>true]]);
    $data=@file_get_contents($url,false,$context); $code=0;
    foreach(($http_response_header??[]) as $line){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$line,$m)){$code=(int)$m[1];break;}}
    return [$code,$data===false?'':$data,$data===false?'HTTP request failed':''];
}
function httpReqLong(string $url, ?string $post=null, array $headers=[]): array {
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>28,CURLOPT_USERAGENT=>'Mozilla/5.0 TranslateApp/1.0'];
        if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$post;}
        if($headers)$opts[CURLOPT_HTTPHEADER]=$headers;
        curl_setopt_array($ch,$opts); $data=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
        return [$code,$data===false?'':$data,$err];
    }
    $headerLines=array_merge(['User-Agent: Mozilla/5.0 TranslateApp/1.0'],$headers);
    $context=stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>implode("\r\n",$headerLines),'content'=>$post??'','timeout'=>28,'ignore_errors'=>true]]);
    $data=@file_get_contents($url,false,$context); $code=0;
    foreach(($http_response_header??[]) as $line){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$line,$m)){$code=(int)$m[1];break;}}
    return [$code,$data===false?'':$data,$data===false?'HTTP request failed':''];
}
function lang(string $s): string { return preg_replace('/[^A-Za-z0-9_-]/','',substr($s,0,20)) ?: 'auto'; }
function cleanText(string $s): string { return trim(preg_replace('/\R/u', "\n", $s) ?? $s); }
function ulen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : strlen($s); }
function usub(string $s,int $start,?int $len=null): string { if(function_exists('mb_substr')) return $len===null ? mb_substr($s,$start,null,'UTF-8') : mb_substr($s,$start,$len,'UTF-8'); return $len===null ? substr($s,$start) : substr($s,$start,$len); }
function urpos(string $s,string $needle): int|false { return function_exists('mb_strrpos') ? mb_strrpos($s,$needle,0,'UTF-8') : strrpos($s,$needle); }
function parseGoogleArray(string $raw): ?string {
    $j=json_decode($raw,true); if(!is_array($j)||!isset($j[0])||!is_array($j[0]))return null;
    $out=''; foreach($j[0] as $row){ if(is_array($row)&&isset($row[0])&&is_string($row[0]))$out.=$row[0]; }
    return trim($out)!==''?$out:null;
}
function googleTranslateMobile(string $text,string $from,string $to): ?string {
    $url='https://translate.google.com/m?sl='.rawurlencode($from ?: 'auto').'&tl='.rawurlencode($to).'&q='.rawurlencode($text);
    [$code,$raw]=httpReq($url); if($code<200||$code>=400||$raw==='')return null;
    if(preg_match('~<div[^>]+class=["\']result-container["\'][^>]*>(.*?)</div>~is',$raw,$m)){
        $v=html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $v=trim(preg_replace('/\s+/u',' ',$v)??$v); return $v!==''?$v:null;
    }
    return null;
}
function googleTranslate(string $text,string $from,string $to): ?string {
    $sl=$from ?: 'auto';
    $hosts=['translate.googleapis.com','translate.google.com'];
    foreach($hosts as $host){
        $url='https://'.$host.'/translate_a/single?client=gtx&dt=t&sl='.rawurlencode($sl).'&tl='.rawurlencode($to).'&q='.rawurlencode($text);
        [$code,$raw]=httpReq($url); if($code>=200&&$code<400&&$raw!==''){ $v=parseGoogleArray($raw); if($v!==null)return $v; }
    }
    return googleTranslateMobile($text,$sl,$to);
}
function myMemory(string $text,string $from,string $to): ?string {
    $src=($from==='auto'||$from==='')?'en':$from;
    $url='https://api.mymemory.translated.net/get?q='.rawurlencode($text).'&langpair='.rawurlencode($src.'|'.$to);
    [$code,$raw]=httpReq($url); if($code<200||$code>=300)return null; $j=json_decode($raw,true); $v=$j['responseData']['translatedText']??null; if(!is_string($v)||$v==='')return null; if(preg_match('/QUERY LENGTH LIMIT EXCEEDED|MAX ALLOWED QUERY/i',$v))return null; return $v;
}
function libre(string $text,string $from,string $to,array $cfg): ?string {
    $url=(string)($cfg['libretranslate_url']??''); if($url==='')return null;
    $payload=json_encode(['q'=>$text,'source'=>$from==='auto'?'auto':$from,'target'=>$to,'format'=>'text','api_key'=>(string)($cfg['libretranslate_key']??'')]);
    [$code,$raw]=httpReq($url,$payload,['Content-Type: application/json']); if($code<200||$code>=300)return null; $j=json_decode($raw,true); $v=$j['translatedText']??null; return is_string($v)?$v:null;
}
function microsoft(string $text,string $from,string $to,array $cfg): ?string {
    $key=(string)($cfg['microsoft_key']??''); if($key==='')return null;
    $query='api-version=3.0&to='.rawurlencode($to); if($from!=='auto'&&$from!=='')$query.='&from='.rawurlencode($from);
    $url='https://api.cognitive.microsofttranslator.com/translate?'.$query;
    $headers=['Content-Type: application/json','Ocp-Apim-Subscription-Key: '.$key]; if(!empty($cfg['microsoft_region']))$headers[]='Ocp-Apim-Subscription-Region: '.$cfg['microsoft_region'];
    [$code,$raw]=httpReq($url,json_encode([['Text'=>$text]],JSON_UNESCAPED_UNICODE),$headers); if($code<200||$code>=300)return null; $j=json_decode($raw,true); $v=$j[0]['translations'][0]['text']??null; return is_string($v)?$v:null;
}

function geminiTranslate(string $text,string $from,string $to): ?string {
    $s=translate_settings();
    if(empty($s['free_ai_enabled'])) return null;
    $key=trim((string)($s['gemini_api_key']??'')); if($key==='') return null;
    $model=preg_replace('/[^A-Za-z0-9._-]/','',(string)($s['gemini_model']??'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
    $source=$from==='auto'?'auto-detected language':$from;
    $prompt="Translate the following text faithfully from {$source} to {$to}. Preserve paragraph breaks, punctuation, names, URLs, numbers, and meaning. Do not explain, summarize, or add notes. Return only the translation.\n\nTEXT:\n".$text;
    $payload=json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.15]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.rawurlencode($key);
    [$code,$raw]=httpReq($url,$payload,['Content-Type: application/json']);
    if($code<200||$code>=300||$raw==='') return null;
    $j=json_decode($raw,true); $parts=$j['candidates'][0]['content']['parts']??null; if(!is_array($parts))return null;
    $out=''; foreach($parts as $part){ if(is_array($part)&&isset($part['text']))$out.=(string)$part['text']; }
    $out=trim($out); return $out!==''?$out:null;
}

function geminiAudioTranscribe(string $audioB64,string $mime,string $language): ?string {
    $s=translate_settings();
    if(empty($s['free_ai_enabled'])) return null;
    $key=trim((string)($s['gemini_api_key']??'')); if($key==='') return null;
    if(strlen($audioB64)>1800000) return null;
    $bin=base64_decode($audioB64,true); if($bin===false||strlen($bin)<1000) return null;
    $mime=preg_match('~^audio/[A-Za-z0-9.+-]+$~',$mime)?$mime:'audio/wav';
    $langName=$language==='auto'?'the dominant language':$language;
    $prompt="Transcribe the intelligible spoken or sung words in this short media audio segment in {$langName}. Be literal and preserve word order. Ignore music-only, applause, laughter, and other non-speech sounds. Do not summarize, translate, explain, or invent missing words. Return only the transcript text. If no intelligible words are present, return an empty string.";
    $payload=json_encode(['contents'=>[['parts'=>[['text'=>$prompt],['inlineData'=>['mimeType'=>$mime,'data'=>$audioB64]]]]],'generationConfig'=>['temperature'=>0.0,'maxOutputTokens'=>512]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $model='gemini-2.5-flash';
    $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.rawurlencode($key);
    [$code,$raw]=httpReqLong($url,$payload,['Content-Type: application/json']);
    if($code<200||$code>=300||$raw==='') return null;
    $j=json_decode($raw,true); $parts=$j['candidates'][0]['content']['parts']??null; if(!is_array($parts))return null;
    $out=''; foreach($parts as $part){if(is_array($part)&&isset($part['text']))$out.=(string)$part['text'];}
    $out=trim($out);
    if(preg_match('/^(?:\[?(?:music|applause|laughter|instrumental)\]?\s*)+$/i',$out))return null;
    return $out!==''?$out:null;
}

function youtubeVideoId(string $raw): string {
    $raw=trim($raw);
    if(preg_match('~^[A-Za-z0-9_-]{11}$~',$raw)) return $raw;
    $parts=@parse_url($raw); if(!is_array($parts)) return '';
    $host=strtolower((string)($parts['host']??'')); $path=(string)($parts['path']??'');
    if(str_contains($host,'youtu.be')) return preg_match('~^/([A-Za-z0-9_-]{11})~',$path,$m)?$m[1]:'';
    parse_str((string)($parts['query']??''),$q); if(!empty($q['v'])&&preg_match('~^[A-Za-z0-9_-]{11}$~',(string)$q['v'])) return (string)$q['v'];
    if(preg_match('~/(?:shorts|embed)/([A-Za-z0-9_-]{11})~',$path,$m)) return $m[1];
    return '';
}
function extractJsonArrayAfter(string $html,string $needle): ?array {
    $pos=strpos($html,$needle); if($pos===false)return null; $start=strpos($html,'[',$pos); if($start===false)return null;
    $depth=0;$str=false;$esc=false;$len=strlen($html);
    for($i=$start;$i<$len;$i++){
        $c=$html[$i];
        if($str){ if($esc){$esc=false;continue;} if($c==='\\'){$esc=true;continue;} if($c==='"')$str=false; continue; }
        if($c==='"'){$str=true;continue;} if($c==='[')$depth++; elseif($c===']'){ $depth--; if($depth===0){$raw=substr($html,$start,$i-$start+1);$j=json_decode($raw,true);return is_array($j)?$j:null;} }
    }
    return null;
}
function youtubeTimedtextTracks(string $videoId): array {
    $url='https://www.youtube.com/api/timedtext?type=list&v='.rawurlencode($videoId);
    [$code,$xml]=httpReq($url); if($code<200||$code>=400||$xml==='')return [];
    $out=[]; if(preg_match_all('~<track\b([^>]*)/>~i',$xml,$mm)){
        foreach($mm[1] as $i=>$attrs){$get=function(string $n)use($attrs){return preg_match('~\b'.preg_quote($n,'~').'="([^"]*)"~i',$attrs,$m)?html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'):'';};$lc=$get('lang_code');if($lc==='')continue;$kind=$get('kind');$name=$get('name');$display=$get('lang_translated')?:($get('lang_original')?:$lc);$out[]=['index'=>1000+$i,'language'=>$lc,'name'=>$display.($name!==''?' — '.$name:''),'kind'=>$kind,'auto'=>$kind==='asr','translatable'=>true,'timedtext'=>true,'track_name'=>$name];}
    } return $out;
}
function parseTimedtextXml(string $raw): array {
    $segments=[]; if(preg_match_all('~<text\b([^>]*)>(.*?)</text>~is',$raw,$mm,PREG_SET_ORDER))foreach($mm as $row){$attrs=$row[1];$get=function(string $n)use($attrs){return preg_match('~\b'.preg_quote($n,'~').'="([^"]*)"~i',$attrs,$m)?$m[1]:'';};$txt=captionClean(strip_tags(html_entity_decode($row[2],ENT_QUOTES|ENT_HTML5,'UTF-8')));if($txt==='')continue;$segments[]=['start'=>(int)round(((float)$get('start'))*1000),'dur'=>(int)round(((float)$get('dur'))*1000),'text'=>$txt];} return $segments;
}
function youtubeCaptionTracks(string $videoId): array {
    if(!preg_match('~^[A-Za-z0-9_-]{11}$~',$videoId))return [];
    $out=[];
    $watch='https://www.youtube.com/watch?v='.rawurlencode($videoId).'&hl=en';
    [$code,$html]=httpReq($watch);
    if($code>=200&&$code<400&&$html!==''){
        $tracks=extractJsonArrayAfter($html,'"captionTracks"'); if(!$tracks)$tracks=extractJsonArrayAfter($html,'captionTracks');
        if(is_array($tracks)) foreach($tracks as $i=>$t){
            if(!is_array($t)||empty($t['baseUrl']))continue;
            $name=$t['name']['simpleText']??($t['name']['runs'][0]['text']??'');
            $lc=(string)($t['languageCode']??''); $kind=(string)($t['kind']??'');
            $out[]=['index'=>(int)$i,'language'=>$lc,'name'=>(string)$name,'kind'=>$kind,'auto'=>$kind==='asr','translatable'=>!empty($t['isTranslatable']),'source'=>'player'];
        }
    }
    // Always try YouTube timedtext list as a second independent source. This fixes cases where
    // the watch page hides captionTracks behind consent/experiments but captions still exist.
    $fallback=youtubeTimedtextTracks($videoId);
    foreach($fallback as $ft){
        $dup=false;foreach($out as $ot){if(($ot['language']??'')===($ft['language']??'')&&($ot['auto']??false)===($ft['auto']??false)&&($ot['name']??'')===($ft['name']??'')){$dup=true;break;}}
        if(!$dup)$out[]=$ft;
    }
    // Manual captions first, then auto-generated, and prefer English near the top.
    usort($out,function($a,$b){$aa=!empty($a['auto'])?1:0;$ba=!empty($b['auto'])?1:0;if($aa!==$ba)return $aa<=>$ba;$ae=str_starts_with(strtolower((string)($a['language']??'')),'en')?0:1;$be=str_starts_with(strtolower((string)($b['language']??'')),'en')?0:1;if($ae!==$be)return $ae<=>$be;return strcmp((string)($a['name']??''),(string)($b['name']??''));});
    return array_values($out);
}
function captionClean(string $txt): string {
    $txt=html_entity_decode($txt,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $txt=preg_replace('~\[(?:music|applause|laughter|cheering|inaudible|silence|instrumental|foreign language|speaking .*?)\]~iu',' ',$txt)??$txt;
    return trim(preg_replace('/\s+/u',' ',$txt)??$txt);
}
function captionUseful(array $segments): bool {
    $words=0;$lines=0;
    foreach($segments as $s){$t=(string)($s['text']??'');if($t==='')continue;$lines++;preg_match_all('/[\p{L}\p{N}]{2,}/u',$t,$m);$words+=count($m[0]);}
    return $lines>=2 && $words>=6;
}
function youtubeCaptions(string $videoId,string $wanted='en',?int $trackIndex=null): ?array {
    if(!preg_match('~^[A-Za-z0-9_-]{11}$~',$videoId))return null;
    $ck='ytcaps-'.hash('sha256',$videoId.'|'.$wanted.'|'.($trackIndex===null?'auto':$trackIndex)); $cached=cacheGet($ck); if($cached&&isset($cached['segments']))return $cached;

    // A timedtext-list track (1000+) can be loaded without depending on the watch-page player JSON.
    if($trackIndex!==null && $trackIndex>=1000){
        $list=youtubeTimedtextTracks($videoId);$pick=$list[$trackIndex-1000]??null;
        if(is_array($pick)){
            $url='https://www.youtube.com/api/timedtext?v='.rawurlencode($videoId).'&lang='.rawurlencode((string)$pick['language']);
            if(!empty($pick['track_name']))$url.='&name='.rawurlencode((string)$pick['track_name']);
            if(!empty($pick['auto']))$url.='&kind=asr';
            // XML is more broadly available than json3 on this endpoint.
            [$xc,$xr]=httpReq($url);
            if($xc>=200&&$xc<400&&$xr!==''){
                $segments=parseTimedtextXml($xr);
                if($segments&&captionUseful($segments)){
                    $out=['segments'=>$segments,'language'=>(string)$pick['language'],'name'=>(string)$pick['name'],'kind'=>(string)$pick['kind'],'track_index'=>$trackIndex,'quality'=>'usable'];
                    cachePut($ck,$out);return $out;
                }
            }
        }
        return null;
    }

    $watch='https://www.youtube.com/watch?v='.rawurlencode($videoId).'&hl=en';
    [$code,$html]=httpReq($watch);
    $tracks=($code>=200&&$code<400&&$html!=='')?extractJsonArrayAfter($html,'"captionTracks"'):null;
    if(!$tracks&&$html!=='')$tracks=extractJsonArrayAfter($html,'captionTracks');

    if(!$tracks){
        // Use the independent timedtext list first, preserving manual/auto distinction.
        $list=youtubeTimedtextTracks($videoId);
        $wantedLower=strtolower($wanted==='auto'?'en':$wanted);
        if($list){
            $pick=null;
            foreach($list as $t){$lc=strtolower((string)($t['language']??''));if(($lc===$wantedLower||str_starts_with($lc,$wantedLower.'-'))&&empty($t['auto'])){$pick=$t;break;}}
            if(!$pick)foreach($list as $t){$lc=strtolower((string)($t['language']??''));if($lc===$wantedLower||str_starts_with($lc,$wantedLower.'-')){$pick=$t;break;}}
            if(!$pick)$pick=$list[0]??null;
            if($pick){
                $url='https://www.youtube.com/api/timedtext?v='.rawurlencode($videoId).'&lang='.rawurlencode((string)$pick['language']);
                if(!empty($pick['track_name']))$url.='&name='.rawurlencode((string)$pick['track_name']);
                if(!empty($pick['auto']))$url.='&kind=asr';
                [$xc,$xr]=httpReq($url);if($xc>=200&&$xc<400&&$xr!==''){
                    $segments=parseTimedtextXml($xr);
                    if($segments&&captionUseful($segments)){$out=['segments'=>$segments,'language'=>(string)$pick['language'],'name'=>(string)$pick['name'],'kind'=>(string)$pick['kind'],'quality'=>'usable'];cachePut($ck,$out);return $out;}
                }
            }
        }
        // Last direct fallback for hosts where type=list is blocked.
        foreach([$wanted,'en'] as $lc){
            foreach(['','&kind=asr'] as $kind){
                $direct='https://www.youtube.com/api/timedtext?v='.rawurlencode($videoId).'&lang='.rawurlencode($lc).'&fmt=json3'.$kind;
                [$dc,$dr]=httpReq($direct); if($dc<200||$dc>=400||$dr==='')continue; $dj=json_decode($dr,true); if(!is_array($dj)||empty($dj['events']))continue;
                $segments=[]; foreach($dj['events'] as $ev){if(empty($ev['segs'])||!is_array($ev['segs']))continue;$txt='';foreach($ev['segs'] as $seg)$txt.=(string)($seg['utf8']??'');$txt=captionClean($txt);if($txt==='')continue;$segments[]=['start'=>(int)($ev['tStartMs']??0),'dur'=>(int)($ev['dDurationMs']??0),'text'=>$txt];}
                if($segments&&captionUseful($segments)){$out=['segments'=>$segments,'language'=>$lc,'name'=>'YouTube captions','kind'=>$kind?'asr':'','quality'=>'usable'];cachePut($ck,$out);return $out;}
            }
        }
        return null;
    }

    $wanted=strtolower($wanted==='auto'?'en':$wanted); $track=null;
    if($trackIndex!==null && isset($tracks[$trackIndex]) && is_array($tracks[$trackIndex])) $track=$tracks[$trackIndex];
    if(!$track) foreach($tracks as $t){$lc=strtolower((string)($t['languageCode']??'')); if(($lc===$wanted||str_starts_with($lc,$wanted.'-')) && (($t['kind']??'')!=='asr')){$track=$t;break;}}
    if(!$track) foreach($tracks as $t){$lc=strtolower((string)($t['languageCode']??'')); if($lc===$wanted||str_starts_with($lc,$wanted.'-')){$track=$t;break;}}
    if(!$track){foreach($tracks as $t){$lc=strtolower((string)($t['languageCode']??''));if(str_starts_with($lc,'en')&&(($t['kind']??'')!=='asr')){$track=$t;break;}}}
    if(!$track){foreach($tracks as $t){$lc=strtolower((string)($t['languageCode']??''));if(str_starts_with($lc,'en')){$track=$t;break;}}}
    if(!$track)$track=$tracks[0]??null; if(!is_array($track)||empty($track['baseUrl']))return null;
    $base=html_entity_decode((string)$track['baseUrl'],ENT_QUOTES|ENT_HTML5,'UTF-8');
    $url=$base.(str_contains($base,'?')?'&':'?').'fmt=json3'; [$ccode,$raw]=httpReq($url); $segments=[];
    if($ccode>=200&&$ccode<400&&$raw!==''){ $j=json_decode($raw,true); if(is_array($j)&&!empty($j['events'])) foreach($j['events'] as $ev){if(empty($ev['segs'])||!is_array($ev['segs']))continue;$txt='';foreach($ev['segs'] as $seg)$txt.=(string)($seg['utf8']??'');$txt=captionClean($txt);if($txt==='')continue;$segments[]=['start'=>(int)($ev['tStartMs']??0),'dur'=>(int)($ev['dDurationMs']??0),'text'=>$txt];}}
    if(!$segments){[$xc,$xr]=httpReq($base);if($xc>=200&&$xc<400&&$xr!=='')$segments=parseTimedtextXml($xr);}
    if(!$segments||!captionUseful($segments))return null;
    $name=$track['name']['simpleText']??($track['name']['runs'][0]['text']??'');
    $out=['segments'=>$segments,'language'=>(string)($track['languageCode']??''),'name'=>(string)$name,'kind'=>(string)($track['kind']??''),'track_index'=>$trackIndex,'quality'=>'usable']; cachePut($ck,$out); return $out;
}
function splitUtf8(string $text,int $max=170): array {
    $parts=[]; while(ulen($text)>$max){$chunk=usub($text,0,$max);$cut=max(urpos($chunk,' ')?:0,urpos($chunk,'.')?:0);if($cut<60)$cut=$max;$parts[]=trim(usub($text,0,$cut));$text=trim(usub($text,$cut));} if($text!=='')$parts[]=$text; return $parts;
}

$action=$_GET['action']??'';
if($action==='settings_get'){
    $s=translate_settings(); out(['ok'=>true,'hcaptcha_enabled'=>(bool)$s['hcaptcha_enabled'],'hcaptcha_sitekey'=>(string)$s['hcaptcha_sitekey'],'cache_days'=>(int)$s['cache_days'],'free_ai_enabled'=>(bool)$s['free_ai_enabled'],'gemini_configured'=>trim((string)$s['gemini_api_key'])!=='','gemini_model'=>(string)$s['gemini_model']]);
}
if($action==='settings_save'){
    $d=body(); if(!translate_check_csrf((string)($d['csrf']??''))) out(['ok'=>false,'error'=>'Invalid session token'],403);
    $current=translate_settings();
    $new=['hcaptcha_enabled'=>!empty($d['hcaptcha_enabled']),'hcaptcha_sitekey'=>(string)($d['hcaptcha_sitekey']??''),'hcaptcha_secret'=>(string)($d['hcaptcha_secret']??''),'cache_days'=>max(1,min(30,(int)($d['cache_days']??7))),'free_ai_enabled'=>!empty($d['free_ai_enabled']),'gemini_api_key'=>(string)($d['gemini_api_key']??''),'gemini_model'=>(string)($d['gemini_model']??'gemini-2.5-flash')];
    if($new['hcaptcha_secret']==='') $new['hcaptcha_secret']=(string)$current['hcaptcha_secret'];
    if($new['gemini_api_key']==='') $new['gemini_api_key']=(string)$current['gemini_api_key'];
    if(!translate_save_settings($new)) out(['ok'=>false,'error'=>'Could not save settings'],500);
    out(['ok'=>true]);
}
if($action==='cache_clear'){
    $d=body(); if(!translate_check_csrf((string)($d['csrf']??''))) out(['ok'=>false,'error'=>'Invalid session token'],403);
    $count=0; foreach(glob(cacheDir().'/*.json') ?: [] as $file){ if(is_file($file) && @unlink($file)) $count++; }
    out(['ok'=>true,'deleted'=>$count]);
}



if($action==='gemini_audio_transcribe'){
    $d=body(); if(!translate_check_csrf((string)($d['csrf']??''))) out(['ok'=>false,'error'=>'Invalid session token'],403);
    $s=translate_settings(); if(empty($s['free_ai_enabled'])||trim((string)($s['gemini_api_key']??''))==='') out(['ok'=>false,'error'=>'Free AI audio assist is not configured'],409);
    $audio=(string)($d['audio']??''); $mime=(string)($d['mime']??'audio/wav'); $language=lang((string)($d['language']??'auto'));
    if($audio===''||strlen($audio)>1800000) out(['ok'=>false,'error'=>'Audio segment is empty or too large'],422);
    $txt=geminiAudioTranscribe($audio,$mime,$language); if($txt===null) out(['ok'=>false,'error'=>'Free AI audio transcription is temporarily unavailable'],502);
    out(['ok'=>true,'text'=>$txt,'provider'=>'gemini-2.5-flash-audio']);
}

if($action==='youtube_caption_tracks'){
    $videoId=youtubeVideoId((string)($_GET['video']??'')); if($videoId==='')out(['ok'=>false,'error'=>'Invalid YouTube video'],422);
    $tracks=youtubeCaptionTracks($videoId); out(['ok'=>true,'video'=>$videoId,'tracks'=>$tracks]);
}
if($action==='youtube_captions'){
    $videoId=youtubeVideoId((string)($_GET['video']??'')); $wanted=lang((string)($_GET['lang']??'en')); $trackRaw=(string)($_GET['track']??''); $trackIndex=$trackRaw!==''&&ctype_digit($trackRaw)?(int)$trackRaw:null;
    if($videoId==='')out(['ok'=>false,'error'=>'Invalid YouTube video'],422);
    $caps=youtubeCaptions($videoId,$wanted,$trackIndex); if(!$caps)out(['ok'=>false,'error'=>'No accessible YouTube captions found for this video'],404);
    out(['ok'=>true,'video'=>$videoId]+$caps);
}
if($action==='translate'){
    $d=body(); $text=cleanText((string)($d['text']??'')); $from=lang((string)($d['from']??'auto')); $to=lang((string)($d['to']??'lv')); $provider=(string)($d['provider']??'auto');
    if($text==='')out(['ok'=>false,'error'=>'Text is empty'],422); if(ulen($text)>8000)out(['ok'=>false,'error'=>'Chunk is too large'],413);
    $cacheKey=cacheKey($text,$from,$to,$provider); $cached=cacheGet($cacheKey);
    if($cached && isset($cached['translated'])) out(['ok'=>true,'translated'=>(string)$cached['translated'],'provider'=>(string)($cached['provider']??'cache'),'cached'=>true]);
    $order=$provider==='auto'?['google_free','mymemory','libretranslate','microsoft','gemini_free']:[$provider];
    foreach($order as $p){$translated=null; if($p==='google_free')$translated=googleTranslate($text,$from,$to); elseif($p==='mymemory')$translated=myMemory($text,$from,$to); elseif($p==='libretranslate')$translated=libre($text,$from,$to,$config); elseif($p==='microsoft')$translated=microsoft($text,$from,$to,$config); elseif($p==='gemini_free')$translated=geminiTranslate($text,$from,$to); if($translated!==null){cachePut($cacheKey,['translated'=>$translated,'provider'=>$p,'created'=>time()]);out(['ok'=>true,'translated'=>$translated,'provider'=>$p,'cached'=>false]);}}
    out(['ok'=>false,'error'=>'Translation providers are unavailable right now'],502);
}
if($action==='tts'){
    if(($config['google_tts_enabled']??true)!==true)out(['ok'=>false,'error'=>'Server TTS export disabled'],403);
    $d=body(); $text=cleanText((string)($d['text']??'')); $to=lang((string)($d['lang']??'lv')); if($text==='')out(['ok'=>false,'error'=>'Text is empty'],422); if(ulen($text)>2500)out(['ok'=>false,'error'=>'Audio export is limited to 2500 characters per request'],413);
    $binary=''; foreach(splitUtf8($text,170) as $part){$url='https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl='.rawurlencode($to).'&q='.rawurlencode($part);[$code,$raw]=httpReq($url);if($code<200||$code>=300||$raw==='')out(['ok'=>false,'error'=>'Free translated-audio service is unavailable; use browser Listen instead'],502);$binary.=$raw;}
    header_remove('Content-Type'); header('Content-Type: audio/mpeg'); header('Content-Disposition: attachment; filename="translation-'.$to.'.mp3"'); header('Content-Length: '.strlen($binary)); echo $binary; exit;
}
out(['ok'=>false,'error'=>'Unknown action'],404);
