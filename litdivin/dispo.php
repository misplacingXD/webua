<?php
// Dates libres d'Ô Lit Divin, lues sur la page de réservation Beds24.
// Le résultat est gardé 1 minute pour ne pas interroger Beds24 à chaque visite.
// Si Beds24 change sa page, le calendrier n'affiche simplement plus les dates libres en couleur :
// le prix et la disponibilité restent vérifiés quand le visiteur choisit ses dates.
$etablissement = 130955;
$logement = 292537;
$cache = sys_get_temp_dir() . '/litdivin-dispo-' . $logement . '.json';
$duree = 60;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lire_page($url) {
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 12,
      CURLOPT_USERAGENT => 'Mozilla/5.0 (site O Lit Divin)',
    ]);
    $page = curl_exec($c);
    curl_close($c);
    return is_string($page) ? $page : '';
  }
  $page = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 12]]));
  return is_string($page) ? $page : '';
}

if (is_file($cache) && time() - filemtime($cache) < $duree) {
  readfile($cache);
  exit;
}

$page = lire_page("https://beds24.com/booking.php?propid=$etablissement&roomid=$logement&numadult=2&lang=fr&referer=site");
$trouve = preg_match('/#ajaxroomoffer(?:\d+-)?' . $logement . '"\)\.data\("avail",\s*(\{[^}]*\})/', $page, $m);
$dates = $trouve ? json_decode($m[1], true) : null;
if (!is_array($dates) || count($dates) < 10) {
  // Beds24 ne répond pas comme prévu : on garde la dernière liste connue s'il y en a une
  if (is_file($cache)) {
    readfile($cache);
  } else {
    echo json_encode(['ok' => false]);
  }
  exit;
}

$json = json_encode(['ok' => true, 'dates' => array_map('strval', array_keys($dates))]);
$provisoire = $cache . '.' . getmypid();
if (@file_put_contents($provisoire, $json) !== false) {
  @rename($provisoire, $cache);
}
echo $json;
