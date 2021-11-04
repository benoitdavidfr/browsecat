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
  private array $children=[]; // 
  private array $labels=[]; // [{label} -> {prefLabel}]

  function __construct(string $filename) {
    $yaml = Yaml::parseFile($filename);
    foreach ($yaml['children'] as $annex) {
      $this->children[] = $annex['prefLabel']['fr'];
      $this->labels[strtolower($annex['prefLabel']['fr'])] = $annex['prefLabel']['fr'];
      $this->labels[strtolower($annex['prefLabel']['en'])] = $annex['prefLabel']['fr'];
      foreach ($annex['hiddenLabels'] ?? [] as $label)
        $this->labels[strtolower($label)] = $annex['prefLabel']['fr'];
    }
  }
  
  function labelIn(string $label): bool { return isset($this->labels[strtolower($label)]); }
  
  function prefLabel(string $label): ?string { return $this->labels[strtolower($label)] ?? null; }
  
  // Les enfants soit d'un label soit à la racine
  function children(?string $label=null): array { return $this->children; }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$annexes = new Annexes('annexesinspire.yaml');
echo $annexes->labelIn("Zones de gestion, de restriction ou de réglementation et unités de déclaration") ? "Yes" : "No", "<br>\n";
echo $annexes->labelIn("Zones de gestion de restriction ou de réglementation et unités de déclaration") ? "Yes" : "No", "<br>\n";
  