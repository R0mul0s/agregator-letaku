<?php

/**
 * Jednorázová kontrola hostingu před nasazením (R86, R93) — ověří, že hosting má všechno,
 * co Slevohlídka potřebuje: PHP 8.4 s rozšířeními, limity, proc_open a pdftotext (PDF letáků),
 * zápis do dočasné složky a odchozí HTTPS k obchodům.
 *
 * Použití: nahrát přes FTP do kořene webu pod náhodným jménem (např. kontrola-7f3a.php),
 * otevřít v prohlížeči, výsledek si zkopírovat a soubor HNED smazat — prozrazuje nastavení
 * serveru. Výstup je prostý text, řádek „CHYBA“ = s tím aplikace nepojede.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

/** Nejnižší verze PHP (composer.json). */
const MIN_PHP = '8.4.0';

/** Rozšíření, bez kterých aplikace nepojede (DEPLOYMENT.md, Předpoklady). */
const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'dom', 'openssl', 'curl', 'fileinfo', 'tokenizer', 'xml', 'ctype'];

/** Nejnižší memory_limit v MB (stažení Billy z celého katalogu, PDF letáků). */
const MIN_MEMORY_MB = 256;

/** Obchody, ke kterým musí jít odchozí HTTPS (stačí odpověď, i 403 nebo 404). */
const OUTBOUND_HOSTS = ['prodejny.kaufland.cz', 'www.lidl.cz', 'www.penny.cz', 'www.albert.cz', 'www.globus.cz', 'www.billa.cz', 'xapi.tesco.com'];

/** Časový limit jednoho odchozího požadavku (s). */
const OUTBOUND_TIMEOUT = 10;

/**
 * Vypíše jeden řádek výsledku.
 */
function report(bool $ok, string $label, string $detail = ''): void
{
    echo ($ok ? 'OK     ' : 'CHYBA  ').$label.($detail !== '' ? ' — '.$detail : '')."\n";
}

/**
 * Hodnota memory_limit v MB (-1 = bez limitu).
 */
function memoryLimitMb(): int
{
    $value = trim((string) ini_get('memory_limit'));
    if ($value === '-1') {
        return PHP_INT_MAX;
    }
    $number = (int) $value;

    return match (strtolower(substr($value, -1))) {
        'g' => $number * 1024,
        'm' => $number,
        'k' => intdiv($number, 1024),
        default => intdiv($number, 1024 * 1024),
    };
}

echo 'Kontrola hostingu pro Slevohlídku — '.date('Y-m-d H:i:s')."\n\n";

report(version_compare(PHP_VERSION, MIN_PHP, '>='), 'PHP '.PHP_VERSION, 'potřeba aspoň '.MIN_PHP);
report(PHP_SAPI !== 'cli', 'SAPI '.PHP_SAPI);

foreach (REQUIRED_EXTENSIONS as $extension) {
    report(extension_loaded($extension), 'rozšíření '.$extension);
}
report(true, 'GD / Imagick (volitelné)', (extension_loaded('gd') ? 'GD ano' : 'GD ne').', '.(extension_loaded('imagick') ? 'Imagick ano' : 'Imagick ne'));

report(memoryLimitMb() >= MIN_MEMORY_MB, 'memory_limit '.ini_get('memory_limit'), 'potřeba aspoň '.MIN_MEMORY_MB.'M');
report(true, 'max_execution_time '.ini_get('max_execution_time'), 'cron si prodlouží na 180 s, pokud to hosting dovolí');
$disabled = trim((string) ini_get('disable_functions'));
report(true, 'disable_functions', $disabled === '' ? '(nic)' : $disabled);
report(true, 'open_basedir', trim((string) ini_get('open_basedir')) ?: '(bez omezení)');

$tmp = sys_get_temp_dir();
$probe = @tempnam($tmp, 'slevohlidka');
report($probe !== false, 'zápis do dočasné složky '.$tmp, 'PDF letáku se ukládá na chvíli sem');
if ($probe !== false) {
    @unlink($probe);
}

$procOpen = function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', $disabled)), true);
report($procOpen, 'proc_open', 'spouští pdftotext (R86)');
if ($procOpen) {
    $process = @proc_open(['pdftotext', '-v'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (is_resource($process)) {
        $output = trim(stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
        $exit = proc_close($process);
        $version = preg_match('/pdftotext version ([\d.]+)/', $output, $match) === 1 ? $match[1] : '';
        report($version !== '', 'pdftotext', $version !== '' ? 'verze '.$version : 'nenalezen (výstup: '.substr($output, 0, 120).', kód '.$exit.')');
    } else {
        report(false, 'pdftotext', 'proc_open selhal');
    }
}

foreach (OUTBOUND_HOSTS as $host) {
    $context = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => OUTBOUND_TIMEOUT, 'ignore_errors' => true, 'header' => "User-Agent: Slevohlidka/1.0 (+slevohlidka.cz)\r\n"]]);
    $headers = @get_headers('https://'.$host.'/', false, $context);
    $status = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';
    report($status !== '', 'odchozí HTTPS '.$host, $status !== '' ? $status : 'bez odpovědi');
}

echo "\nHotovo. Soubor teď z hostingu smaž.\n";
