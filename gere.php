<?php
/*PhpDoc:
title: gere.php - visualise et exploite le contenu des catalogues moissonnés et stockés dans PgSql
name: gere.php
doc: |
  Je m'intéresse principalement aux MDD du périmètre ministériel, cad les MDD dont un au moins des responsibleParty
  est une DG du pôle ministériel (MTE/MCTRCT/MM) ou un service déconcentré du pôle ou une DTT(M).
  Les mdContacts de ces MDD ne sont parfois pas des organisations du pôle.

  A FAIRE:
    remplacer l'utilisation des *Sel.yaml par celle de organisation.yaml
journal: |
  11/11/2021:
    - carte de thème indépendamment de l'org
  10/11/2021:
    - renommage manage.php -> gere.php
  8/11/2021:
    - renommage index.php -> manage.php pour utiliser index.php comme accès plus gd public
  27/10-4/11/2021:
    - réécriture nlle version utilisant PgSql
  18/10/2021:
    - création
includes:
  - cswserver.inc.php
  - cats.inc.php
  - catinpgsql.inc.php
  - arbo.inc.php
  - orginsel.inc.php
  - mdvars2.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/orginsel.inc.php';

use Symfony\Component\Yaml\Yaml;

// Liste des arborescences auxquelles les mots-clés peuvent appartenir
$arbos = [
  'arboCovadis'=> new Arbo('arbocovadis.yaml'),
  'annexesInspire'=> new Arbo('annexesinspire.yaml'),
];

// Choisir le serveur
if ($_SERVER['HTTP_HOST']=='localhost')
  PgSql::open('host=pgsqlserver dbname=gis user=docker');
else
  PgSql::open('pgsql://benoit@db207552-001.dbaas.ovh.net:35250/catalog/public');
//PgSql::open('pgsql://browsecat:Browsecat9@db207552-001.dbaas.ovh.net:35250/catalog/public');


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
      perimetre varchar(256), -- 'Min','Op','Autres' ; null si non défini
      area real -- surface des bbox en degrés carrés
    )");

    foreach ($cats as $catid => $cat) {
      if ($cat['dontAgg'] ?? false) continue;
      $sql = "insert into catalogagg(cat, id, record, title, type, perimetre, area)\n"
            ."  select '$catid', id, record, title, type, perimetre, area\n"
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
      if (is_file("catalogs/${catid}Sel.yaml")) {
        foreach (Yaml::parseFile("catalogs/${catid}Sel.yaml")['orgNames'] as $orgName) {
          if (!in_array($orgName, $orgNames))
            $orgNames[] = $orgName;
        }
      }
    }
    asort($orgNames);
    file_put_contents('catalogs/aggSel.yaml',
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
  //echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsArbo&amp;type=responsibleParty'>
    Liste des responsibleParty hors arbo. des orgs.</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsArbo&amp;type=mdContact'>
    Liste des mdContact hors arbo. des orgs. des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdataperimetre'>Liste les MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldwkw'>
    MDD dont au moins un mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=ldnkw'>MDD dont aucun mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=setPerimetre'>Enregistre le périmetre sur les MD</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=mdContacts'>Liste les mdContacts des MDD du périmètre</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbMdParTheme'>Dénombrement des MD par thème</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbMdParOrg&amp;type=responsibleParty'>Dénombrement des MD par responsibleParty</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbMdParOrgTheme&amp;type=responsibleParty&amp;arbo=arboCovadis'>
    Dénombrement des MD par responsibleParty et arboCovadis</a></li>\n";  
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

if ($_GET['action']=='showUsingIds') { // affiche une liste de fiches définie par une liste d'ids 
  $ids = explode(',', $_GET['ids']);
  foreach ($ids as $k => $id) {
    $ids[$k] = "'$id'";
  }
  $sql = "select id,title from catalog$_GET[cat]
          where type in ('dataset','series')
            and id in (".implode(',', $ids).")";
  //echo $sql;
  echo "<ul>\n";
  foreach (PgSql::query($sql) as $record) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die("</ul>\n");
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

if ($_GET['action']=='orgsHorsSel') { // liste les organisations hors sélection, utilise les fichiers *Sel.yaml 
  if (!is_file("catalogs/$_GET[cat]Sel.yaml"))
    $orgNamesSel = [];
  else
    $orgNamesSel = Yaml::parseFile("catalogs/$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
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

if ($_GET['action']=='orgsHorsArbo') { // liste les organisations $_GET[type] hors de l'arbo des organisations 
  if (!is_file("orgpmin.yaml")) {
    echo "Arbo non trouvée<br>\n";
    $arboOrgs = null;
  }
  else
    $arboOrgs = new Arbo('orgpmin.yaml');
  $orgNames = []; // liste des organisations hors Arbo sous la forme [{lower}=> {name}]
  $sql = "select record from catalog$_GET[cat] where type in('data','series')"
    .(($_GET['type']=='mdContact') ? " and perimetre='Min'" : '');
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    //echo "<pre>"; print_r($record); //die();
    foreach ($record[$_GET['type']] ?? [] as $org) {
      if (isset($org['organisationName'])) {
        if (!$arboOrgs || !$arboOrgs->labelIn($org['organisationName']))
          $orgNames[strtolower($org['organisationName'])] = $org['organisationName'];
      }
    }
  }
  if (!$orgNames)
    die("Aucune organisation trouvée<br>\n");
  ksort($orgNames);
  if ($_GET['type']=='mdContact')
    echo "<ul>\n";
  else
    echo "<pre>\n";
  foreach ($orgNames as $orgName) {
    if ($_GET['type']=='mdContact') {
      $url = "?cat=$_GET[cat]&amp;action=mddOfOrg&amp;org=".urlencode($orgName)."&type=mdContact";
      echo "<li><a href='$url'>$orgName</li>\n";
    }
    else
      echo "  - $orgName\n";
  }
  die();
}

if ($_GET['action']=='orgs') { // liste des organisations sélectionnés avec lien vers leurs MDD
  if (!is_file("catalogs/$_GET[cat]Sel.yaml"))
    die("Pas de sélection");
  $orgNames = Yaml::parseFile("catalogs/$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  sort($orgNames);
  echo "<ul>\n";
  foreach ($orgNames as $orgName) {
    $url = "?cat=$_GET[cat]&amp;action=mddOfOrg&amp;org=".urlencode($orgName)."&type=responsibleParty";
    echo "<li><a href='$url'>$orgName</li>\n";
  }
  die();
}

if ($_GET['action']=='listdataperimetre') { // Liste les MDD du périmètre 
  echo "<ul>\n";
  $sql = "select id,title from catalog$_GET[cat] where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  }
  echo "</ul>\n";
  die();
}

if ($_GET['action']=='mddOfOrg') { // liste les MDD d'une organisation comme $_GET[type], si mdContact uniq. MDD du périmètre
  echo "<ul>\n";
  $sql = "select id,title,record from catalog$_GET[cat] where type in('data','series')"
    .(($_GET['type']=='mdContact') ? " and perimetre='Min'" : '');
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    $mdOfOrg = false;
    foreach ($record[$_GET['type']] ?? [] as $org) {
      if (isset($org['organisationName']) && ($org['organisationName'] == $_GET['org'])) {
        $mdOfOrg = true;
        break;
      }
    }
    if ($mdOfOrg)
      echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  }
  die();
}

if ($_GET['action']=='setPerimetre') { // met à jour le périmetre dans la table
  PgSql::query("update catalog$_GET[cat] set perimetre=null");
  foreach (PgSql::query("select id,record from catalog$_GET[cat] where type in ('dataset','series')") as $tuple) {
    $id = $tuple['id'];
    $record = json_decode($tuple['record'], true);
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
  
if ($_GET['action']=='nbMdParOrg') { // Dénombrement des MDD par organisation de type $_GET[type]
  // ERREUR le script suppose que toutes les organiations sont au niveau 2
  $arboOrgsPMin = new Arbo('orgpmin.yaml');
  //$arboOrgsHors = new Arbo('orghorspmin.yaml')];
  $nbMdParOrg = []; // [{orgName} => nb]
  $nbMd = 0;
  $sql = "select id, title, record from catalog$_GET[cat]
          where type in ('dataset','series') and perimetre='Min'";
  //echo "<pre>";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    //echo "record = "; print_r($record);
    foreach ($record[$_GET['type']] as $org) {
      //print_r($org);
      if (!isset($org['organisationName']))
        $nbMdParOrg['UNDEF'] = 1 + ($nbMdParOrg['UNDEF'] ?? 0);
      elseif ($orgShortName = $arboOrgsPMin->short($org['organisationName'])) {
        //echo "$orgShortName<br>\n";
        $nbMdParOrg[$orgShortName] = 1 + ($nbMdParOrg[$orgShortName] ?? 0);
      }
    }
    $nbMd++;
  }

  echo "<table border=1>\n";
  foreach($arboOrgsPMin->children('') as $id1 => $niv1) {
    //echo "$id1-> ",$niv1->short(),"<br>\n";
    foreach($niv1->children() as $id2 => $niv2) {
      $short = $niv2->short();
      if ($nb = $nbMdParOrg[$short]?? 0) {
        printf("<tr><td>%s</td><td align='right'>%d</td><td align='right'>%.0f %%</td></tr>\n", $short, $nb, $nb/$nbMd*100);
      }
    }
  }
  $nb = $nbMdParOrg['UNDEF']?? 0;
  if ($nb)
    printf("<tr><td>UNDEF</td><td align='right'>%d</td><td align='right'>%.0f %%</td></tr>\n", $nb, $nb/$nbMd*100);
  echo "</table>\n";
  die();
}

if ($_GET['action']=='nbMdParOrgTheme') { // Dén. des MDD par organisation du type $_GET[type] et par theme $_GET[arbo]
  // Seules les MDD du périmètre sont prises en compte
  echo "<h2>Dénombrement des MDD du périmètre par $_GET[type] et par thème de $_GET[arbo]</h2>\n";
  function ligneThemes(Arbo $arbo, array $nbMdParTheme) { // ligne des themes en haut et en bas de l'affichage
    echo "<tr><td><center><b>org</b></center></td><td><center><b>S</b></center></td>\n";
    // Affichage des themes en en-têtes de colonnes
    foreach ($arbo->nodes() as $theme) {
      $plabel = $theme->prefLabel();
      if (isset($nbMdParTheme[$plabel]))
        echo "<td><center><b>",$theme->short(),"</b></center></td>";
    }
    echo "<td><b>NC</b></td></tr>\n";
  }
  
  function htmlFormSelect(string $name, array $values, string $default) {
    echo "<select name='$name' id='$name'>\n";
    foreach ($values as $value)
      echo "  <option value='$value'",($value==$default) ? ' selected' : '',">$value</option>\n";
    echo "</select>\n";
  }
  
  { // Menu pour changer de catalogue ou d'aborescence 
    echo "<form>\n";
    htmlFormSelect('cat', array_merge(array_keys($cats), ['agg']), $_GET['cat']);
    echo "<input type=hidden name='action' value='nbMdParOrgTheme'>\n";
    echo "<input type=hidden name='type' value='responsibleParty'>\n";
    htmlFormSelect('arbo', array_keys($arbos), $_GET['arbo']);
    echo "<input type=submit value='go'>\n";
    echo "</form>\n";
  }

  $nonClasse = new Concept(['NC'],['prefLabels'=>['fr'=>'NON CLASSE']]);
  $orgNonDefinies = [
    //new Concept(['ND'],['short'=>'ORG NON DEF', 'prefLabels'=>['fr'=>'ORG NON DEF']]),
    new Concept(['NOORG'],['short'=>'NO ORG', 'prefLabels'=>['fr'=>'NO ORG']]),
  ];

  $arboOrgsPMin = new Arbo('orgpmin.yaml');
  $nbMdParOrgTheme = []; // nb de MD par org. et par theme -> [{orgName} => [{theme} => nb]]
  $nbMdParOrg = []; // nb de MD par org -> [{orgName} => nb]
  $nbMdParTheme = []; // nb de MD par thème -> [{theme} => nb]
  $nbMdd = 0;
  
  // Dénombrement
  $sql = "select id, title, record from catalog$_GET[cat]
          where type in ('dataset','series') and perimetre='Min'";
  //echo "<pre>";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    //echo "record = "; print_r($record);
    $orgShortNames = []; // liste des noms courts du périm. ou ['HORS MIN'=> 1]
    foreach ($record[$_GET['type']] as $org) {
      //print_r($org);
      
      if (!isset($org['organisationName'])) continue;
      if (!($orgShortName = $arboOrgsPMin->short($org['organisationName']))) continue;
      $orgShortNames[$orgShortName] = 1;
    }
    if (!$orgShortNames)
      $orgShortNames = ['NO ORG'=> 1];
    $nbOrgs = count($orgShortNames);

    $prefLabels = prefLabels($record['keyword'] ?? [], ['a'=>$arbos[$_GET['arbo']]]);
    //echo "prefLabels()="; print_r($prefLabels); echo "<br>\n";
    $nbThemes = count($prefLabels['a'] ?? ['xx']);
    foreach (array_keys($orgShortNames) as $orgShortName) {
      $nbMdParOrg[$orgShortName] = 1/$nbOrgs + ($nbMdParOrg[$orgShortName] ?? 0);
      foreach ($prefLabels['a'] ?? ['NON CLASSE'] as $plabel) {
        //echo "$orgShortName X $plabel -> ",1/$nbOrgs/$nbThemes,"<br>\n";
        $nbMdParTheme[$plabel] = 1/$nbOrgs/$nbThemes + ($nbMdParTheme[$plabel] ?? 0);
        if (!isset($nbMdParOrgTheme[$orgShortName][$plabel]))
          $nbMdParOrgTheme[$orgShortName][$plabel] = 1/$nbOrgs/$nbThemes;
        else
          $nbMdParOrgTheme[$orgShortName][$plabel] += 1/$nbOrgs/$nbThemes;
      }
    }
    $nbMdd++;
  }

  //print_r($nbMdParOrgTheme);
  echo "<table border=1>\n";
  // Affichage des themes en en-têtes de colonnes
  ligneThemes($arbos[$_GET['arbo']], $nbMdParTheme);
  
  //echo "<tr>"; for($i=1;$i<=200;$i++) echo "<td>$i</td>"; echo "<tr>\n"; // numérotation des colonnes
  
  // affichage du contenu de la table org X theme
  foreach(array_merge($arboOrgsPMin->nodes(), $orgNonDefinies) as $idorg => $org) {
    $short = $org->short();
    if (!isset($nbMdParOrg[$short])) continue;
    printf("<tr><td>%s</td><td align='right'>%d</td>", $short, ceil($nbMdParOrg[$short]));
    foreach (array_merge($arbos[$_GET['arbo']]->nodes(), [$nonClasse]) as $theme) {
      $plabel = (string)$theme;
      if (!isset($nbMdParTheme[$plabel])) continue;
      $nb = isset($nbMdParOrgTheme[$short][$plabel]) ? $nbMdParOrgTheme[$short][$plabel] : 0;
      $url = "?cat=$_GET[cat]&amp;action=mddOrgTheme"
        ."&amp;type=$_GET[type]&amp;org=".urlencode((string)$org)
        ."&amp;arbo=$_GET[arbo]&amp;theme=".urlencode((string)$theme);
      $title = (string)$theme;
      if ($nb)
        printf("<td align='right'><a href='%s' title='%s'>%d</a></td>", $url, $theme, ceil($nb));
      else
        echo "<td></td>";
    }
    // colonne supplémentaire avec le nom court du thème
    echo "<td>$short</td></tr>\n";
  }
  
  // 2ème affichage des thèmes en ligne de bas de tableau
  ligneThemes($arbos[$_GET['arbo']], $nbMdParTheme);
  
  // Affichage d'une ligne finale des sommes par colonne en caractères plus petits
  echo "<tr><td align='center'><b>Somme</b></td><td align='center'><small>$nbMdd</small></td>\n";
  foreach (array_merge($arbos[$_GET['arbo']]->nodes(), [$nonClasse]) as $theme) {
    $plabel = $theme->prefLabel();
    if (!isset($nbMdParTheme[$plabel])) continue;
    $url = "?cat=$_GET[cat]&amp;action=mddOrgTheme&amp;arbo=$_GET[arbo]&amp;theme=".urlencode((string)$theme);
    printf("<td align='right'><small><a href='%s'>%d</a></small></td>", $url, $nbMdParTheme[$plabel]);
  }
  echo "</tr>\n";
  echo "</table>\n";
  
  // Enfin affichage de la liste des thèmes avec le nom court
  echo "<h2>Nomenclature des thèmes</h2>\n";
  echo "<table border=1><th>code</th><th>étiquette</th>\n";
  foreach (array_merge($arbos[$_GET['arbo']]->nodes(), [$nonClasse]) as $theme) {
    if (isset($nbMdParTheme[(string)$theme]))
      echo "<tr><td>",$theme->short(),"</td><td>$theme</td></tr>\n";
  }
  echo "</table>\n";
  die();
}

if ($_GET['action']=='mddOrgTheme') { // liste les MDD avec org et theme
  $mapurl = "map.php?cat=$_GET[cat]"
    .(isset($_GET['org']) ? "&amp;otype=$_GET[type]&amp;org=".urlencode($_GET['org']) : '')
    .(isset($_GET['theme']) ? "&amp;arbo=$_GET[arbo]&amp;theme=$_GET[theme]" : '');
  echo "<h2>MDD de ",
    (isset($_GET['org']) ? "$_GET[type]=\"$_GET[org]\" et " : ''),
    (isset($_GET['theme']) ? "$_GET[arbo]=\"$_GET[theme]\"" : ''),
    " (<a href='$mapurl'>map</a>)</h2><ul>\n";
  $arboOrgsPMin = new Arbo('orgpmin.yaml');
  $sql = "select cat.id, title
          from catalog$_GET[cat] cat"
         .(isset($_GET['org']) ? ", catorg$_GET[cat] org" : '')
         .(isset($_GET['theme']) ? ", cattheme$_GET[cat] theme" : '')."
          where
            type in ('dataset','series') and perimetre='Min' and area is not null\n";
  if (isset($_GET['org']))
    $sql .= "and cat.id=org.id and org.org='".str_replace("'","''", $_GET['org'])."'\n";
  if (isset($_GET['theme']))
    $sql .= "and cat.id=theme.id and theme.theme='".str_replace("'","''", $_GET['theme'])."'\n";
  //echo "<pre>";
  foreach (PgSql::query($sql) as $tuple) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  }
}
