<?php
echo "Attention, fichier arbo.inc.php périmé<br>\n";
/*PhpDoc:
name: arbo.inc.php
title: arbo.inc.php - arborescence de thèmes V2
classes:
doc: |
  J'appelle arborescence de thèmes un ensemble de thèmes structurés hiérarchiquement.
  Permet de regrouper la gestion des annexes Inspire et de l'arborescence Covadis.
  Une arborescence de thèmes est un thésaurus simple défini comme une arborescence de thèmes.
  J'ai a priori 2 arborescences de thèmes:
    - l'arborescence Covadis
    - la liste des annexes Inspire
  Je traite aussi les arborescence d'organisations comme des arborescence de thèmes
  Fonctionnellement une arborescence de thèmes est un arbre de thèmes, chacun étant un concept SKOS.
  Chaque concept correspond à
    - un prefLabel, éventuellement dans différentes langues
    - des altLabels et des hiddenLabels
  Le prefLabel fr peut être défini par le chemin des clés.
journal: |
  19/12/2021:
    - remplacement de ce fichier par skos.inc.php et theme.inc.php afin de mieux modulariser les fonctionnalités
      - skos.inc.php correspond aux fonctionnalités d'un thésaurus Skos
      - theme.inc.php s'appuie sur skos.inc.php pour exploiter les thèmes
  28/11/2021:
    - ajout possibilité entrée de premier niveau vide
  7/11/2021:
    - ajout regexps
  6/11/2021:
    - passage en V2
    - décomposition en 2 avec la création de tree.inc.php
  3/11/2021:
    - création
includes: [../phplib/accents.inc.php, tree.inc.php]
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/../phplib/accents.inc.php';
require_once __DIR__.'/tree.inc.php';

use Symfony\Component\Yaml\Yaml;

// concept SKOS avec un nom court et organisé en arbre
// Normalement il existe toujours au moins un prefLabel
// sauf certains noeuds intermédiaires qui sont juste des contenants
class Concept extends Node {
  private ?string $short; // éventuel nom court servant uniquement à l'affichage et pas comme clé
  private array $prefLabels; // [{lang}=> {label}], éventuellement non utilisé
  private array $altLabels; // liste des synonymes
  private array $hiddenLabels; // liste des synonymes cachés
  private array $regexps; // liste d'expression régulières utilisées pour classer une fiche à partir de ses champs textuels
  private ?string $definition; // champ définition
  private ?string $note; // champ note
  private ?string $covadis; // soit la chaine Covadis, soit null si pas de Covadis, soit 'default' si Cvadis par défaut
  
  function __construct(array $path, array $labels, array $children=[]) {
    //echo "Concept::__construct(prefLabels: ",json_encode($prefLabels),")<br>\n";
    $this->path = $path;
    $this->short = $labels['short'] ?? null;;
    $this->prefLabels = $labels['prefLabels'] ?? [];
    $this->altLabels = $labels['altLabels'] ?? [];
    $this->hiddenLabels = $labels['hiddenLabels'] ?? [];
    $this->regexps = $labels['regexps'] ?? [];
    $this->definition = $labels['definition'] ?? null;
    $this->note = $labels['note'] ?? null;
    $this->covadis = in_array('covadis', array_keys($labels)) ? $labels['covadis'] : 'default';
    $this->children = $children;
  }

  // renvoie le nom court s'il est défini, sinon la dernière clé du path
  function short(): string { return $this->short ? $this->short : $this->path[count($this->path)-1]; }
  function __toString(): string { return $this->prefLabels['fr'] ?? ''; }
  function prefLabel(string $lang='fr'): ?string { return $this->prefLabels[$lang] ?? null; }
  function altLabels(): array { return $this->altLabels; }
  function addAltLabel(string $label): void { $this->altLabels[] = $label; }
  function regexps() : array { return $this->regexps; }
  function definition() : ?string { return $this->definition; }
  function note() : ?string { return $this->note; }

  function asArray(): array {
    $children = [];
    foreach ($this->children as $id => $child)
      $children[$id] = $child->asArray();
    return array_merge(
      ['path'=> $this->path],
      $this->short ? ['short'=> $this->short] : [],
      $this->prefLabels ? ['prefLabels'=> $this->prefLabels] : [],
      $this->altLabels ? ['altlabels'=> $this->altLabels] : [],
      $this->hiddenLabels ? ['hiddenLabels'=> $this->hiddenLabels] : [],
      $children ? ['children'=> $children] : [],
    );
  }

  function show(): void { echo '<pre>', Yaml::dump($this->asArray()), "</pre>\n"; }
  
  function covadis(): ?string { // reconstruit l'étiquette Covadis
    if ($this->covadis === null)
      return null; // le thème ou sous-thème n'existait pas dans l'arborescence Covadis
    elseif ($this->covadis <> 'default')
      return $this->covadis; // l'étiquette Covadis a été stockée dans la liste des thèmes
    else {
      $lkey = $this->path[count($this->path)-1];
      if (count($this->path)==2)
        $lkey = 'N_'.$lkey;
      return str_replace(['-'], ['_'], strtoupper($lkey));
    }
  }
};

class Arbo extends Tree { // Arborescence de thèmes, chacun défini comme un Concept
  private bool $keyIsPrefLabel; // le prefLabel de chaque concept est-il le chemin défini par les clés ?
  private array $labels=[]; // table des synonymes pour retrouver facilement le concept, [$label -> $path]

  // standardise les étiquettes en remplaçant les catactères posant problèmes
  static function std(string $label): string { return str_replace(["’","’"],["'","'"], $label); }
  
  // Simplifie les étiquettes comsidérées comme similaires du point de vue synonymie, NE STANDARDISE PAS
  static function simplif(string $label) { return str_replace(['  '],[' '], strtolower(supprimeAccents($label))); }
  
  function __construct(string $filename) {
    $yaml = Yaml::parseFile($filename);
    $this->keyIsPrefLabel = $yaml['keyIsPrefLabel'];
    foreach ($yaml['children'] as $id => $child) {
      if ($child) {
        $this->labels[self::simplif($id)] = [$id];
        $this->children[$id] = $this->build($child, [$id]);
      }
    }
  }

  // parcours récursivement le sous-arbre pour créer les Concepts et construire les synonymes
  // chaque appel construit un thème, $yaml correspond à la description d'un thème
  private function build(array $yaml, array $path): Concept {
    //echo "Thematic::build($path)<br>\n";
    if ($this->keyIsPrefLabel)
      $yaml['prefLabels'] = ['fr'=> '/'.implode('/',$path)];
    foreach (['prefLabels','altLabels','hiddenLabels'] as $var) {
      foreach ($yaml[$var] ?? [] as $id => $label) {
        $yaml[$var][$id] = self::std($label);
        $this->labels[self::simplif($label)] = $path;
      }
    }
    foreach($yaml['regexps'] ?? [] as $id => $regexp)
      $yaml['regexps'][$id] = self::std($regexp);
    $children = [];
    foreach ($yaml['children'] ?? [] as $id => $child) {
      $childPath = array_merge($path, [$id]);
      if ($this->keyIsPrefLabel)
        $this->labels[strtolower($id)] = $childPath;
      $children[$id] = $this->build($child ?? [], $childPath);
    }
    return new Concept($path, $yaml, $children);
  }
  
  function asArray(): array {
    $children = [];
    foreach ($this->children as $id => $child)
      $children[$id] = $child->asArray();
    return array_merge(
      $children ? ['children'=> $children] : [],
      ['labels'=> $this->labels],
    );
  }
  
  function show(): void { echo '<pre>', Yaml::dump($this->asArray(), 8, 2), "</pre>\n"; }
  
  function labelIn(string $label): array { // le label est-il défini ? Si oui renvoie son path, sinon []
    return $this->labels[self::simplif(self::std($label))] ?? [];
  }
  
  function prefLabel(string $label, string $lang='fr'): ?string { // retrouve le prefLabel à partir d'un label
    if ($path = $this->labelIn($label))
      return $this->node($path)->prefLabel($lang);
    else
      return null;
  }
  
  function short(string $label): ?string { // retrouve le nom court à partir d'un label
    if ($path = $this->labelIn($label))
      return $this->node($path)->short();
    else
      return null;
  }

  function addCovadis() { // ajoute les étiquettes Covadis
    foreach ($this->children as $thid => $theme) {
      if ($tcovadis = $theme->covadis()) {
        $this->labels[strtolower("/$tcovadis")] = [$thid];
        $theme->addAltLabel("/$tcovadis");
        foreach ($theme->children() as $sthid => $stheme) {
          if ($stcovadis = $stheme->covadis()) {
            $this->labels[strtolower("/$tcovadis/$stcovadis")] = [$thid, $sthid];
            $stheme->addAltLabel("/$tcovadis/$stcovadis");
            $this->labels[strtolower($stcovadis)] = [$thid, $sthid];
            $stheme->addAltLabel($stcovadis);
          }
        }
      }
    }
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


echo "<pre>\n";

if (0) {
  $arbo = new Arbo('annexesinspire.yaml');
  foreach (['Répartition des espèces'] as $label)
    echo "$label -> ",$arbo->prefLabel($label),"<br>\n";
}
elseif (0) {
  $arbo = new Arbo('arbocovadis.yaml');
  foreach (['/NATURE_PAYSAGE_BIODIVERSITE','NATURE_PAYSAGE_BIODIVERSITE','INVENTAIRE NATURE BIODIVERSITE'] as $label)
    echo "$label -> ",$arbo->prefLabel($label) ?? '!',"<br>\n";
}
elseif (0) {
  $arbo = new Arbo('arbocovadis.yaml');
  $arbo->show();
}
elseif (0) {
  //$orgs = new Arbo('arbotest.yaml');
  $orgs = new Arbo('orgpmin.yaml');
  //print_r($orgs);
  echo "<table border=1><th>spath</th><th>short</th><th>prefLabelFr</th>\n";
  foreach ($orgs->nodes() as $spath => $node) {
    echo "<tr><td>$spath</td><td>",$node->short(),"</td><td>",$node->prefLabel() ?? "NO PREFLABEL","</td></tr>\n";
  }
  echo "</table>\n";
}
else {
  $geozones = new Arbo('geozones.yaml');
}
die("<br><br>\n");
