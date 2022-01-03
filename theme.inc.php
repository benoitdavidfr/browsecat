<?php
/*PhpDoc:
title: theme.inc.php - organisation des thèmes du guichet
name: theme.inc.php
classes:
doc: |
  Définit 2 classes Theme et Taxonomy pour structurer et utiliser les thèmes du guichet ;
  ces 2 classes héritent respectivement de Concept et Scheme définis dans skos.inc.php
  qui implémente la notion de thésaurus Skos défini comme une forêt de Concepts.
  Ces 2 classes implémentent les spécificités des thèmes du guichet par rapport à SKOS.
  Ce fichier remplace le fichier arbo.inc.php, améliorant ainsi la modularisation des fonctionnalités
journal: |
  18-19/12/2021:
    - création
*/
require_once __DIR__.'/skos.inc.php';

use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: Theme
title: class Theme extends Concept
doc: |
  Classe des thèmes organisés comme une forêt dans une Taxonomy
  Etend l'objet Concept avec 3 nouveaux champs:
    - short est une chaine courte identifiant le concept pour un affichage synthétique
    - covadis fait le lien avec l'arborescence Covadis
    - regexps contient une liste de regexps pour tester le titre ou le titre alternatif d'une fiche de MD
      et afficher cette fiche à un thème.
*/
class Theme extends Concept {
  protected ?string $short=null; // étiquette courte pour un affichage synthétique
  protected ?string $covadis; // soit la chaine Covadis, soit null si pas de Covadis, soit 'default' si Covadis par défaut
  protected array $regexps=[]; // liste de regexp
  
  static function covadisFromPath(array $path): string { // déduit l'étiquette Covadis si absente du fichier
    $lkey = $path[count($path)-1];
    if (count($path) == 2)
      $lkey = 'N_'.$lkey;
    return str_replace(['-'], ['_'], strtoupper($lkey));
  }
  
  function __construct(array $array, array $path, array $extra) { // init. récursivement un arbre à partir d'un array
    //echo "Theme::__construct(array, path='/",implode('/',$path),"')\n";
    $this->short = $array['short'] ?? null;;
    $this->regexps = $array['regexps'] ?? [];
    $this->covadis = in_array('covadis', array_keys($array)) ? $array['covadis'] : self::covadisFromPath($path);
    
    // remplit les champs $path et $children + appel récursif
    parent::__construct($array, $path, $extra);
  }
  
  function short(): ?string { return $this->short; }
  function setShort(string $short): void { $this->short = $short; }
  function regexps(): array { return $this->regexps; }
  function covadis(): ?string { return $this->covadis; }

  /*function asArray(string $option=''): array {
    return array_merge(
      $this->short ? ['short'=> $this->short] : [],
      $this->covadis ? ['covadis'=> $this->covadis] : [],
      parent::asArray(),
      $this->regexps ? ['regexps'=> $this->regexps] : [],
    );
  }*/
};

/*PhpDoc: classes
name: Taxonomy
title: class Taxonomy extends Scheme
doc: |
  Classe des Taxonomy ; une taxonomie contient une forêt de thèmes
  Ajoute à Scheme les fonctionnalités suivantes liées à browsecat:
    - à l'initialisation
      - définition du champ short des thèmes
      - ajoute les chemins covadis des thèmes comme altLabels
    - diverses fonctionnalités exploitant des champs de fiches de MD
*/
class Taxonomy extends Scheme {
  protected string $id; // identifiant de la taxonomy
  protected string $title; // titre de la taxonomy
  protected string $update; // date de dernière mise à jour sous la forme 'Y-m-d'
  
  function __construct(string $filename, string $childClass='Theme') { // init. récursivement un arbre à partir d'un array
    $this->update = date('Y-m-d', filemtime($filename));
    $yaml = Yaml::parseFile($filename);
    $this->id = $yaml['$id'];
    $this->title = $yaml['title'];
    parent::__construct($yaml, $childClass);
    $this->addCovadisAsAltLabels();
    $this->setShort();
  }
  
  private function addCovadisAsAltLabels(): void { // ajoute les étiquettes Covadis comme synonymes
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
  
  private function setShort() { // affecte des valeurs short
    $num0 = 0;
    foreach ($this->children as $childid => $child) {
      if ($num0 < 26)
        $short = chr($num0 + ord('A'));
      else
        $short = chr(floor($num0/26) + ord('A')-1).chr($num0%26 +ord('A'));
      //echo "short=$short\n";
      if (!$child->short())
        $child->setShort($short);
      $num0++;
      $num = 1;
      foreach ($child->children() as $subchild) {
        //$sshort = $short.$num++;
        //echo "sshort=$sshort\n";
        if (!$subchild->short())
          $subchild->setShort($short.$num++);
      }
    }
  }

  function noKwIn(array $keywords): bool { // retourne vrai ssi aucun mot-clé ne correspond à un thème
    foreach ($keywords as $keyword) {
      if ($this->labelIn($keyword['value'] ?? ''))
        return false;
    }
    return true;
  }
  
  /*function prefLabelsFromKeywords(array $keywords): array { // retourne les prefLabels correspondant aux keywords
    $prefLabels = [];
    foreach ($keywords as $keyword) {
      if ($prefLabel = $this->prefLabel($keyword['value'] ?? ''))
        if (!in_array($prefLabel, $prefLabels))
          $prefLabels[] = $prefLabel;
    }
    return $prefLabels;
  }*/
  
  function prefLabelsFromThemes(array $themes): array { // retourne les prefLabels correspondant aux themes
    $prefLabels = [];
    foreach ($themes as $theme) {
      if (!in_array($theme['value'], $prefLabels))
        $prefLabels[] = $theme['value'];
    }
    return $prefLabels;
  }
  
  function themePathsFromKeywords(array $keywords): array { // retourne les themes correspondant aux keywords
    $paths = [];
    foreach ($keywords as $keyword) {
      if ($path = $this->labelIn($keyword['value'] ?? '')) {
        if (!in_array($path, $paths))
          $paths[] = $path;
      }
    }
    return $paths;
  }
  
  // Teste les regexps des thèmes sur la liste de strings, renvoit la liste des thèmes pour lesquels le match est positif
  function testRegexps(array $strings): array {
    $matchThemes = [];
    foreach ($this->nodes() as $thid => $theme) {
      //echo $theme->prefLabel(),"<br>\n";
      foreach ($theme->regexps() as $regexp) {
        $regexp = Concept::simplif(Concept::std($regexp));
        foreach ($strings as $string) {
          if (preg_match("!$regexp!i", Concept::simplif(Concept::std($string)))) {
            $matchThemes[$regexp] = $theme;
            break 2;
          }
        }
      }
    }
    return $matchThemes;
  }
  
  // ajoute si possible un thème aux mots-clés en effectuant les tests regexp sur les strings
  // Renvoie vrai ssi au moins un thème a été détecté
  /*function addThemeInKeywords(array &$keywords, array $strings): bool {
    echo "title: $strings[0]<br>\n";
    if (isset($strings[1])) {
      echo "&nbsp;&nbsp;alternative: $strings[1]<br>\n";
    }
    //echo '<pre>',Yaml::dump($record->asArray()),"</pre>\n";
    if ($matchThemes = $this->testRegexps($strings)) {
      foreach ($matchThemes as $regexp => $theme)
        echo '&nbsp;&nbsp;<b>',$theme->prefLabel()," ($regexp)</b><br>\n";
      // ajout du/des themes aux keywords
      $keywords[] = [
        '@id'=> 'https://raw.githubusercontent.com/benoitdavidfr/browsecat/main/themes.yaml#'.$theme->pathAsString(),
        'value'=> $theme->prefLabel(),
        'thesaurusTitle'=> "Thèmes du guichet d'accès à la donnée de la transition écologique et de la cohésion des territoires",
        'thesaurusDate'=> $this->update,
        'thesaurusDateType'=> 'publication',
        'thesaurusId'=> 'https://raw.githubusercontent.com/benoitdavidfr/browsecat/main/themes.yaml',
      ];
    }
    return (bool)$matchThemes;
  }*/
  
  // construit à partir de thèmes des enregistrements au format de la fiche de MD
  function recordFromTheme(Theme $theme, string $source): array {
    return [
      '@id'=> $this->id.'#'.$theme->pathAsString(),
      'value'=> $theme->prefLabel(),
      'thesaurusTitle'=> $this->title,
      'thesaurusDate'=> $this->update,
      'thesaurusDateType'=> 'publication',
      'thesaurusId'=> $this->id,
      'source'=> $source,
    ];
  }

  // déduit si possible un/des thèmes à partir des mots-clés
  function deduceThemesFromKeywords(array $keywords, int $levelMin, bool $verbose): array {
    $listOfThemes = [];
    foreach ($keywords as $keyword) {
      if (($themePath = $this->labelIn($keyword['value'] ?? ''))
        && !in_array($themePath, $listOfThemes)
        && (count($themePath) >= $levelMin))
          $listOfThemes[] = $themePath;
    }
    foreach ($listOfThemes as $i => $themePath) {
      $listOfThemes[$i] = $this->recordFromTheme($this->node($themePath), 'from keywords');
    }
    return $listOfThemes;
  }
  
  // déduit si possible un/des thèmes à partir des mots-clés d'un thésaurus particulier défini par un pattern sur son titre
  function deduceThemesFromThesaurus(array $keywords, string $thesaurusTitlePattern, bool $verbose): array {
    $listOfThemes = [];
    foreach ($keywords as $keyword) {
      //echo "<pre>keyword="; print_r($keyword); echo "</pre>\n";
      if (preg_match($thesaurusTitlePattern, $keyword['thesaurusTitle'] ?? '')) {
        //echo "$thesaurusTitlePattern ok<br>\n";
        if (($themePath = $this->labelIn($keyword['value'] ?? '')) && !in_array($themePath, $listOfThemes))
          $listOfThemes[] = $themePath;
      }
      else {
        //echo "$thesaurusTitlePattern KO<br>\n";
      }
    }
    foreach ($listOfThemes as $i => $themePath) {
      $listOfThemes[$i] = $this->recordFromTheme($this->node($themePath), "from thesaurus $thesaurusTitlePattern");
    }
    return $listOfThemes;
  }
  
  // déduit si possible un/des thèmes en effectuant les tests regexp sur les strings
  // Renvoie la liste de thèmes à ajouter sous la forme d'enregistrements à ajouter à $record['themes']
  function deduceThemesFromTitles(array $strings, bool $verbose): array {
    if ($verbose) {
      echo "title: $strings[0]<br>\n";
      if (isset($strings[1]))
        echo "&nbsp;&nbsp;alternative: $strings[1]<br>\n";
    }
    $listOfThemes = [];
    if ($matchThemes = $this->testRegexps($strings)) {
      foreach ($matchThemes as $regexp => $theme)
        if ($verbose)
          echo '&nbsp;&nbsp;<b>',$theme->prefLabel()," ($regexp)</b><br>\n";
      // ajout du/des themes aux keywords
      $listOfThemes[] = $this->recordFromTheme($theme, 'from titles');
    }
    return $listOfThemes;
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


if (1) {
  //echo '<pre>';
  $themes = new Taxonomy(Yaml::parseFile('themes.yaml'));
  echo '<pre>'; print_r($themes); echo "<pre>\n";
  echo '<pre>',$themes->dump(),"<pre>\n";
}
