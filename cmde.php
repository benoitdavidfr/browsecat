<?php
/*PhpDoc:
title: cmde.php - regroupe plusieurs commandes CLI en permettant de les exécuter sur le PgSql local ou distant
name: cmde.php
doc: |
  Certaines commandes étant longues, je les exécute pour la base OVH sur localhost
journal: |
  16/11/2021:
    - chgt de mécanisme pour addarea avec une table catbbox et renomage de la commande en addbbox
  15/11/2021:
    - ajout dans crauxtabl / cattheme$catid des NON CLASSE
    - ajout d'un export en yaml d'un catalogue
  14/11/2021:
    - modif sperim et crauxtabl pour utiliser OrgArbo à la place de Arbo
    - ajout possibilités cmde=all et d'une liste de cmdes
  13/11/2021:
    - modif crauxtabl pour éviter les répétitions d'org dans catorg${cat} et de theme dans cattheme
  11/11/2021:
    - création
includes:
  - cats.inc.php
  - catinpgsql.inc.php
  - orginsel.inc.php
  - orgarbo.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orginsel.inc.php';
require_once __DIR__.'/orgarbo.inc.php';

use Symfony\Component\Yaml\Yaml;


if (php_sapi_name()=='cli') {
  function usage(array $argv) { // hors choix du catalogue
    echo "usage: php $argv[0] {cmde} {serveur} {cat}|all\n";
    echo " où {cmde} vaut:\n";
    echo "  - export pour exporter en Yaml les fiches de MDD du catalogue\n";
    echo "  - sperim pour actualiser le périmètre sur chaque catalogue à partir du fichier \${catid}Sel.yaml\n";
    echo "  - ajoutheme pour ajouter des thèmes\n";
    echo "  - addbbox pour ajouter la table catbbox par catalogue et la peupler\n";
    echo "  - crauxtabl pour créer les 2 tables auxilaires par catalogue\n";
    echo "  - cragg pour créer les 3 tables du catalogue agrégé (ne nécessite pas de paramètre {cat})\n";
    echo "  - all pour éxécuter ttes les commandes sauf export\n";
    echo "  - une liste de noms de commande séparés par ',' pour éxécuter cette liste\n";
    echo " où {serveur} vaut local pour la base locale et distant pour la base distante\n";
    echo " où {cat} est le nom du catalogue ou all pour tous les catalogues sauf le Géocatalogue\n";
    die();
  }

  function usageCat(array $argv, array $cats) { // affiche la liste des catalogues pour en choisir un
    echo "usage: php $argv[0] $argv[1] $argv[2] {cat}|all\n";
    echo " où {cat} vaut:\n";
    foreach ($cats as $catid => $cat)
      echo "  - $catid\n";
    die();
  }

  function multicmdes(array $cmdes, array $argv, string $catid, array $cats) { // génère les ordes pour plusieurs cmdes
    unset($cats['geocatalogue']);
    $listcats = ($catid == 'all') ? array_keys($cats) : [$catid];
    foreach ($listcats as $catid2) {
      foreach ($cmdes as $cmde) {
        if ($cmde == 'cragg') continue;
        echo "echo php $argv[0] $cmde $argv[2] $catid2\n";
        echo "php $argv[0] $cmde $argv[2] $catid2\n";
      }
    }
    echo "echo php $argv[0] cragg $argv[2]\n";
    echo "php $argv[0] cragg $argv[2]\n";
    die();
  }
  
  //echo "argc=$argc\n";
  $catid = $argv[3] ?? '';
  if (!($cmde = $argv[1] ?? null))
    usage($argv);
  elseif ($cmde == 'all') { // exécute ttes les commandes
    multicmdes(['sperim','ajoutheme','addbbox','crauxtabl','cragg'], $argv, $catid, $cats);
  }
  elseif (strpos($cmde, ',') !== false) {
    multicmdes(explode(',', $cmde), $argv, $catid, $cats);
  }
  elseif (!in_array($cmde, ['sperim','ajoutheme','addbbox','crauxtabl','cragg','export','none'])) {
    echo "Erreur: paramètre commande $cmde inconnue !\n";
    usage($argv);
  }
  
  if (!CatInPgSql::chooseServer($argv[2] ?? null)) { // Choix du serveur 
    echo "Erreur: paramètre serveur incorrect !\n";
    usage($argv);
  }
  
  if ($cmde <> 'cragg') {
    if (!$catid)
      usageCat($argv, $cats);
    elseif ($catid == 'all') { // génère les cmdes pour traiter tous les catalogues
      unset($cats['geocatalogue']);
      foreach (array_keys($cats) as $catid) {
        echo "echo php $argv[0] $argv[1] $argv[2] $catid\n";
        echo "php $argv[0] $argv[1] $argv[2] $catid\n";
      }
      die();
    }
    elseif (!in_array($catid, array_merge(array_keys($cats), ['agg','none']))) {
      echo "Erreur: catalogue $catid inconnu !\n";
      usageCat($argv, $cats);
    }
  }

  if ($catid == 'none') {
    die("ok cat none\n");
  }
}
else { // Uniquement en CLI 
  die("Uniquement en CLI\n");
}


$arboOrgsPMin = new OrgArbo('orgpmin.yaml');

if ($cmde == 'export') { // export d'un catalogue dans un fichier Yaml
  // Le champ title est modifié pour faciliter la visualisation dans TextMate
  // De même le formattage de chaque fiche est modifiée
  echo "title: 'Export du catalogue $catid'\n";
  echo "records:\n";
  foreach (PgSql::query("select record from catalog$catid where type in ('dataset','series')") as $tuple) {
    $record = json_decode($tuple['record'], true);
    $title = $record['dct:title'][0];
    unset($record['dct:title']);
    $record = array_merge(['dct:title'=> $title], $record);
    echo str_replace("-\n  ","- ",Yaml::dump([$record], 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
  }
  die();
}

if ($cmde == 'testexport') { // Test de relecture du fichier Yaml généré
  $yaml = Yaml::parsefile("$catid.yaml");
  echo Yaml::dump($yaml, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  die();
}

if ($cmde == 'sperim') { // actualiser le périmètre sur chaque catalogue à partir du fichier \${catid}Sel.yaml
  PgSql::query("update catalog$catid set perimetre=null");
  foreach (PgSql::query("select id,record from catalog$catid where type in ('dataset','series')") as $tuple) {
    $id = $tuple['id'];
    $record = json_decode($tuple['record'], true);
    if ($arboOrgsPMin->recordIn($record)) {
      PgSql::query("update catalog$catid set perimetre='Min' where id='$id'");
    }
  }

  die("Ok $catid<br>\n");
}

if ($cmde == 'ajoutheme') { // pour ajouter des thèmes
  // Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
  function prefLabels(array $keywords, array $arbos): array {
    $prefLabels = []; // liste des prefLabels des mots-clés structuré par arbo, sous forme [arboid => [prefLabel => 1]]
    foreach ($keywords as $keyword) {
      //echo "<pre>"; print_r($keyword); echo "</pre>\n";
      if ($kwValue = $keyword['value'] ?? null) {
        foreach ($arbos as $arboid => $arbo) {
          if ($prefLabel = $arbo->prefLabel($kwValue)) {
            $prefLabels[$arboid][$prefLabel] = 1;
          }
        }
      }
    }
    ksort($prefLabels);
    foreach ($prefLabels as $arboid => $labels) {
      $prefLabels[$arboid] = array_keys($labels);
    }
    //echo "<pre>prefLabels(",Yaml::dump($keywords),") -> ",Yaml::dump($prefLabels),"</pre>";
    return $prefLabels;
  }

  $cat = new CatInPgSql($catid);
  $arboCovadis = new Arbo('arbocovadis.yaml');

  $regexps = []; // liste des regexps - [{regexp} => ['theme'=> {theme}, 'nbre'=> {nbre}]]
  foreach($arboCovadis->nodes() as $theme) {
    foreach ($theme->regexps() as $regexp)
      $regexps[Arbo::simplif($regexp)] = ['theme'=> (string)$theme, 'nbre'=> 0];
  }

  $nbMdd = 0;
  $nbAjouts = 0;
  $sql = "select id,title, record from catalog$catid
          where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    // Supprime les keyword précédemmnt ajoutés
    $keywordsDeleted = false;
    foreach ($record['keyword'] ?? [] as $i => $keyword) {
      if ('http://localhost/browsecat/ajouttheme.php/arbocovadis' == ($keyword['thesaurusId'] ?? '')) {
        unset($record['keyword'][$i]);
        $keywordsDeleted = true;
      }
    }
    if ($record['keyword'] ?? [])
      $record['keyword'] = array_values($record['keyword']);
    if ($prefLabels = prefLabels($record['keyword'] ?? [], ['a'=>$arboCovadis])) {
      if ($keywordsDeleted)
        $cat->updateRecord($tuple['id'], $record);
      continue;
    }
    //echo " - $tuple[title]\n";
    $keywords = []; // mots-clés à ajouter [{label}=> 1]
    foreach ($regexps as $regexp => $match) {
      if (preg_match("!$regexp!i", Arbo::simplif($tuple['title']))) {
        $keywords[$match['theme']] = 1;
        $regexps[$regexp]['nbre']++;
      }
    }
    if ($keywords || $keywordsDeleted) {
      //echo "   + ",implode(', ', array_keys($keywords)),"\n";
      foreach (array_keys($keywords) as $kw)
        $record['keyword'][] = [
          'value'=> $kw,
          'thesaurusTitle'=>"ajouttheme.php/arbocovadis",
          'thesaurusDate'=> date('Y-m-d'),
          'thesaurusDateType'=> 'publication',
          'thesaurusId'=> 'http://localhost/browsecat/ajouttheme.php/arbocovadis',
        ];
      //print_r($record);
      $cat->updateRecord($tuple['id'], $record);
      $nbAjouts++;
    }
    else {
      $noMatches[] = $tuple['title'];
    }
    $nbMdd++;
  }

  if ($nbMdd)
    printf("%s : %d ajouts / %d mdd soit %.0f %%\n", $catid, $nbAjouts, $nbMdd, $nbAjouts/$nbMdd*100);
  else
    echo "Aucune MDD concernées pour $catid\n";

  //echo Yaml::dump(['$regexps'=> $regexps]);
  foreach ($regexps as $regexp => $match) {
    if ($match['nbre'])
      echo Yaml::dump([$regexp => $match]);
  }

  //echo Yaml::dump(['$noMatches' => $noMatches]);
  die();
}

if ($cmde == 'addbbox') { // ajout de la table catbbox
  function floorp(float $num, int $precision = 0): float {
    if (!$precision)
      return floor($num);
    else
      return floor($num * 10**$precision) / 10**$precision;
  }

  function ceilp(float $num, int $precision = 0): float {
    if (!$precision)
      return ceil($num);
    else
      return ceil($num * 10**$precision) / 10**$precision;
  }

  //foreach ([5.12345,-5.12345,2/3] as $num) { echo "$num -> ",floorp($num, 2)," / ",ceilp($num, 2),"\n"; } die();

  function area(array $bbox): ?float {
    if (!isset($bbox['eastLon']) || !is_numeric($bbox['eastLon']) || ($bbox['eastLon'] < -180) || ($bbox['eastLon'] > 180))
      return null;
    if (!isset($bbox['westLon']) || !is_numeric($bbox['westLon']) || ($bbox['westLon'] < -180) || ($bbox['westLon'] > 180))
      return null;
    if (!isset($bbox['northLat']) || !is_numeric($bbox['northLat']) || ($bbox['northLat'] < -90) || ($bbox['northLat'] > 90))
      return null;
    if (!isset($bbox['southLat']) || !is_numeric($bbox['southLat']) || ($bbox['southLat'] < -90) || ($bbox['southLat'] > 90))
      return null;
    return abs(($bbox['eastLon'] - $bbox['westLon']) * ($bbox['northLat'] - $bbox['southLat']));
  }
  
  if (0)
  foreach (array_keys($cats) as $catid) { // suppression des anciennes structures
    foreach ([
        "alter table catalog$catid
          drop column if exists area, drop column if exists westLon, drop column if exists southLat,
          drop column if exists eastLon, drop column if exists northLat",
        "drop index if exists catalog${catid}_area_idx",
      ] as $sql) {
        try {
          PgSql::query($sql);
        }
        catch (Exception $e) {
          echo '<b>',$e->getMessage()," sur $sql</b>\n\n";
          die();
        }
      }
  }
  
  // résolution décamétrique des coordonnées => 4 chiffres après la virgule -> numeric(7,4)
  // résolution décamétrique des surfaces => 4*2 chiffres après la virgule, max = 360 * 180 = 64800 -> numeric(13,8)
  foreach ([
      "drop table if exists catbbox$catid",
      "create table catbbox$catid(
        id varchar(256) not null, -- fileIdentifier
        nobbox int not null, -- le no de bbox à partir de 0
        area numeric(13,8),
        westLon numeric(7,4),
        southLat numeric(7,4),
        eastLon numeric(7,4),
        northLat numeric(7,4)
      )",
      "create unique index on catbbox$catid(id, nobbox)",
      "create index catbbox${catid}_area_idx ON catbbox$catid (area desc,westLon,southLat,eastLon)",
    ] as $sql) {
      try {
        PgSql::query($sql);
      }
      catch (Exception $e) {
        echo '<b>',$e->getMessage()," sur $sql</b>\n\n";
        die();
      }
  }

  $sql = "select id, title, record from catalog$catid where type in ('dataset','series')";
  foreach (PgSql::query($sql) as $tuple) {
    //echo "$tuple[title]\n";
    $record = json_decode($tuple['record'], true);
    //print_r($record['dcat:bbox']);
    foreach ($record['dcat:bbox'] ?? [] as $nobbox => $bbox) {
      if ($area = area($bbox)) {
        $westLon = floorp(min($bbox['westLon'], $bbox['eastLon']), 4);
        $southLat = floorp(min($bbox['southLat'], $bbox['northLat']), 4);
        $eastLon = ceilp(max($bbox['westLon'], $bbox['eastLon']), 4);
        $northLat = ceilp(max($bbox['southLat'], $bbox['northLat']), 4);
        /*$sql = "update catalog$catid
                set area=$area, westLon=$westLon, southLat=$southLat, eastLon=$eastLon, northLat=$northLat
                where id='$tuple[id]'";*/
        $sql = "insert into catbbox$catid(id, nobbox, area, westLon, southLat, eastLon, northLat)
                values ('$tuple[id]', $nobbox, $area, $westLon, $southLat, $eastLon, $northLat)";
        try {
          PgSql::query($sql);
        }
        catch (Exception $e) {
          echo '<b>',$e->getMessage()," sur $sql</b>\n\n";
          die();
        }
      }
    }
  }
  die("Ok $catid\n");
}

if ($cmde == 'crauxtabl') { // créer les 2 tables auxilaires par catalogue

  $arbo = 'arboCovadis'; // arborescence utilisée

  // Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
  function prefLabels(array $keywords, array $arbos): array {
    $prefLabels = []; // liste des prefLabels des mots-clés structuré par arbo, sous forme [arboid => [prefLabel => 1]]
    foreach ($keywords as $keyword) {
      //echo "<pre>"; print_r($keyword); echo "</pre>\n";
      if ($kwValue = $keyword['value'] ?? null) {
        foreach ($arbos as $arboid => $arbo) {
          if ($prefLabel = $arbo->prefLabel($kwValue)) {
            $prefLabels[$arboid][$prefLabel] = 1;
          }
        }
      }
    }
    ksort($prefLabels);
    foreach ($prefLabels as $arboid => $labels) {
      $prefLabels[$arboid] = array_keys($labels);
    }
    //echo "<pre>prefLabels(",Yaml::dump($keywords),") -> ",Yaml::dump($prefLabels),"</pre>";
    return $prefLabels;
  }

  // Liste des arborescences auxquels les mots-clés peuvent appartenir
  $arbos = [
    'arboCovadis'=> new Arbo('arbocovadis.yaml'),
    'annexesInspire'=> new Arbo('annexesinspire.yaml'),
  ];

  PgSql::query("drop table if exists catorg$catid");
  PgSql::query("create table catorg$catid(
    id varchar(256) not null, -- fileIdentifier de la fiche de données
    org text not null -- nom de l'organisation
  )");

  PgSql::query("drop table if exists cattheme$catid");
  PgSql::query("create table cattheme$catid(
    id varchar(256) not null, -- fileIdentifier de la fiche de données
    theme text not null -- nom du theme
  )");

  $sql = "select id, title, record from catalog$catid where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    //echo "$tuple[title]\n";
    $record = json_decode($tuple['record'], true);

    $prefLabels = prefLabels($record['keyword'] ?? [], [$arbo=> $arbos[$arbo]]);
    //echo "id=$tuple[id], prefLabels="; print_r($prefLabels);

    if (isset($prefLabels[$arbo])) {
      foreach ($prefLabels[$arbo] as $theme) {
        $sql = "insert into cattheme$catid(id, theme) values ('$tuple[id]', '$theme')";
        //echo "$sql\n";
        PgSql::query($sql);
      }
    }
    else { // cas où aucun thème n'est associé
      $sql = "insert into cattheme$catid(id, theme) values ('$tuple[id]', 'NON CLASSE')";
      //echo "$sql\n";
      PgSql::query($sql);
    }
    
    $responsibleParties = []; // liste des parties pour éviter les doublons, [{$orgname}=> 1]
    foreach ($record['responsibleParty'] ?? [] as $party) {
      if (!($plabel = $arboOrgsPMin->prefLabel($party))) continue;
      if (isset($responsibleParties[$plabel])) continue;
      $responsibleParties[$plabel] = 1;
      //echo "  stdOrgname=$orgname\n";
      $plabel = str_replace("'", "''", $plabel);
      $sql = "insert into catorg$catid(id, org) values ('$tuple[id]', '$plabel')";
      //echo "  $sql\n";
      PgSql::query($sql);
    }
  }

  PgSql::query("create unique index on catorg$catid(id, org)");
  PgSql::query("create index on catorg$catid(org)");
  PgSql::query("create unique index on cattheme$catid(id, theme)");
  PgSql::query("create index on cattheme$catid(theme)");

  echo "Ok $catid\n";
}

if ($cmde == 'cragg') { // créer les 3 tables du catalogue agrégé
  // Créer une table agrégée
  PgSql::query("drop table if exists catalogagg");
  PgSql::query("create table catalogagg(
    id varchar(256) not null primary key, -- fileIdentifier
    cat varchar(256) not null, -- nom du catalogue
    record json, -- enregistrement de la fiche en JSON
    title text, -- 1.1. Intitulé de la ressource
    type varchar(256), -- 1.3. Type de la ressource
    perimetre varchar(256) -- 'Min','Op','Autres' ; null ssi non défini
  )");

  foreach(['org','theme'] as $typtab) {
    PgSql::query("drop table if exists cat${typtab}agg");
    PgSql::query("create table cat${typtab}agg(
      id varchar(256) not null, -- fileIdentifier de la fiche de données
      $typtab text not null -- nom de l'organisation ou du theme
    )");
  }

  PgSql::query("drop table if exists catbboxagg");
  PgSql::query("create table catbboxagg(
    id varchar(256) not null, -- fileIdentifier
    nobbox int not null, -- le no de bbox à partir de 0
    area numeric(13,8), -- surface des bbox en degrés carrés, résolution dam2, null ssi non défini ou 0
    westLon numeric(7,4), -- coordonnées, résolution décamétrique
    southLat numeric(7,4),
    eastLon numeric(7,4),
    northLat numeric(7,4)
  )");

  foreach ($cats as $catid => $cat) {
    if ($cat['dontAgg'] ?? false) continue;
    $sql = "insert into catalogagg(cat, id, record, title, type, perimetre)\n"
          ."  select '$catid', id, record, title, type, perimetre\n"
          ."  from catalog$catid\n"
          ."  where id not in (select id from catalogagg)";
    echo "$sql\n";
    PgSql::query($sql);

    foreach(['org','theme'] as $typtab) {
      $sql = "insert into cat${typtab}agg(id, ${typtab})\n"
            ."  select id, ${typtab} from cat${typtab}${catid} where id not in (select id from cat${typtab}agg)";
      echo "$sql\n";
      PgSql::query($sql);
    }
    
    $sql = "insert into catbboxagg(id, nobbox, area, westLon, southLat, eastLon, northLat)\n"
          ."  select id, nobbox, area, westLon, southLat, eastLon, northLat\n"
          ."  from catbbox$catid\n"
          ."  where id not in (select id from catbboxagg)";
    echo "$sql\n";
    PgSql::query($sql);
  }

  PgSql::query("create index on catorgagg(id)");
  PgSql::query("create index on catorgagg(org)");
  PgSql::query("create index on catthemeagg(id)");
  PgSql::query("create index on catthemeagg(theme)");
  PgSql::query("create unique index on catbboxagg(id, nobbox)");
  PgSql::query("create index on catbboxagg(area desc,westLon,southLat,eastLon)");

  echo "Ok\n";
}

if ($cmde == 'none') { // utilisée pour tester le dialogue 
  echo "ok none\n";
}
