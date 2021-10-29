<?php
/*PhpDoc:
name: annexes.inc.php
title: annexes.inc.php - gestion de la liste des annexes Inspire
classes:
doc: |
journal: |
  26/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

//echo "<pre>\n";

class Annexes {
  private array $labels=[]; // [$label -> 1]

  function __construct(string $filename) {
    $yaml = Yaml::parseFile($filename);
    foreach ($yaml['children'] as $annex) {
      $this->labels[strtolower($annex['prefLabel']['fr'])] = 1;
      $this->labels[strtolower($annex['prefLabel']['en'])] = 1;
      foreach ($annex['hiddenLabels'] ?? [] as $label)
        $this->labels[strtolower($label)] = 1;
    }
  }
  
  function labelIn(string $label): bool { return isset($this->labels[strtolower($label)]); }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$annexes = new Annexes('annexesinspire.yaml');
echo $annexes->labelIn("Zones de gestion, de restriction ou de réglementation et unités de déclaration") ? "Yes" : "No", "<br>\n";
echo $annexes->labelIn("Zones de gestion de restriction ou de réglementation et unités de déclaration") ? "Yes" : "No", "<br>\n";
  