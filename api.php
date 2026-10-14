<?php
declare(strict_types=1);
require __DIR__.'/auth.php';
translate_start_session();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$config = translate_config();

const CACHE_TTL = 86400;
function cacheDir(): string {
    $dir=__DIR__.'/storage/cache';
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    return $dir;
}
function cleanupCache(): void {
    $dir=cacheDir(); $now=time();
    foreach(glob($dir.'/*.json') ?: [] as $file){ if(is_file($file) && $now-(int)@filemtime($file)>CACHE_TTL) @unlink($file); }
}
function cacheKey(string $text,string $from,string $to,string $provider): string { return hash('sha256',$provider."\0".$from."\0".$to."\0".$text); }
function cacheGet(string $key): ?array {
    $file=cacheDir().'/'.$key.'.json';
    if(!is_file($file) || time()-(int)@filemtime($file)>CACHE_TTL){ if(is_file($file)) @unlink($file); return null; }
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
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>22,CURLOPT_USERAGENT=>'Mozilla/5.0 TranslateApp/1.0'];
        if ($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$post;}
        if($headers)$opts[CURLOPT_HTTPHEADER]=$headers;
        curl_setopt_array($ch,$opts); $data=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
        return [$code,$data===false?'':$data,$err];
    }
    $headerLines=array_merge(['User-Agent: Mozilla/5.0 TranslateApp/1.0'],$headers);
    $context=stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>implode("\r\n",$headerLines),'content'=>$post??'','timeout'=>22,'ignore_errors'=>true]]);
    $data=@file_get_contents($url,false,$context); $code=0;
    foreach(($http_response_header??[]) as $line){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$line,$m)){$code=(int)$m[1];break;}}
    return [$code,$data===false?'':$data,$data===false?'HTTP request failed':''];
}
function lang(string $s): string { return preg_replace('/[^A-Za-z0-9_-]/','',substr($s,0,20)) ?: 'auto'; }
function cleanText(string $s): string { return trim(preg_replace('/\R/u', "\n", $s) ?? $s); }
function ulen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : strlen($s); }
function usub(string $s,int $start,?int $len=null): string { if(function_exists('mb_substr')) return $len===null ? mb_substr($s,$start,null,'UTF-8') : mb_substr($s,$start,$len,'UTF-8'); return $len===null ? substr($s,$start) : substr($s,$start,$len); }
function urpos(string $s,string $needle): int|false { return function_exists('mb_strrpos') ? mb_strrpos($s,$needle,0,'UTF-8') : strrpos($s,$needle); }
function googleTranslate(string $text,string $from,string $to): ?string {
    $url='https://translate.googleapis.com/translate_a/single?client=gtx&dt=t&sl='.rawurlencode($from ?: 'auto').'&tl='.rawurlencode($to).'&q='.rawurlencode($text);
    [$code,$raw]=httpReq($url); if($code<200||$code>=300||$raw==='')return null; $j=json_decode($raw,true); if(!is_array($j)||!isset($j[0]))return null;
    $out=''; foreach($j[0] as $row){ if(is_array($row)&&isset($row[0]))$out.=(string)$row[0]; } return $out!==''?$out:null;
}
function myMemory(string $text,string $from,string $to): ?string {
    $src=($from==='auto'||$from==='')?'en':$from;
    $url='https://api.mymemory.translated.net/get?q='.rawurlencode($text).'&langpair='.rawurlencode($src.'|'.$to);
    [$code,$raw]=httpReq($url); if($code<200||$code>=300)return null; $j=json_decode($raw,true); $v=$j['responseData']['translatedText']??null; return is_string($v)&&$v!==''?$v:null;
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
function splitUtf8(string $text,int $max=170): array {
    $parts=[]; while(ulen($text)>$max){$chunk=usub($text,0,$max);$cut=max(urpos($chunk,' ')?:0,urpos($chunk,'.')?:0);if($cut<60)$cut=$max;$parts[]=trim(usub($text,0,$cut));$text=trim(usub($text,$cut));} if($text!=='')$parts[]=$text; return $parts;
}

$action=$_GET['action']??'';
if($action==='settings_get'){
    $s=translate_settings(); out(['ok'=>true,'hcaptcha_enabled'=>(bool)$s['hcaptcha_enabled'],'hcaptcha_sitekey'=>(string)$s['hcaptcha_sitekey']]);
}
if($action==='settings_save'){
    $d=body(); if(!translate_check_csrf((string)($d['csrf']??''))) out(['ok'=>false,'error'=>'Invalid session token'],403);
    $current=translate_settings();
    $new=['hcaptcha_enabled'=>!empty($d['hcaptcha_enabled']),'hcaptcha_sitekey'=>(string)($d['hcaptcha_sitekey']??''),'hcaptcha_secret'=>(string)($d['hcaptcha_secret']??'')];
    if($new['hcaptcha_secret']==='') $new['hcaptcha_secret']=(string)$current['hcaptcha_secret'];
    if(!translate_save_settings($new)) out(['ok'=>false,'error'=>'Could not save settings'],500);
    out(['ok'=>true]);
}
if($action==='cache_clear'){
    $d=body(); if(!translate_check_csrf((string)($d['csrf']??''))) out(['ok'=>false,'error'=>'Invalid session token'],403);
    $count=0; foreach(glob(cacheDir().'/*.json') ?: [] as $file){ if(is_file($file) && @unlink($file)) $count++; }
    out(['ok'=>true,'deleted'=>$count]);
}

if($action==='translate'){
    $d=body(); $text=cleanText((string)($d['text']??'')); $from=lang((string)($d['from']??'auto')); $to=lang((string)($d['to']??'lv')); $provider=(string)($d['provider']??'auto');
    if($text==='')out(['ok'=>false,'error'=>'Text is empty'],422); if(ulen($text)>8000)out(['ok'=>false,'error'=>'Chunk is too large'],413);
    $cacheKey=cacheKey($text,$from,$to,$provider); $cached=cacheGet($cacheKey);
    if($cached && isset($cached['translated'])) out(['ok'=>true,'translated'=>(string)$cached['translated'],'provider'=>(string)($cached['provider']??'cache'),'cached'=>true]);
    $order=$provider==='auto'?['google_free','mymemory','libretranslate','microsoft']:[$provider];
    foreach($order as $p){$translated=null; if($p==='google_free')$translated=googleTranslate($text,$from,$to); elseif($p==='mymemory')$translated=myMemory($text,$from,$to); elseif($p==='libretranslate')$translated=libre($text,$from,$to,$config); elseif($p==='microsoft')$translated=microsoft($text,$from,$to,$config); if($translated!==null){cachePut($cacheKey,['translated'=>$translated,'provider'=>$p,'created'=>time()]);out(['ok'=>true,'translated'=>$translated,'provider'=>$p,'cached'=>false]);}}
    out(['ok'=>false,'error'=>'Translation providers are unavailable right now'],502);
}
if($action==='tts'){
    if(($config['google_tts_enabled']??true)!==true)out(['ok'=>false,'error'=>'Server TTS export disabled'],403);
    $d=body(); $text=cleanText((string)($d['text']??'')); $to=lang((string)($d['lang']??'lv')); if($text==='')out(['ok'=>false,'error'=>'Text is empty'],422); if(ulen($text)>2500)out(['ok'=>false,'error'=>'Audio export is limited to 2500 characters per request'],413);
    $binary=''; foreach(splitUtf8($text,170) as $part){$url='https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl='.rawurlencode($to).'&q='.rawurlencode($part);[$code,$raw]=httpReq($url);if($code<200||$code>=300||$raw==='')out(['ok'=>false,'error'=>'Free translated-audio service is unavailable; use browser Listen instead'],502);$binary.=$raw;}
    header_remove('Content-Type'); header('Content-Type: audio/mpeg'); header('Content-Disposition: attachment; filename="translation-'.$to.'.mp3"'); header('Content-Length: '.strlen($binary)); echo $binary; exit;
}
out(['ok'=>false,'error'=>'Unknown action'],404);
