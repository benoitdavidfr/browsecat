<?php
/*PhpDoc:
name: server.php
title: server.php - serveur pour tester HttpRetry
doc: |
  Génère aléatoirement soit une erreur soit un retour OK pour tester HttpRetry.
  Permet de rester le cas de redirection en utilisant le paramère redirect.
  Fonctionne sur localhost et Alwaysdata.
journal: |
  30-31/10/2021:
    - création
*/
if (isset($_GET['redirect'])) {
  $location = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]"; // supprime les paramètres
  header('HTTP/1.1 302 Found');
  header("Location: $location");
  die("302 Found\n");
}

//echo "<pre>"; print_r($_SERVER);
//$url = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; // reconstruit l'URL d'appel
//echo "url=$url<br>\n";

if (rand(0,4) == 4) { // Génère 1 fois sur 5 un retour OK et les 4 autres fois une erreur 404
  die ("Serveur Ok\n");
}
else { // sinon une erreur
  header('HTTP/1.1 404 Not Found');
  die ("Erreur 404 Not Found\n");
}
