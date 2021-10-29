<?php
/*PhpDoc:
name: arbo.inc.php
title: arbo.inc.php - gestion de l'arborescence covadis
classes:
doc: |
journal: |
  26/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

//echo "<pre>\n";

/*
class Arbo {
  private string $key;
  private array $hiddenLabels;
  private array $children;
  
  static function load(string $filename): Arbo {
    $yaml = Yaml::parseFile($filename);
    return new Arbo('', $yaml);
  }
  
  function __construct(string $key, array $yaml) {
    //echo "<pre>\n"; print_r($yaml);
    $this->key = $key;
    $this->hiddenLabels = $yaml['hiddenLabels'] ?? [];
    foreach ($yaml['children'] ?? [] as $id => $child) {
      $this->children[$id] = $child ? new Arbo($id, $child) : null;
    }
  }
};

$arbo = Arbo::load('arbocovadis.yaml');
echo "<pre>\n"; print_r($arbo);
*/

class Arbo {
  private array $labels=[]; // [$label -> $path]

  function __construct(string $filename) {
    $yaml = Yaml::parseFile($filename);
    $this->browse('', $yaml);
  }
  
  // parcours l'arbre pour récupérer les hiddenLabels
  function browse(string $path, array $yaml) {
    //echo "browse($path)<br>\n";
    foreach ($yaml['hiddenLabels'] ?? [] as $hiddenLabel)
      $this->labels[strtolower($hiddenLabel)] = $path;
    foreach ($yaml['children'] ?? [] as $id => $child) {
      $this->labels[strtolower($id)] = $path;
      if ($child)
        $this->browse("$path/$id", $child);
    }
  }
  
  function labelIn(string $label): bool { return isset($this->labels[strtolower($label)]); }
  
  function prefLabel(string $label): ?string { return $this->labels[strtolower($label)] ?? null; }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$arbo = new Arbo('arbocovadis.yaml');
//echo "<pre>\n"; print_r($arbo);

echo $arbo->labelInArbo('Nuisance/Bruit') ? 'Yes' : 'No', "<br>\n";
echo $arbo->labelInArbo('Nuisance/Brui') ? 'Yes' : 'No', "<br>\n";

echo $arbo->prefLabel('Nuisance/Bruit'), "<br>\n";
echo $arbo->prefLabel('nuisance/Bruit'), "<br>\n";
echo $arbo->prefLabel('Nuisance/Brui'), "<br>\n";
