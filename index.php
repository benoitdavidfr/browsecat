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
  - annexes.inc.php
  - orginsel.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/annexes.inc.php';
require_once __DIR__.'/orginsel.inc.php';

use Symfony\Component\Yaml\Yaml;

// Liste des thèmes auxquels les mots-clés peuvent appartenir
$themes = [
  'arboCovadis'=> new Arbo('arbocovadis.yaml'),
  'annexesInspire'=> new Annexes('annexesinspire.yaml'),
];


// Un au moins des mots-clés appartient-il à un au moins des thèmes ? Renvoie la liste des clés des thèmes
// Si $options contient 'showKeywords' alors la liste des mots-clés est affichée
function kwInThemes(array $keywords, array $themes, array $options=[]): array {
  static $keywordValuesShown = []; // liste des mots-clés affichés pour ne les afficher chacun qu'une seule fois

  $inThemes = []; // liste des themes auquel un des mots-clés appartient, sous forme [themeid => 1]
  foreach ($keywords as $keyword) {
    //echo "<pre>"; print_r($keyword); echo "</pre>\n";
    if ($kwValue = $keyword['value'] ?? null) {
      $in = false; // appartenance de ce mot-clé à un des thèmes
      foreach ($themes as $themeid => $theme) {
        if ($theme->labelIn($kwValue)) {
          $in = true;
          $inThemes[$themeid] = 1;
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
  ksort($inThemes);
  return array_keys($inThemes);
}

// calcule le nbre de mots-clés des MDD du périmètre du catalogue $catid appartenant à un des thèmes définis dans $themes
// retourne une liste constituée:
//  - Pour chaque thème, nbre de MDD du périmètre dont au moins un mot-clé appartient au thème
//  - Nbre total de MDD du périmètre
// Si $options contient 'showKeywords' alors la liste des mots-clés est affichée
function listkws(string $catid, array $themes, array $options): array {
  $nbExplique = []; // Pour chaque thème, nbre de MDD du périmètre dont au moins un mot-clé appartient au thème
  $nbTotal = 0; // Nbre total de MDD ayant une organisation dans la sélection
  
  foreach (PgSql::query("select record from catalog$catid where type in ('dataset','series')") as $tuple) {
    $record = json_decode($tuple['record'], true);
    //echo "<pre>"; print_r($record); echo "</pre>\n";
    if (!orgInSel($catid, $record)) // si aucune organisation appartient à la sélection alors on saute
      continue;
    $kwInThemes = kwInThemes($record['keyword'] ?? [], $themes, $options);
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
    echo "<li><a href='?action=createAggTable'>Créer une table agrégée</a></li>\n";
    echo "<li><a href='?action=createAggSel'>Créer une sélection agrégée</a></li>\n";
    echo "<li><a href='?cat=agg&amp;action=listkws'>Liste des mots-clés du catalogue agrégé</a></li>\n";
    echo "<li><a href='?action=listkws'>Synthèse des taux d'appartenance des mots-clés aux thèmes</a></li>\n";
    echo "<li><a href='?action=croise'>Calcul du nbre de MD communes entre catalogues</a></li>\n";
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

    foreach (array_keys($cats) as $i => $catid) {
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
    foreach (array_keys($cats) as $i => $catid) {
      foreach (Yaml::parseFile("${catid}Sel.yaml")['orgNames'] as $orgName) {
        if (!in_array($orgName, $orgNames))
          $orgNames[] = $orgName;
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
    echo "<h2>Nombre de métadonnées D+S communes entre catalogues</h2>\n";
    echo "<table border=1><th></th>\n";
    foreach (array_keys($cats) as $catid2) {
      echo "<th>",substr($catid2, 0, 6),"</th>";
    }
    echo "\n";
    
    echo "<tr><td>count</td>";
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
  elseif ($_GET['action']=='listkws') { // Synthèse des taux d'appartenance des mots-clés aux thèmes
    echo "<pre>\n";
    foreach (array_merge(['agg'], array_keys($cats)) as $catid) {
      list ($nbExplique, $nbTotal) = listkws($catid, $themes, []);
      echo "$catid:\n";
      foreach ($nbExplique as $theme => $nb)
        printf("  $theme: %d / %d = %.0f %%\n", $nb, $nbTotal, $nb/$nbTotal*100);
    }
  }
  die();
}
  
if (!isset($_GET['action'])) { // menu principal 
  echo "Actions proposées:<ul>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdata'>Toutes les MDD (type dataset ou series)</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listservices'>Toutes les MD de service</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldwkw'>MDD dont au moins un mot-clé correspond à un des thèmes</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldnkw'>MDD dont aucun mot-clé correspond à un des thèmes</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=setPerimetre'>Enregistre le primetre sur les MD</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=mdContacts'>Liste les mdContacts des MDD du périmètre</a></li>\n";
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
  list ($nbExplique, $nbTotal) = listkws($_GET['cat'], $themes, ['showKeywords']);
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
    if (kwInThemes($record['keyword'] ?? [], $themes)) {
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
