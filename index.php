<?php
/*PhpDoc:
title: index.php - visualise et exploite le contenu des catalogues moissonnés et stockés dans PgSql
name: index.php
doc: |
  Je m'intéresse principalement aux MDD du périmètre ministériel, cad les MDD dont un au moins des responsibleParty
  est une DG du pôle ministériel (MTE/MCTRCT/MM) ou un service déconcentré du pôle ou une DTT(M).
  Les mdContacts de ces MDD ne sont parfois pas des organisations du pôle.
journal: |
  27-29/10/2021:
    - réécriture nlle version utilisant PgSql
  18/10/2021:
    - création
includes:
  - cswserver.inc.php
  - cats.inc.php
  - catinpgsql.inc.php
  - arbo.inc.php
  - orginsel.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/orginsel.inc.php';

use Symfony\Component\Yaml\Yaml;

// Liste des arborescences auxquels les mots-clés peuvent appartenir
$arbos = [
  'arboCovadis'=> new Arbo('arbocovadis.yaml'),
  'annexesInspire'=> new Arbo('annexesinspire.yaml'),
];


// Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
function prefLabels(array $keywords, array $arbos): array {
  $prefLabels = []; // liste des prefLabels des mots-clés structuré par arbo, sous forme [arboid => [prefLabel => 1]]
  foreach ($keywords as $keyword) {
    //echo "<pre>"; print_r($keyword); echo "</pre>\n";
    if ($kwValue = $keyword['value'] ?? null) {
      foreach ($arbos as $arboid => $arbo) {
        if ($arbo->labelIn($kwValue)) {
          $prefLabels[$arboid][$arbo->prefLabel($kwValue)] = 1;
        }
      }
    }
  }
  ksort($prefLabels);
  foreach ($prefLabels as $arboid => $labels) {
    $prefLabels[$arboid] = array_keys($labels);
  }
  return $prefLabels;
}

// Un au moins des mots-clés appartient-il à un au moins des thèmes ? Renvoie la liste des clés des thèmes
// Si $options contient 'showKeywords' alors la liste des mots-clés est affichée
function kwInArbos(array $keywords, array $arbos, array $options=[]): array {
  static $keywordValuesShown = []; // liste des mots-clés affichés pour ne les afficher chacun qu'une seule fois

  $inArbos = []; // liste des arbos auquels un des mots-clés appartient, sous forme [arboid => 1]
  foreach ($keywords as $keyword) {
    //echo "<pre>"; print_r($keyword); echo "</pre>\n";
    if ($kwValue = $keyword['value'] ?? null) {
      $in = false; // appartenance de ce mot-clé à une des arbos
      foreach ($arbos as $arboid => $arbo) {
        if ($arbo->labelIn($kwValue)) {
          $in = true;
          $inArbos[$arboid] = 1;
        }
      }
      if (!isset($keywordValuesShown[$kwValue]) && in_array('showKeywords', $options)) { // affichage éventuel 
        echo $in ? '<b>' : '';
        echo "$kwValue -> ", $in ? 'In' : 'Not', "<br>\n";
        echo $in ? '</b>' : '';
        $keywordValuesShown[$kwValue] = 1;
      }
    }
  }
  ksort($inArbos);
  return array_keys($inArbos);
}

// calcule le nbre de mots-clés des MDD du périmètre du catalogue $catid appartenant à une des arbos définis dans $arbos
// retourne une liste constituée:
//  - Pour chaque arbo, nbre de MDD du périmètre dont au moins un mot-clé appartient à l'arbo
//  - Nbre total de MDD du périmètre
// Si $options contient 'showKeywords' alors la liste des mots-clés est affichée
function listkws(string $catid, array $arbos, array $options): array {
  $nbExplique = []; // Pour chaque thème, nbre de MDD du périmètre dont au moins un mot-clé appartient au thème
  $nbTotal = 0; // Nbre total de MDD ayant une organisation dans la sélection
  
  foreach (PgSql::query("select record from catalog$catid where type in ('dataset','series')") as $tuple) {
    $record = json_decode($tuple['record'], true);
    //echo "<pre>"; print_r($record); echo "</pre>\n";
    if (!orgInSel($catid, $record)) // si aucune organisation appartient à la sélection alors on saute
      continue;
    $kwInThemes = kwInArbos($record['keyword'] ?? [], $arbos, $options);
    foreach ($kwInThemes as $theme)
      $nbExplique[$theme] = 1 + ($nbExplique[$theme] ?? 0);
    if ($kwInThemes)
      $nbExplique['tousThemes'] = 1 + ($nbExplique['tousThemes'] ?? 0);
    $nbTotal++;
  }
  ksort($nbExplique);
  return [$nbExplique, $nbTotal];
}

if (!isset($_GET['cat'])) { // choix du catalogue ou actions globales
  if (!isset($_GET['action'])) { // liste des catalogues pour en choisir un
    echo "Catalogues:<ul>\n";
    foreach ($cats as $catid => $cat) {
      echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
    }
    echo "</ul>\n";
    echo "Actions globales:<ul>\n";
    echo "<li><a href='?action=createAggTable'>(Re)Créer une table agrégeant les catalogues</a></li>\n";
    echo "<li><a href='?action=createAggSel'>(Re)Créer un fichier agrégeant les organisations du périmètre</a></li>\n";
    echo "<li><a href='?cat=agg&amp;action=listkws'>Lister les mots-clés du catalogue agrégé</a></li>\n";
    echo "<li><a href='?action=listkws'>Synthèse des taux d'appartenance des mots-clés aux arborescences</a></li>\n";
    echo "<li><a href='?action=croise'>Calcul du nbre de MD communes entre catalogues</a></li>\n";
    echo "<li><a href='?action=diffgeocat'>Affichage des MDD du Géocatalogue du périmètre n'appartenant pas à agg</a></li>\n";
    echo "</ul>\n";
  }
  elseif ($_GET['action']=='createAggTable') { // Créer une table agrégée
    PgSql::query("drop table if exists catalogagg");
    PgSql::query("create table catalogagg(
      cat varchar(256) not null, -- nom du catalogue
      id varchar(256) not null, -- fileIdentifier
      record json, -- enregistrement de la fiche en JSON
      title text, -- 1.1. Intitulé de la ressource
      type varchar(256), -- 1.3. Type de la ressource
      perimetre varchar(256) -- 'Min','Op','Autres' ; null si non défini
    )");

    foreach ($cats as $catid => $cat) {
      if ($cat['dontAgg'] ?? false) continue;
      $sql = "insert into catalogagg(cat, id, record, title, type, perimetre)\n"
            ."  select '$catid', id, record, title, type, perimetre\n"
            ."  from catalog$catid\n"
            ."  where id not in (select id from catalogagg)";
      echo "<pre>$sql</pre>\n";
      PgSql::query($sql);
    }
  }
  elseif ($_GET['action']=='createAggSel') { // Créer une sélection agrégée
    $orgNames = [];
    foreach ($cats as $catid => $cat) {
      if ($cat['dontAgg'] ?? false) continue;
      if (is_file("${catid}Sel.yaml")) {
        foreach (Yaml::parseFile("${catid}Sel.yaml")['orgNames'] as $orgName) {
          if (!in_array($orgName, $orgNames))
            $orgNames[] = $orgName;
        }
      }
    }
    asort($orgNames);
    file_put_contents('aggSel.yaml',
      Yaml::dump([
        'title'=> "liste des noms d'organisations du périmètre ministériel pour Agg (sélection)",
        'orgNames'=> array_values($orgNames),
      ])
    );
    echo "Ok\n";
  }
  elseif ($_GET['action']=='croise') { // Calcul du nbre de MD communes entre catalogues
    echo "<h2>Nombre de métadonnées D+S du périmètre communes entre catalogues</h2>\n";
    echo "<table border=1><th></th>\n";
    foreach ($cats as $catid1 => $cat) {
      echo "<th>",$cat['shortName'] ?? substr($catid1, 0, 6),"</th>";
    }
    echo "\n";
    
    echo "<tr><td>nbTotal</td>";
    foreach (array_keys($cats) as $catid2) {
      $tuple = PgSql::getTuples("select count(*) c from catalog$catid2")[0];
      echo "<td align='right'>$tuple[c]</td>";
    }
    echo "</tr>\n";

    echo "<tr><td>nb in Min</td>";
    foreach (array_keys($cats) as $catid2) {
      $tuple = PgSql::getTuples("select count(*) c from catalog$catid2 where perimetre='Min'")[0];
      echo "<td align='right'>$tuple[c]</td>";
    }
    echo "</tr>\n";

    foreach (array_keys($cats) as $catid1) {
      echo "<tr><td><b>$catid1</b></td>";
      foreach (array_keys($cats) as $catid2) {
        if ($catid2 == $catid1)
          echo "<td align='center'>***</td>\n";
        else {
          $sql =
            "select count(*) c
             from catalog$catid1 c1, catalog$catid2 c2
             where c1.id=c2.id and c1.perimetre='Min' and c2.perimetre='Min'";
          $tuple = PgSql::getTuples($sql)[0];
          if ($tuple['c']==0)
            echo "<td></td>";
          else
            echo "<td align='right'><a href='?cat=$catid1&amp;action=commun&amp;cat2=$catid2'>$tuple[c]</a></td>";
        }
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
  elseif ($_GET['action']=='listkws') { // Synthèse des taux d'appartenance des mots-clés aux arbos
    echo "<pre>\n";
    foreach (array_merge(['agg'], array_keys($cats)) as $catid) {
      list ($nbExplique, $nbTotal) = listkws($catid, $arbos, []);
      echo "$catid:\n";
      foreach ($nbExplique as $theme => $nb)
        printf("  $theme: %d / %d = %.0f %%\n", $nb, $nbTotal, $nb/$nbTotal*100);
    }
  }
  elseif ($_GET['action']=='diffgeocat') { // Affiche les MDD du Géocatalogue du périmètre absentes de agg
    $limit = 5000;
    $sql = "select count(*) nb from cataloggeocatalogue\n"
      ."where type in ('dataset','series')\n"
      ."  and id not in (select id from catalogagg)\n"
      ."  and perimetre='Min'";
    echo PgSql::getTuples($sql)[0]['nb']," tuples<br>\n";
    echo "<ul>";
    $sql = "select id,title from cataloggeocatalogue\n"
      ."where type in ('dataset','series')\n"
      ."  and id not in (select id from catalogagg)\n"
      ."  and perimetre='Min'\n"
      ."order by id\n"
      ."limit $limit offset ".($_GET['offset'] ?? 0);
    foreach (PgSql::query($sql) as $tuple) {
      echo "<li><a href='?cat=geocatalogue&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
    }
    echo "</ul>\n";
    $offset = $limit + ($_GET['offset'] ?? 0);
    echo "<a href='?action=diffgeocat&amp;offset=$offset'>next</a>\n";
  }
  die();
}
  
if (!isset($_GET['action'])) { // menu principal 
  echo "Actions proposées:<ul>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdata'>Toutes les MDD (type dataset ou series)</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdataYaml'>Toutes les MDD (type dataset ou series) en Yaml</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listservices'>Toutes les MD de service</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdataperimetre'>Liste les MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldwkw'>MDD dont au moins un mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldnkw'>MDD dont aucun mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=setPerimetre'>Enregistre le périmetre sur les MD</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=mdContacts'>Liste les mdContacts des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbMdParTheme'>Dénombrement des MD par thème</a></li>\n";
  echo "</ul>\n";
  // Menu
  echo "Affichage d'une fiche à partir de son id<br>\n";
  echo "<form>\n";
  echo "<input type=hidden name='cat' value='$_GET[cat]'>\n";
  echo "id: <input type=text size='80' name='id'>\n";
  echo "<select name='action'>\n";
  echo "  <option value='showPg'>showPg</option>\n";
  echo "  <option value='showIsoXml'>showIsoXml</option>\n";
  echo "  <option value='showDcXml'>showDcXml</option>\n";
  echo "  <option value='showPath'>showPath</option>\n";
  echo "  <option value='extract'>extract</option>\n";
  echo "</select>\n";
  echo "<input type=submit value='go'>\n";
  echo "</form>\n";
  die();
}

if ($_GET['action']=='listdata') { // Toutes les MD de type dataset ou series
  echo "<ul>\n";
  foreach (PgSql::query("select id,title from catalog$_GET[cat] where type in ('dataset','series')") as $record) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die("</ul>\n");
}

if ($_GET['action']=='listdataYaml') { // Toutes les MD de type dataset ou series en Yaml
  echo "<pre>\n";
  foreach (PgSql::query("select record from catalog$_GET[cat] where type in ('dataset','series')") as $tuple) {
    $record = json_decode($tuple['record'], true);
    echo Yaml::dump([$record], 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  }
  die();
}

if ($_GET['action']=='listservices') { // Toutes les MD de type service
  echo "<ul>\n";
  foreach (PgSql::query("select id,title,record from catalog$_GET[cat] where type='service'") as $record) {
    $rec = json_decode($record['record'], true);
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$record[id]'>$record[title]</a> (",
      $rec['serviceType'][0] ?? "NON DEFINI",")</li>\n";
  }
  die();
}

if ($_GET['action']=='showPg') { // affiche une fiche depuis PgSql
  $tuples = PgSql::getTuples("select record from catalog$_GET[cat] where id='$_GET[id]'");
  if (!$tuples) {
    die("Aucun enregistrement pour id='$_GET[id]'<br>\n");
  }
  $record = json_decode($tuples[0]['record'], true);
  //echo "<pre>",json_encode($record, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  echo '<pre>',Yaml::dump($record, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  die();
}

if ($_GET['action']=='showIsoXml') { // affiche une fiche ISO en XML depuis CSW
  $cswServer = new CswServer($cats[$_GET['cat']]['endpointURL'], $_GET['cat']);
  $isoRecord = $cswServer->getRecordById($_GET['id']);
  header('Content-type: text/xml');
  echo $isoRecord;
  die();
}

if ($_GET['action']=='showDcXml') { // affiche une fiche DC en XML depuis CSW
  $cswServer = new CswServer($cats[$_GET['cat']]['endpointURL'], $_GET['cat']);
  $isoRecord = $cswServer->getRecordById($_GET['id'], 'dc', 'full');
  header('Content-type: text/xml');
  echo $isoRecord;
  die();
}

if ($_GET['action']=='showPath') { // affiche les chemins de stockage en buffer
  $cswServer = new CswServer($cats[$_GET['cat']]['endpointURL'], $_GET['cat']);
  echo "ISO: ", $cswServer->getRecordByIdPath($_GET['id']), "<br>\n";
  echo "DC: ", $cswServer->getRecordByIdPath($_GET['id'],'dc','full'), "<br>\n";
  die();
}

if ($_GET['action']=='extract') { // réalise la transformation ISO->JSON sur la fiche
  require_once __DIR__.'/mdvars2.inc.php';
  $cswServer = new CswServer($cats[$_GET['cat']]['endpointURL'], $_GET['cat']);
  $isoRecord = $cswServer->getRecordById($_GET['id']);
  $mdrecord = Mdvars::extract($_GET['id'], $isoRecord);
  echo '<pre>',Yaml::dump($mdrecord);
  die();
}

if ($_GET['action']=='orgsHorsSel') { // liste les organisations hors sélection, permet de vérifier la sélection
  if (!is_file("$_GET[cat]Sel.yaml"))
    $orgNamesSel = [];
  else
    $orgNamesSel = Yaml::parseFile("$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  $orgNames = [];
  foreach (PgSql::query("select record from catalog$_GET[cat]") as $record) {
    $record = json_decode($record['record'], true);
    //echo "<pre>"; print_r($record); die();
    foreach ($record['responsibleParty'] ?? [] as $party) {
      if (isset($party['organisationName']) && !in_array($party['organisationName'], $orgNamesSel))
        $orgNames[$party['organisationName']] = 1;
    }
  }
  ksort($orgNames);
  echo "<pre>\n";
  foreach (array_keys($orgNames) as $orgName) {
    echo "  - $orgName\n";
  }
  die();
}

if ($_GET['action']=='orgs') { // liste des organisations sélectionnés avec lien vers leurs MD
  if (!is_file("$_GET[cat]Sel.yaml"))
    die("Pas de sélection");
  $orgNames = Yaml::parseFile("$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  sort($orgNames);
  echo "<ul>\n";
  foreach ($orgNames as $orgName) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=mdOfOrg&amp;org=",urlencode($orgName),"'>$orgName</li>\n";
  }
  die();
}

if ($_GET['action']=='listdataperimetre') { // Liste les MDD du périmètre 
  echo "<ul>\n";
  foreach (PgSql::query("select id,title from catalog$_GET[cat] where perimetre='Min'") as $tuple) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  }
  echo "</ul>\n";
  die();
}

if ($_GET['action']=='mdOfOrg') { // liste les MD d'une organisation
  echo "<ul>\n";
  foreach (PgSql::query("select id,title,record from catalog$_GET[cat]") as $record) {
    $rec = json_decode($record['record'], true);
    $mdOfOrg = false;
    foreach ($rec['responsibleParty'] ?? [] as $party) {
      if (isset($party['organisationName']) && ($party['organisationName'] == $_GET['org'])) {
        $mdOfOrg = true;
        break;
      }
    }
    if ($mdOfOrg)
      echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die();
}

if ($_GET['action']=='setPerimetre') { // met à jour le périmetre dans la table
  PgSql::query("update catalog$_GET[cat] set perimetre=null");
  foreach (PgSql::query("select id,record from catalog$_GET[cat]") as $record) {
    $id = $record['id'];
    $record = json_decode($record['record'], true);
    if (orgInSel($_GET['cat'], $record)) {
      PgSql::query("update catalog$_GET[cat] set perimetre='Min' where id='$id'");
    }
  }
  die("Ok<br>\n");
}

if ($_GET['action']=='listkws') { // Liste les mots-clés des MDD dont une org est dans la sélection
  list ($nbExplique, $nbTotal) = listkws($_GET['cat'], $arbos, ['showKeywords']);
  echo "--<br>\n";
  foreach ($nbExplique as $theme => $nb)
    printf("$theme: %d / %d = %.0f %%<br>\n", $nb, $nbTotal, $nb/$nbTotal*100);
  die("<br>\n");
}

if (in_array($_GET['action'], ['ldwkw','ldnkw'])) { // MDD dont au moins un / aucun mot-clé correspond à un des thèmes
  foreach (PgSql::query("select id,title,record from catalog$_GET[cat] where type in ('dataset','series')") as $record) {
    $id = $record['id'];
    $title = $record['title'];
    $record = json_decode($record['record'], true);
    if (!orgInSel($_GET['cat'], $record)) // si aucune organisation appartient à la sélection alors on saute
      continue;
    if (kwInArbos($record['keyword'] ?? [], $arbos)) {
      if ($_GET['action'] == 'ldwkw')
        echo "<a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$id'>$title</a><br>\n";
    }
    else {
      if ($_GET['action'] <> 'ldwkw')
        echo "<a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$id'>$title</a><br>\n";
    }
  }
  die();
}

if ($_GET['action']=='commun') { // données communes entre le catalogue $_GET['cat'] et $_GET['cat2']
  echo "<h2>MD communes entre $_GET[cat] et $_GET[cat2]</h2>\n";
  echo "<ul>\n";
  $sql = "select c1.id, c1.title from catalog$_GET[cat] c1, catalog$_GET[cat2] c2
    where c1.id=c2.id and c1.perimetre='Min' and c2.perimetre='Min'";
  echo "<pre>$sql</pre>\n";
  foreach (PgSql::query($sql) as $record) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die();
  
}

if ($_GET['action']=='mdContacts') { // Liste les mdContacts des MDD du périmètre
  echo "<h2>Liste des MDD du périmètre dont le mdContact n'est pas une organisation du périmètre</h2>\n";
  foreach (PgSql::query("select id,title, record from catalog$_GET[cat] where type in ('dataset','series')") as $tuple) {
    $record = json_decode($tuple['record'], true);
    if (!orgInSel($_GET['cat'], $record)) // si aucune organisation appartient à la sélection alors on saute
      continue;
    if (!orgInSel($_GET['cat'], $record, 'mdContact')) {
      echo "<pre>title: <a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a>\n";
      echo Yaml::dump([
        'responsibleParty'=> $record['responsibleParty'],
        'mdContact'=> $record['mdContact'] ?? "NON DEFINI",
      ]), "</pre>\n";
    }
  }
  die();
}

if ($_GET['action']=='nbMdParTheme') { // Dénombrement des MDD par thème
  // A FAIRE
  // faire une carte par thème
  
  $nbMdParTheme = []; // [{arboid} => [{prefLabel} => nb]]
  $nbMd = 0;
  $sql = "select id,title, record from catalog$_GET[cat]
          where type in ('dataset','series') and perimetre='Min'";
  //echo "<pre>";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    if ($prefLabels = prefLabels($record['keyword'] ?? [], $arbos)) {
      //print_r($record['keyword'] ?? []);
      //print_r($prefLabels);
      foreach ($prefLabels as $arboid => $labels) {
        foreach ($labels as $label) {
          if (isset($nbMdParTheme[$arboid][$label]))
            $nbMdParTheme[$arboid][$label]++;
          else
            $nbMdParTheme[$arboid][$label] = 1;
        }
      }
      foreach (array_keys($arbos) as $arboid) {
        if (!isset($prefLabels[$arboid])) {
          if (isset($nbMdParTheme[$arboid]['NON CLASSE']))
            $nbMdParTheme[$arboid]['NON CLASSE']++;
          else
            $nbMdParTheme[$arboid]['NON CLASSE'] = 1;
          //echo "<a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$arboid $tuple[title]<br>\n";
        }
      }
    }
    $nbMd++;
  }

  //echo "<pre>"; print_r($nbMdParTheme); echo "</pre>\n";
  
  echo "<h2>annexesInspire</h2><table border=1>\n";
  foreach ($arbos['annexesInspire']->children('') as $annex) {
    $nbre = $nbMdParTheme['annexesInspire'][$annex->__toString()] ?? 0;
    $url = "?cat=$_GET[cat]&amp;action=mdTheme&amp;arbo=annexesInspire&amp;theme=$annex";
    printf("<tr><td>%s</td><td align='right'><a href='%s'>%d</a></td><td>%.2f %%</td></tr>\n",
      $annex, $url, $nbre, $nbre/$nbMd*100);
  }
  $nbre = $nbMdParTheme['annexesInspire']['NON CLASSE'] ?? 0;
  $url = "?cat=$_GET[cat]&amp;action=mdTheme&amp;arbo=annexesInspire&amp;theme=NON+CLASSE";
  printf("<tr><td>%s</td><td align='right'><a href='%s'>%d</a></td><td>%.2f %%</td></tr>\n",
    'NON CLASSE', $url, $nbre, $nbre/$nbMd*100);
  echo "</table>\n";
  
  echo "<h2>arboCovadis</h2><table border=1>\n";
  foreach ($arbos['arboCovadis']->children('') as $id1 => $theme1) {
    //print_r($label1);
    $nbre = $nbMdParTheme['arboCovadis'][$theme1->__toString()] ?? 0;
    if ($nbre == 0)
      printf("<tr><td>%s</td><td colspan=2></tr>\n", $theme1, $nbre, $nbre/$nbMd*100);
    else
      printf("<tr><td>%s</td><td align='right'>%d</td><td>%.2f %%</td></tr>\n", $theme1, $nbre, $nbre/$nbMd*100);
    foreach ($arbos['arboCovadis']->children($theme1->__toString()) as $id2 => $theme2) {
      $nbre = $nbMdParTheme['arboCovadis'][$theme2->__toString()] ?? 0;
      $url = "?cat=$_GET[cat]&amp;action=mdTheme&amp;arbo=arboCovadis&amp;theme=$theme2";
      if ($nbre == 0)
        printf("<tr><td>-- /%s</td><td colspan=2></td></tr>\n", $id2);
      else
        printf("<tr><td>-- /%s</td><td align='right'><a href='%s'>%d</a></td><td>%.2f %%</td></tr>\n",
          $id2, $url, $nbre, $nbre/$nbMd*100);
    }
  }
  $nbre = $nbMdParTheme['arboCovadis']['NON CLASSE'] ?? 0;
  $url = "?cat=$_GET[cat]&amp;action=mdTheme&amp;arbo=arboCovadis&amp;theme=NON+CLASSE";
  printf("<tr><td>%s</td><td align='right'><a href='%s'>%d</td><td>%.2f %%</td></tr>\n",
    'NON CLASSE', $url, $nbre, $nbre/$nbMd*100);
  echo "</table>\n";
  die();
  
  foreach ($nbMdParTheme as $theme => $nbPerPrefLabel) {
    echo "<h2>$theme</h2><table border=1>\n";
    foreach ($nbPerPrefLabel as $prefLabel => $nbre) {
      printf("<tr><td>%s</td><td>%d </td><td>%.2f %%</td></tr>\n", $prefLabel, $nbre, $nbre/$nbMd*100);
    }
    echo "</table>\n";
  }
  die();
}

if ($_GET['action']=='mdTheme') { // MDD du thème
  echo "<h2>MDD de $_GET[cat] et du theme $_GET[theme] de $_GET[arbo]</h2>\n";
  $sql = "select id,title, record from catalog$_GET[cat]
          where type in ('dataset','series') and perimetre='Min'";
  echo "<ul>\n";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    $prefLabels = prefLabels($record['keyword'] ?? [], [$_GET['arbo']=> $arbos[$_GET['arbo']]]);
    //echo "<pre>id=$tuple[id], prefLabels="; print_r($prefLabels); echo "</pre>\n";
    if ($_GET['theme'] <> 'NON CLASSE') {
      if (in_array($_GET['theme'], $prefLabels[$_GET['arbo']] ?? []))
        echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
    }
    else {
      if (!$prefLabels)
        echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
    }
  }
}
