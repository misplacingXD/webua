<?php
// Formulaire de contact d'Ô Lit Divin : envoie le message par e-mail à Laure.
// L'adresse d'envoi doit exister dans Hostinger pour que le formulaire fonctionne.
$destinataire = 'lesgitesdelaure@gmail.com';   // l'adresse qui reçoit les messages
$expediteur   = 'contact@litdivin.fr';   // adresse créée sur le domaine, utilisée seulement pour l'envoi
$max_par_heure = 5;                       // messages acceptés par visiteur et par heure

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function repondre($code, $ok) {
  http_response_code($code);
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  repondre(405, false);
}

// Piège à robots : ce champ est invisible pour les humains
if (!empty($_POST['site_web'])) {
  repondre(200, true);
}

// Refuse les envois lancés depuis un autre site
$origine = $_SERVER['HTTP_ORIGIN'] ?? '';
$hote = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');   // sans le numéro de port
if ($origine !== '' && parse_url($origine, PHP_URL_HOST) !== $hote) {
  repondre(403, false);
}

$nom      = trim(strip_tags($_POST['nom'] ?? ''));
$email    = trim($_POST['email'] ?? '');
$occasion = trim(strip_tags($_POST['occasion'] ?? ''));
$message  = trim(strip_tags($_POST['message'] ?? ''));

// Empêche l'ajout d'en-têtes cachés dans le nom, l'adresse ou l'occasion
$nom      = str_replace(["\r", "\n"], ' ', $nom);
$email    = str_replace(["\r", "\n"], '', $email);
$occasion = str_replace(["\r", "\n"], ' ', $occasion);

if ($nom === '' || mb_strlen($nom) > 100
  || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
  || mb_strlen($occasion) > 100
  || mb_strlen($message) > 5000) {
  repondre(400, false);
}

// Limite le nombre de messages par visiteur : sans ça, un robot peut inonder la boîte de Laure
// et faire classer le domaine comme expéditeur de spam.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
$compteur = sys_get_temp_dir() . '/litdivin-contact-' . hash('sha256', $ip) . '.json';
$maintenant = time();
$envois = [];
$f = @fopen($compteur, 'c+');
if ($f && flock($f, LOCK_EX)) {
  $lu = json_decode(stream_get_contents($f) ?: '[]', true);
  if (is_array($lu)) {
    foreach ($lu as $t) {
      if (is_int($t) && $t > $maintenant - 3600) $envois[] = $t;
    }
  }
  if (count($envois) >= $max_par_heure) {
    flock($f, LOCK_UN);
    fclose($f);
    repondre(429, false);
  }
  $envois[] = $maintenant;
  ftruncate($f, 0);
  rewind($f);
  fwrite($f, json_encode($envois));
  flock($f, LOCK_UN);
  fclose($f);
}

$sujet = '=?UTF-8?B?' . base64_encode("Nouveau message du site Ô Lit Divin : $nom") . '?=';
$corps = "Nom : $nom\nE-mail : $email\nOccasion : $occasion\n\nMessage :\n$message\n";
$entetes = [
  'From: =?UTF-8?B?' . base64_encode('Ô Lit Divin') . '?= <' . $expediteur . '>',
  'Reply-To: ' . $email,
  'Content-Type: text/plain; charset=UTF-8',
  'MIME-Version: 1.0',
];

$envoye = mail($destinataire, $sujet, $corps, implode("\r\n", $entetes), '-f' . $expediteur);
repondre($envoye ? 200 : 500, $envoye);
