<?php
/*PhpDoc:
title: cmde.php - regroupe plusieurs commandes CLI en permettant de les exécuter sur le PgSql local ou distant
name: cmde.php
doc: |
  Certaines commandes étant longues, je les exécute pour la base OVH sur localhost
journal: |
  13/11/2021:
    - modif crauxtabl pour éviter les répétitions d'org dans catorg${cat} et de theme dans cattheme
  11/11/2021:
    - création
includes:
  - cats.inc.php
  - catinpgsql.inc.php
  - orginsel.inc.php
  - arbo.inc.php
*/
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orginsel.inc.php';
require_once __DIR__.'/arbo.inc.php';

if (php_sapi_name()=='cli') {
  function usage(array $argv) { // hors choix du catalogue
    echo "usage: php $argv[0] {cmde} {serveur} {cat}|all\n";
    echo " où {cmde} vaut:\n";
    echo "  - sperim pour actualiser le périmètre sur chaque catalogue à partir du fichier \${catid}Sel.yaml\n";
    echo "  - ajoutheme pour ajouter des thèmes\n";
    echo "  - addarea pour ajouter les champs area, ... et les peupler\n";
    echo "  - crauxtabl pour créer les 2 tables auxilaires par catalogue\n";
    echo "  - cragg pour créer les 3 tables du catalogue agrégé (ne nécessite pas de paramètre {cat})\n";
    echo " où {serveur} vaut local pour la base locale et distant pour la base distante\n";
    echo " où {cat} est le nom du catalogue\n";
    die();
  }

  function usageCat(array $argv, array $cats) { // affiche la liste des catalogues pour en choisir un
    echo "usage: php $argv[0] $argv[1] $argv[2] {cat}|all\n";
    echo " où {cat} vaut:\n";
    foreach ($cats as $catid => $cat)
      echo "  - $catid\n";
    die();
  }

  //echo "argc=$argc\n";
  if (!($cmde = $argv[1] ?? null))
    usage($argv);
  elseif (!in_array($cmde, ['sperim','ajoutheme','addarea','crauxtabl','cragg','none'])) {
    echo "Erreur: paramètre commande $cmde inconnue !\n";
    usage($argv);
  }
  
  if (!CatInPgSql::chooseServer($argv[2] ?? null)) { // Choix du serveur 
    echo "Erreur: paramètre serveur incorrect !\n";
    usage($argv);
  }
  
  $catid = $argv[3] ?? null;
  if ($cmde <> 'cragg') {
    if (!$catid)
      usageCat($argv, $cats);
    elseif ($catid == 'all') { // génère les cmdes pour traiter tous les catalogues
      foreach (array_keys($cats) as $catid) {
        echo "echo php $argv[0] $argv[1] $argv[2] $catid\n";
        echo "php $argv[0] $argv[1] $argv[2] $catid\n";
      }
      die();
    }
    elseif (!in_array($catid, array_merge(array_keys($cats), ['agg']))) {
      echo "Erreur: catalogue $catid inconnu !\n";
      usageCat($argv, $cats);
    }
  }
}
else { // Uniquement en CLI 
  die("Uniquement en CLI\n");
}


if ($cmde == 'sperim') { // actualiser le périmètre sur chaque catalogue à partir du fichier \${catid}Sel.yaml
  PgSql::query("update catalog$catid set perimetre=null");
  foreach (PgSql::query("select id,record from catalog$catid where type in ('dataset','series')") as $tuple) {
    $id = $tuple['id'];
    $record = json_decode($tuple['record'], true);
    if (orgInSel($catid, $record)) {
      PgSql::query("update catalog$catid set perimetre='Min' where id='$id'");
    }
  }

  die("Ok $catid<br>\n");
}

if ($cmde == 'ajoutheme') {
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

  $arboCovadis = new Arbo('arbocovadis.yaml');

  foreach($arboCovadis->nodes() as $theme) {
    foreach ($theme->regexps() as $regexp)
      $matches[Arbo::simplif($regexp)] = ['theme'=> (string)$theme, 'nbre'=> 0];
  }

  $nbMdd = 0;
  $nbAjouts = 0;
  $sql = "select id,title, record from catalog$catid
          where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    if ($prefLabels = prefLabels($record['keyword'] ?? [], ['a'=>$arboCovadis]))
      continue;
    //echo " - $tuple[title]\n";
    $keywords = [];
    foreach ($matches as $label => $match) {
      if (preg_match("!$label!i", Arbo::simplif($tuple['title']))) {
        $keywords[$match['theme']] = 1;
        $matches[$label]['nbre']++;
      }
    }
    if ($keywords) {
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
      $cat = new CatInPgSql($catid);
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

  //echo Yaml::dump($matches);

  //echo Yaml::dump(['$noMatches' => $noMatches]);
  die();
}

if ($cmde == 'addarea') { // ajouter les champs area, ... et les peupler
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
  
  // résolution décamétrique des coordonnées => 4 chiffres après la virgule -> numeric(7,4)
  // résolution décamétrique des surfaces => 4*2 chiffres après la virgule, max = 360 * 180 = 64800 -> numeric(13,8)
  foreach ([
      "alter table catalog$catid
        drop column if exists area, drop column if exists westLon, drop column if exists southLat,
        drop column if exists eastLon, drop column if exists northLat",
      "alter table catalog$catid
        add area numeric(13,8),
        add westLon numeric(7,4),
        add southLat numeric(7,4),
        add eastLon numeric(7,4),
        add northLat numeric(7,4)",
      "drop index if exists catalog${catid}_area_idx",
      "create index catalog${catid}_area_idx ON catalog$catid (area,westLon,southLat,eastLon)",
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
    if (isset($record['dcat:bbox'][0]) && ($area = area($record['dcat:bbox'][0]))) {
      $bbox = $record['dcat:bbox'][0];
      $westLon = floorp(min($bbox['westLon'], $bbox['eastLon']), 4);
      $southLat = floorp(min($bbox['southLat'], $bbox['northLat']), 4);
      $eastLon = ceilp(max($bbox['westLon'], $bbox['eastLon']), 4);
      $northLat = ceilp(max($bbox['southLat'], $bbox['northLat']), 4);
      $sql = "update catalog$catid
              set area=$area, westLon=$westLon, southLat=$southLat, eastLon=$eastLon, northLat=$northLat
              where id='$tuple[id]'";
      try {
        PgSql::query($sql);
      }
      catch (Exception $e) {
        echo '<b>',$e->getMessage()," sur $sql</b>\n\n";
        die();
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
  PgSql::query("create unique index on catorg$catid(id, org)");
  PgSql::query("create index on catorg$catid(org)");

  PgSql::query("drop table if exists cattheme$catid");
  PgSql::query("create table cattheme$catid(
    id varchar(256) not null, -- fileIdentifier de la fiche de données
    theme text not null -- nom du theme
  )");
  PgSql::query("create unique index on cattheme$catid(id, theme)");
  PgSql::query("create index on cattheme$catid(theme)");

  $arboOrgsPMin = new Arbo('orgpmin.yaml');

  $sql = "select id, title, record from catalog$catid where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    //echo "$tuple[title]\n";
    $record = json_decode($tuple['record'], true);

    $prefLabels = prefLabels($record['keyword'] ?? [], [$arbo=> $arbos[$arbo]]);
    //echo "id=$tuple[id], prefLabels="; print_r($prefLabels);

    foreach ($prefLabels[$arbo] ?? [] as $theme) {
      $sql = "insert into cattheme$catid(id, theme) values ('$tuple[id]', '$theme')";
      //echo "$sql\n";
      PgSql::query($sql);
    }
    
    $responsibleParties = []; // liste des parties pour éviter les doublons, [{$orgname}=> 1]
    foreach ($record['responsibleParty'] ?? [] as $party) {
      if (!isset($party['organisationName'])) continue;
      $orgname = $party['organisationName'];
      //echo "  orgname=$orgname\n";
      $orgname = $arboOrgsPMin->prefLabel($orgname);
      if (!$orgname || ($orgname == 'COVADIS')) continue;
      if (isset($responsibleParties[$orgname])) continue;
      $responsibleParties[$orgname] = 1;
      //echo "  stdOrgname=$orgname\n";
      $orgname = str_replace("'", "''", $orgname);
      $sql = "insert into catorg$catid(id, org) values ('$tuple[id]', '$orgname')";
      //echo "  $sql\n";
      PgSql::query($sql);
    }
  }
  echo "Ok $catid<br>\n";
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
    perimetre varchar(256), -- 'Min','Op','Autres' ; null ssi non défini
    area numeric(13,8), -- surface des bbox en degrés carrés, résolution dam2, null ssi non défini ou 0
    westLon numeric(7,4), -- coordonnées, résolution décamétrique
    southLat numeric(7,4),
    eastLon numeric(7,4),
    northLat numeric(7,4)
  )");

  foreach(['org','theme'] as $typtab) {
    PgSql::query("drop table if exists cat${typtab}agg");
    PgSql::query("create table cat${typtab}agg(
      id varchar(256) not null, -- fileIdentifier de la fiche de données
      $typtab text not null -- nom de l'organisation ou du theme
    )");
  }

  foreach ($cats as $catid => $cat) {
    if ($cat['dontAgg'] ?? false) continue;
    $sql = "insert into catalogagg(cat, id, record, title, type, perimetre, area, westLon, southLat, eastLon, northLat)\n"
          ."  select '$catid', id, record, title, type, perimetre, area, westLon, southLat, eastLon, northLat\n"
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
  }

  PgSql::query("create index on catalogagg(area,westLon,southLat,eastLon)");
  PgSql::query("create index on catorgagg(id)");
  PgSql::query("create index on catorgagg(org)");
  PgSql::query("create index on catthemeagg(id)");
  PgSql::query("create index on catthemeagg(theme)");

  echo "Ok\n";
}

if ($cmde == 'none') { // utilisée pour tester le dialogue 
  echo "ok none\n";
}
