<?php
// Formulaire de contact d'Ô Lit Divin : envoie le message par e-mail à Laure.
// L'adresse d'envoi doit exister dans Hostinger pour que le formulaire fonctionne.
$destinataire = 'lesgitesdelaure@gmail.com';   // l'adresse qui reçoit les messages
$expediteur   = 'contact@litdivin.fr';   // adresse créée sur le domaine, utilisée seulement pour l'envoi

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false]);
  exit;
}

// Piège à robots : ce champ est invisible pour les humains
if (!empty($_POST['site_web'])) {
  echo json_encode(['ok' => true]);
  exit;
}

$nom      = trim(strip_tags($_POST['nom'] ?? ''));
$email    = trim($_POST['email'] ?? '');
$occasion = trim(strip_tags($_POST['occasion'] ?? ''));
$message  = trim(strip_tags($_POST['message'] ?? ''));

if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) > 5000) {
  http_response_code(400);
  echo json_encode(['ok' => false]);
  exit;
}

// Empêche l'ajout d'en-têtes cachés dans le nom ou l'adresse
$nom   = str_replace(["\r", "\n"], ' ', $nom);
$email = str_replace(["\r", "\n"], '', $email);

$sujet = '=?UTF-8?B?' . base64_encode("Nouveau message du site Ô Lit Divin : $nom") . '?=';
$corps = "Nom : $nom\nE-mail : $email\nOccasion : $occasion\n\nMessage :\n$message\n";
$entetes = [
  'From: =?UTF-8?B?' . base64_encode('Ô Lit Divin') . '?= <' . $expediteur . '>',
  'Reply-To: ' . $email,
  'Content-Type: text/plain; charset=UTF-8',
  'MIME-Version: 1.0',
];

$envoye = mail($destinataire, $sujet, $corps, implode("\r\n", $entetes), '-f' . $expediteur);
http_response_code($envoye ? 200 : 500);
echo json_encode(['ok' => $envoye]);
