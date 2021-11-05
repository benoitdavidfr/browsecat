<?php
/*PhpDoc:
name: arbo.inc.php
title: arbo.inc.php - arborescence de thèmes
classes:
doc: |
  J'appelle arborescence de thèmes un ensemble de thèmes structurés hiérarchiquement.
  Permet de regrouper la gestion des annexes Inspire et de l'arborescence Covadis.
  Une arborescence de thèmes est un thésaurus simple défini comme une arborescence de thèmes.
  J'ai a priori 2 arborescences de thèmes:
    - l'arborescence Covadis
    - la liste des annexes Inspire
  Fonctionnellement une arborescence de thèmes est un arbre de thèmes, chacun étant un concept SKOS.
  Chaque concept correspond à
    - un prefLabel, éventuellement dans différentes langues
    - des altLabels et des hiddenLabels
  Le prefLabel fr peut être défini par le chemin des clés.
journal: |
  3/11/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

//echo "<pre>";

class Concept { // un concept SKOS avec éventuellement des enfants
  private array $path; // chemin d'accès dans l'arbre comme liste de clés
  private ?string $short; // éventuel nom court
  private array $prefLabels; // [{lang}=> {label}], éventuellement non utilisé
  private array $altLabels; // liste des synonymes
  private array $hiddenLabels; // liste des synonymes cachés
  private array $children; // tableau associatif des enfants, chacun comme Concept avec un clé d'accès
  
  function __construct(array $path, ?string $short, array $prefLabels, array $altLabels, array $hiddenLabels, array $children) {
    //echo "Concept::__construct(prefLabels: ",json_encode($prefLabels),")<br>\n";
    $this->path = $path;
    $this->short = $short;
    $this->prefLabels = $prefLabels;
    $this->altLabels = $altLabels;
    $this->hiddenLabels = $hiddenLabels;
    $this->children = $children;
  }
  
  function pathAsString(): string { return '/'.implode('/', $this->path); }
  function __toString(): string { return $this->prefLabels['fr'] ?? ''; }
  function prefLabel(string $lang): ?string { return $this->prefLabels[$lang]; }
  function path(): ?string { return $this->prefLabels[$lang]; }
  function children(): array { return $this->children; }

  // renvoie le nom court s'il est défini, sinon la dernière clé du path
  function short(): string { return $this->short ? $this->short : $this->path[count($this->path)-1]; }

  function getChild(array $path): Concept { // accès à la descendance par le chemin comme liste de clés
    //print_r($path);
    $first = array_shift($path);
    if (!isset($this->children[$first]))
      throw new Exception("Erreur dans Concept::getChild() sur '$first'");
    elseif (!$path)
      return $this->children[$first];
    else
      return $this->children[$first]->getChild($path);
  }
  
  function asArray(): array {
    return array_merge(
      ['path'=> $this->path],
      ['prefLabels'=> $this->prefLabels],
      $this->altLabels ? ['altlabels'=> $this->altLabels] : [],
      ['hiddenLabels'=> $this->hiddenLabels],
    );
  }
  
  function show() { echo '<pre>', Yaml::dump($this->asArray()), "</pre>\n"; }
};

class Arbo { // une arboresence = ens. de thèmes structurés hiérarchiquement
  private bool $keyIsPrefLabel; // le prefLabel de chaque concept est-il le chemin défini par les clés ?
  private array $children=[]; // tableau associatif des thèmes de premier niveau, chacun comme Concept
  private array $labels=[]; // table des synonymes pour retrouver facilement le prefLabel, [$label -> $path]
  
  // standardise les étiquettes en remplacant les catactères posant problèmes
  static function std(string $label): string { return str_replace(["’","’"],["'","'"], $label); }
  
  function __construct(string $filename) {
    $yaml = Yaml::parseFile($filename);
    $this->keyIsPrefLabel = $yaml['keyIsPrefLabel'];
    $this->children = $this->build($this->keyIsPrefLabel, $yaml)->children();
    //$this->show();
  }

  // parcours l'arbre pour créer les Thèmes et construire les synonymes
  // $yaml correspond à la description d'un thème, chaque appel construit un thème
  private function build(bool $keyIsPrefLabel, array $yaml, array $path=[]): Concept {
    //echo "Thematic::build($path)<br>\n";
    foreach (['prefLabels','altLabels','hidenLabels'] as $var) {
      foreach ($yaml[$var] ?? [] as $id => $label) {
        $yaml[$var][$id] = self::std($label); // apostrophe problématique
      }
    }
    if (!$keyIsPrefLabel) {
      foreach ($yaml['prefLabels'] ?? [] as $label)
        $this->labels[strtolower($label)] = $path;
    }
    elseif ($path) {
      $this->labels[strtolower('/'.implode('/', $path, ))] = $path;
    }
    foreach ($yaml['altLabels'] ?? [] as $label)
      $this->labels[strtolower($label)] = $path;
    foreach ($yaml['hiddenLabels'] ?? [] as $label)
      $this->labels[strtolower($label)] = $path;
    $children = [];
    foreach ($yaml['children'] ?? [] as $id => $child) {
      if ($keyIsPrefLabel) {
        $this->labels[strtolower($id)] = array_merge($path, [$id]);
      }
      $children[$id] = $this->build($keyIsPrefLabel, $child ?? [], array_merge($path, [$id]));
    }
    return new Concept(
      $path,
      $yaml['short'] ?? null,
      $path ?
         (($keyIsPrefLabel || !isset($yaml['prefLabels'])) ? ['fr'=> '/'.implode('/',$path)] : $yaml['prefLabels'])
         : [],
      $yaml['altLabels'] ?? [],
      $yaml['hiddenLabels'] ?? [],
      $children,
    );
  }

  function children(string $label): array { // enfants d'un concept donné comme [id => Concept]
    if ($label == '')
      return $this->children;
    elseif ($path = $this->labels[strtolower($label)] ?? null) {
      return $this->getChild($path)->children();
    }
    else
      return [];
  }
  
  // affiche récursivement l'arborescence
  function show(?Concept $concept=null, array $path=[]): void {
    echo "<ul>\n";
    $children = !$concept ? $this->children('') : $concept->children();
    foreach ($children as $id => $child) {
      $label = $child->prefLabel('fr');
      $childPath = array_merge($path,[$id]);
      echo "<li><a href='?action=show&amp;path=/",implode('/',$childPath),"'>$label</a>\n";
      $this->show($child, $childPath);
      echo "</li>\n";
    }
    echo "</ul>\n";
  }
  
  // accès par le chemin comme liste de clés
  function getChild(array $path): Concept {
    //print_r($path);
    if (count($path)==1)
      return $this->children[$path[0]];
    else {
      $first = array_shift($path);
      return $this->children[$first]->getChild($path);
    }
  }
  
  // le label est-il défini ?
  function labelIn(string $label): bool {
    $label = self::std($label);
    return isset($this->labels[strtolower($label)]);
  }
  
  // retrouve le prefLabel à partir d'un label
  function prefLabel(string $label): ?string {
    $label = self::std($label);
    if ($path = $this->labels[strtolower($label)] ?? null) {
      $prefLabel = $this->getChild($path)->prefLabel('fr');
      //echo "Thematic::prefLabel($label)->$prefLabel<br>\n";
      return $prefLabel;
    }
    else {
      //echo "Thematic::prefLabel($label)->null<br>\n";
      return null;
    }
  }
  
  // renvoie le nom court ou null ssi le label est absent de l'arborescence
  function short(string $label): ?string {
    $label = self::std($label);
    if ($path = $this->labels[strtolower($label)] ?? null) {
      return $this->getChild($path)->short();
    }
    else {
      return null;
    }
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


echo "<!DOCTYPE HTML><html><head><title>TEST arbo.inc.php</title></head><body>\n";
if (0) {
  $arbo = new Thematic('arbocovadis.yaml', true);
  echo "<pre>\n"; print_r($arbo); echo "</pre>\n";

  if (isset($_GET['action'])) {
    if ($_GET['action']=='show') {
      $path = explode('/', $_GET['path']);
      array_shift($path);
      $arbo->getChild($path)->show();
    }
    die();
  }

  $arbo->show();

  echo $arbo->labelIn('Nuisance/Bruit') ? 'Yes' : 'No', "<br>\n";
  echo $arbo->labelIn('Nuisance/Brui') ? 'Yes' : 'No', "<br>\n";

  echo $arbo->prefLabel('Nuisance/Bruit'), "<br>\n";
  echo $arbo->prefLabel('nuisance/Bruit'), "<br>\n";
  echo $arbo->prefLabel('Nuisance/Brui'), "<br>\n";
  
  echo "<pre>"; print_r($arbo->children("/AMENAGEMENT_URBANISME"));
}
elseif (0) {
  $annexes = new Thematic('annexesinspire.yaml', false);
  echo "<pre>\n"; print_r($annexes); echo "</pre>\n";
  $annexes->show();
  echo $annexes->prefLabel('Zones de gestion, de restriction ou de réglementation et unités de déclaration'), "<br>\n";
  
}
else {
  $orgs = new Arbo('organisation.yaml');
  //echo "<pre>"; print_r($orgs);
  foreach (["Préfecture de l’Isère","Préfecture de l'Isère","Préfecture de l’Isère"] as $orgName) {
    echo "$orgName -> ",$orgs->labelIn($orgName) ? 'In' : 'Out',"<br>\n";
  }
}
die("<br><br>\n");