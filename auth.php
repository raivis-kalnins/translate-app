<?php
declare(strict_types=1);

function translate_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = is_file(__DIR__.'/config.local.php') ? (array) require __DIR__.'/config.local.php' : [];
    return $cfg;
}

function translate_settings_path(): string { return __DIR__.'/storage/settings.json'; }
function translate_settings(): array {
    $defaults = ['hcaptcha_enabled'=>false,'hcaptcha_sitekey'=>'','hcaptcha_secret'=>'','cache_days'=>7,'free_ai_enabled'=>false,'gemini_api_key'=>'','gemini_model'=>'gemini-2.5-flash'];
    $path = translate_settings_path();
    if (!is_file($path)) return $defaults;
    $d = json_decode((string)@file_get_contents($path), true);
    return is_array($d) ? array_merge($defaults, $d) : $defaults;
}
function translate_save_settings(array $settings): bool {
    $dir = dirname(translate_settings_path());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $data = [
        'hcaptcha_enabled'=>(bool)($settings['hcaptcha_enabled']??false),
        'hcaptcha_sitekey'=>trim((string)($settings['hcaptcha_sitekey']??'')),
        'hcaptcha_secret'=>trim((string)($settings['hcaptcha_secret']??'')),
        'cache_days'=>max(1,min(30,(int)($settings['cache_days']??7))),
        'free_ai_enabled'=>(bool)($settings['free_ai_enabled']??false),
        'gemini_api_key'=>trim((string)($settings['gemini_api_key']??'')),
        'gemini_model'=>preg_replace('/[^A-Za-z0-9._-]/','',trim((string)($settings['gemini_model']??'gemini-2.5-flash'))) ?: 'gemini-2.5-flash',
    ];
    return @file_put_contents(translate_settings_path(), json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function translate_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('translate63');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function translate_password_hash(): string {
    $cfg = translate_config();
    return (string)($cfg['login_password_hash'] ?? '$2y$12$VOhURU2hfNVNqTpoxqHB2uXb46psKKbGqyYUN.4ZJTjFuGXhtso16');
}
function translate_logged_in(): bool { translate_start_session(); return !empty($_SESSION['translate_logged_in']); }
function translate_csrf(): string {
    translate_start_session();
    if (empty($_SESSION['translate_csrf'])) $_SESSION['translate_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['translate_csrf'];
}
function translate_check_csrf(?string $token): bool { return is_string($token) && hash_equals(translate_csrf(), $token); }
function translate_login(string $password): bool {
    if (!password_verify($password, translate_password_hash())) return false;
    translate_start_session();
    session_regenerate_id(true);
    $_SESSION['translate_logged_in'] = true;
    translate_csrf();
    return true;
}
function translate_logout(): void { translate_start_session(); $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); }
function translate_http_post(string $url, array $fields): array {
    $post=http_build_query($fields);
    if(function_exists('curl_init')){
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch); return [$code,$raw===false?'':$raw];
    }
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$post,'timeout'=>12,'ignore_errors'=>true]]);
    $raw=@file_get_contents($url,false,$ctx); return [200,$raw===false?'':$raw];
}
function translate_verify_hcaptcha(string $token): bool {
    $s=translate_settings();
    if (empty($s['hcaptcha_enabled'])) return true;
    if ($token==='' || empty($s['hcaptcha_secret'])) return false;
    [, $raw]=translate_http_post('https://api.hcaptcha.com/siteverify',['secret'=>(string)$s['hcaptcha_secret'],'response'=>$token,'remoteip'=>(string)($_SERVER['REMOTE_ADDR']??'')]);
    $j=json_decode($raw,true); return is_array($j) && !empty($j['success']);
}
