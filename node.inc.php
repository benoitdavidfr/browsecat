<?php
/*PhpDoc:
name: node.inc.php
title: node.inc.php - arbre de noeuds
classes:
doc: |
  Réécriture de tree.inc.php pour
    - fusionner les 2 classes Node et Tree en une seule classe Node
    - permettre dans une classe héritée de constuire un arbre d'objets de la classe héritée
  Un arbre est constitué de noeuds ; chaque noeud est associé à son père au travers d'une clé.
  Il est possible de définir des champs pour le noeud en créant une classe héritée de la classe Node
  tout en bénéficiant des fonctionnalités définies pour Node.
journal: |
  18-19/12/2021:
    - création par fork de tree.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class Node { // Noeud
  protected array $path=[]; // chemin d'accès ds l'arbre comme liste de clés, chaque clé est string ne contenant pas '/'
  protected array $children=[]; // dictionnaire des enfants, chacun comme Node ou objet d'une sous-classe

  function path(): array { return $this->path; }
  function pathAsString(): string { return '/'.implode('/', $this->path); }
  function children(): array { return $this->children; } // enfants 

  // initialise récursivement un arbre à partir d'un array contenant l'info qui peut par exemple être issu d'un fichier Yaml
  // L'array contient notamment éventuellement un champ 'children' qui est le dictionnaire des enfants
  // NON - Si la class Node est héritée avec d'autres champs alors l'initialisation crée des objets de la classe héritée
  // Le champ $extra permet de passer des paramètres complémentaires dans les appels récursifs
  function __construct(array $array, array $path=[], array $extra=[]) {
    $this->path = $path;
    /*foreach($this as $k => $v) { PAS UNE BONNE IDEE, skos a besoin de redéfinir ses champs
      if (!in_array($k,['path','children']) && isset($array[$k])) {
        $this->$k = $array[$k];
      }
    }*/
    $class = get_class($this);
    foreach ($array['children'] ?? [] as $id => $child) {
      $this->children[$id] = new $class($child ?? [], array_merge($path, [$id]), $extra);
    }
    //ksort($this->children); // JE NE SAIS PAS SI C'EST UNE BONNE IDEE
  }
  
  // Fabrique à partir de l'objet un array qui peut notamment être sérialisé en Yaml ou en JSON
  // Dans un classe héritée, si l'option vaut 'extended' alors inclut les champs ajoutés
  function asArray(string $option=''): array {
    //echo "asArray($option)\n";
    $array = [];
    if ($option == 'extended') {
      foreach($this as $k => $v)
        if (!in_array($k,['path','children']) && $this->$k)
          $array[$k] = $this->$k;
    }
    foreach ($this->children as $id => $child)
      $array['children'][$id] = $child->asArray($option);
    return $array;
  }
  
  function node(array $path): Node { // accès à un noeud par son chemin comme liste de clés par desc. récursive dans l'arbre 
    //print_r($path);
    if (!$path)
      return $this;
    $first = array_shift($path);
    if (!isset($this->children[$first]))
      throw new Exception("Erreur dans Node::node() sur '$first'");
    else
      return $this->children[$first]->node($path);
  }

  function nodes(): array { // retourne le noeud + ses descendants sous la forme [{pathAsString} => Concept] sauf racine
    $nodes = $this->path ? [$this->pathAsString()=> $this] : []; // le concept lui-même est un descendant si <> racine
    foreach ($this->children as $id => $child) // plus les descendants de ses enfants
      $nodes = array_merge($nodes, $child->nodes());
    return $nodes;
  }

  function dump(array $options=[]): string {
    //echo Yaml::dump(['options'=> $options]);
    return Yaml::dump($this->asArray($options['asArrayOption'] ?? ''), $options['level'] ?? 8, $options['indent'] ?? 2);
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


echo '<pre>';

$yaml = <<<EOT
children:
  ssarbre1:
    prefLabels: {fr: sous arbre 1}
    #xx: yy
  ssarbre2:
    prefLabels: {fr: sous arbre 2}
    children:
      ssarbre21:
        prefLabels: {fr: sous arbre 21}
EOT;

if (0) { // Test de la classe Node
  //print_r(Yaml::parse($yaml));
  $tree = new Node(Yaml::parse($yaml));
  //print_r($tree);
  echo $tree->dump(),"\n";

  //print_r($tree->node(['ssarbre2']));

  echo 'nodes()='; print_r($tree->nodes());
  die();
}

class ExtendedNode extends Node { // Test d'une classe héritée
  protected $prefLabels=[];
  //protected $xx;
  
  function __construct(array $array, array $path=[], array $extra=[]) { // init. récursivement un arbre à partir d'un array
    //echo "Concept::__construct()\navec yaml="; print_r($yaml);
    if ($path)
      $this->prefLabels = $array['prefLabels'];
    parent::__construct($array, $path, $extra);
  }
};

if (1) { // Test de la classe ExtendedNode 
  $scheme = new ExtendedNode(Yaml::parse($yaml));
  echo $scheme->dump(['asArrayOption'=>'extended']),"\n";
  print_r($scheme);
}
