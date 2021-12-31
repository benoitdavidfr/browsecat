<?php
/*PhpDoc:
title: skos.inc.php - thésaurus Skos
name: skos.inc.php
classes:
doc: |
  2 classes Concept et Scheme pour gérer un thésaurus Skos
  définies en héritant de la classe Node
journal: |
  18-21/12/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/../phplib/accents.inc.php';
require_once __DIR__.'/node.inc.php';

use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: Concept
title: class Concept extends Node
doc: |
  Classe des concepts organisés comme une forêt dans un Schema de concepts (Scheme).
  Les enfants d'un concept sont les concepts plus spécifiques
*/
class Concept extends Node {
  protected array $prefLabels=[]; // [{lang}=> {label}]
  protected array $altLabels=[]; // liste des synonymes
  protected array $hiddenLabels=[]; // liste des synonymes cachés
  protected ?string $definition=null; // définition du concept
  protected ?string $note=null; // note sur le concept
  
  // standardise les étiquettes en remplaçant les catactères posant problèmes
  static function std(string $label): string { return str_replace(["’","’",'—'],["'","'",'-'], $label); }
  
  // Simplifie les étiquettes comsidérées comme similaires du point de vue synonymie, NE STANDARDISE PAS
  static function simplif(string $label) { return str_replace(['  '],[' '], strtolower(supprimeAccents($label))); }
  
  function __construct(array $array, array $path, array $extra) { // init. récursivement un arbre à partir d'un array
    //echo "Concept::__construct(array, path='/",implode('/',$path),"', extra)\n";
    parent::__construct($array, $path, $extra);
    $scheme = $extra['scheme'];
    if (!isset($array['prefLabels']) && $scheme->keyIsPrefLabel())
      $array['prefLabels'] = ['fr'=> $this->pathAsString()];
    foreach (['prefLabels','altLabels','hiddenLabels'] as $var) {
      foreach ($array[$var] ?? [] as $id => $label) {
        $label = self::std($label);
        $this->$var[$id] = $label;
        $scheme->labels[self::simplif($label)] = $path;
      }
    }
    $this->definition = $array['definition'] ?? null;
    $this->note = $array['note'] ?? null;
    // j'ajoute la clé locale du concept dans les labels
    $scheme->labels[self::simplif($path[count($path)-1])] = $path;
  }
  
  /*function asArray(): array { // PAS NECESSAIRE EN UTILISANT L'OPTION ['extended'] dans asArray()
    $children = [];
    foreach ($this->children as $id => $child)
      $children[$id] = $child->asArray();
    return array_merge(
      //['path'=> $this->path],
      $this->prefLabels ? ['prefLabels'=> $this->prefLabels] : [],
      $this->altLabels ? ['altlabels'=> $this->altLabels] : [],
      $this->hiddenLabels ? ['hiddenLabels'=> $this->hiddenLabels] : [],
      $children ? ['children'=> $children] : [],
    );
  }*/
    
  function asArray(string $option=''): array { return parent::asArray('extended'); }
  function prefLabel(string $lang='fr'): ?string { return $this->prefLabels[$lang] ?? null; }
  function __toString(): string { return $this->prefLabels['fr'] ?? ''; }
  function altLabels(): array { return $this->altLabels; }
  function addAltLabel(string $altLabel): void { $this->altLabels[] = $altLabel; }
  function hiddenLabels(): array { return $this->hiddenLabels; }
  function definition(): ?string { return $this->definition; }
  function note(): ?string { return $this->note; }
};

/*PhpDoc: classes
name: Scheme
title: class Scheme extends Node
doc: |
  Classe des Schemas de concepts contenant des concepts
*/
class Scheme extends Node {
  protected $keyIsPrefLabel=false; // vrai ssi le prefLabel fr est le chemin du concept
  public array $labels=[]; // liste des synonymes permettant d'accéder aux concepts, [{label}=> {path}]
  
  function __construct(array $array, string $childClass='Concept') { // init. récursivement un arbre à partir d'un array
    // Le paramètre $childClass permet de réutiliser cette méthode dans une classe héritée
    //echo "Scheme::__construct($childClass, array)\n";
    if ($array['keyIsPrefLabel'] ?? false)
      $this->keyIsPrefLabel = true;
    foreach ($array['children'] as $id => $child) {
      // la référence au présent objet sera disponible dans les appels récursifs dans Concept::__construct() dans $extra
      $this->children[$id] = new $childClass($child ?? [], [$id], ['scheme'=> $this]);
    }
    ksort($this->labels);
  }

  function keyIsPrefLabel(): bool { return $this->keyIsPrefLabel; }
  //function asArray(string $option=''): array { return parent::asArray('extended'); }

  function labelIn(string $label): array { // Si le label est défini alors renvoie son path, sinon []
    return $this->labels[Concept::simplif(Concept::std($label))] ?? [];
  }
  
  function prefLabel(string $label, string $lang='fr'): ?string { // retrouve le prefLabel à partir d'un label
    if ($path = $this->labelIn($label))
      return $this->node($path)->prefLabel($lang);
    else
      return null;
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


echo "<pre>";

$yaml = <<<EOT
title: exemple de thésaurus
children:
  risques:
    prefLabels: {fr: Risques}
    children:
      pprn:
        prefLabels: {fr: Plan de Prévention de Risques Naturels}
        altLabels:
          - PPRI
      zonages-risque-technologique:
EOT;

if (1) {
  $scheme = new Scheme(Yaml::parse($yaml));
  //print_r($scheme);
  echo $scheme->dump();
  die();
}
elseif (1) {
  $arboCovadis = new Scheme(Yaml::parseFile('arbocovadis.yaml'));
  $annexesInspire = new Scheme(Yaml::parseFile('annexesinspire.yaml'));
  $geozones = new Scheme(Yaml::parseFile('geozones.yaml'));
  echo '<pre>',$geozones->dump(),"</pre>\n";
  die();
}

class Concept2 extends Concept {
};

class Scheme2 extends Scheme {
};

if (0) { // Vérification que cela marche avec des classes dérivées
  $scheme = new Scheme2('Concept2', Yaml::parse($yaml));
  print_r($scheme);
  echo $scheme->dump();
  die();
}

if (1) {
  $yaml = <<<EOT
children:
  donnees-generiques:
    covadis: DONNEE_GENERIQUE
    note: données de référence non métier et données d'habillage
    prefLabels:
      fr: Données génériques
    children:
      demographie:
        definition: Données démographiques, recensement
        prefLabels:
          fr: Démographie
        altLabels:
          - Donnée générique/Démographie
          - Donnée générique / Démographie
          - population
          - Population communale
          - Répartition de la population - démographie
EOT;
  $scheme = new Scheme('Concept', Yaml::parse($yaml));
  foreach (["Répartition de la population - démographie","Répartition de la population — démographie"] as $label) {
    echo "$label -> "; print_r($scheme->labelIn($label));
  }
}
