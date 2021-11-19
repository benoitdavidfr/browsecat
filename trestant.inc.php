<?php
/*PhpDoc:
title: trestant.inc.php - calcule le temps restant pour éxécuter une commande en fonction de la proportion de traitement effectuée
name: trestant.inc.php
doc: |
journal: |
  13/11/2021:
    - création
*/

// retourne le temps restant, $proportion est la proportion effectuée du traitement
function tempsRestant(float $proportion=0): ?string {
  static $start = 0;
  if ($proportion == 0) {
    $start = time();
    return null;
  }
  $duree = time() - $start;
  if (!$duree)
    return 'non défini';
  $total = $duree / $proportion; // estimation du temps total en secondes
  $restant = $total * (1 - $proportion); // estimation du temps restant en secondes
  //return sprintf('d=%f, p=%f, t=%f -> r=%.0f s.', $duree, $proportion, $total, $restant);
  if ($restant < 60)
    return sprintf('%.0f s.', $restant);
  else
    return sprintf('%d min %.0f s.', floor($restant/60), $restant - floor($restant/60)*60);
}
