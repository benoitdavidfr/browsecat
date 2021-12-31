<?php
/*PhpDoc:
name: node.inc.php
title: node.inc.php - dictionnaire généralisé par un arbre
classes:
doc: |
  Gestion d'un structure généralisant le concept de dictionnaire par des clés définies par une liste de chaines de car. ;
  la valeur est un objet Node qui un noeud de l'arbre ainsi défini et contient des enfants dans un champ children.
  L'arbre peut aussi contenir des classes héréitées de Node.
  Cette classe apporte principalment les fonctionnalités suivantes:
    - initialisation de l'arbre à partir d'un array contenant d'un part un champ children contenant un dict. d'enfants
      et d'autre part des champs supplémentaire qui seront portés par le noeud.
    - accès à un noeud quelconque de l'arbre à partir de la racine de cet arbre par le chemin des clés des noeuds.
    - linéarisation de l'arbre en doigts de gants pour faciliter son parcours dans une boucle foreach ; les clés sont
      transformées en chaines de caractères dans lesquelles les éléments sont séparés par un '/'
    - affichage de l'arbre sous la forme d'un sortie Yaml

  Il s'agit d'une réécriture de tree.inc.php pour
    - fusionner les 2 classes Node et Tree en une seule classe Node
    - permettre dans une classe héritée de constuire un arbre d'objets de la classe héritée

  Pour définir des champs au noeud, définir une classe héritant de la classe Node tout en bénéficiant des fonctionnalités
  définies pour Node.
  Exemple les classes Skos qui implémente un arbre définisant un Scheme et des Concepts SKOS simples.
journal: |
  18-19/12/2021:
    - création par fork de tree.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class Node { // Un objet est un noeud de l'arbre qui peut ainsi être construit
  protected array $path=[]; // chemin d'accès dans l'arbre comme liste de clés, chaque clé est string ne contenant pas '/'
  protected array $children=[]; // dict. des enfants, chacun comme Node ou objet d'une sous-classe + un clé l'identifiant

  function path(): array { return $this->path; }
  function pathAsString(): string { return '/'.implode('/', $this->path); }
  function children(): array { return $this->children; } // enfants 

  // initialise récursivement un arbre à partir d'un array contenant l'info qui peut par exemple être issu d'un fichier Yaml
  // L'array contient notamment éventuellement un champ 'children' qui est le dictionnaire des enfants
  // Le paramètre $extra permet de passer des paramètres complémentaires dans les appels récursifs
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

  function nodes(): array { // retourne le noeud + ses descendants sauf racine sous la forme [{pathAsString} => Node]
    $nodes = $this->path ? [$this->pathAsString()=> $this] : []; // le concept lui-même est un descendant si <> racine
    foreach ($this->children as $id => $child) // plus les descendants de ses enfants
      $nodes = array_merge($nodes, $child->nodes());
    return $nodes;
  }

  function dump(array $options=[]): string { // effectue un affichage Yaml avec les paramètres level et indent 
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
