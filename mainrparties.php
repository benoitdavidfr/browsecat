<?php
/*PhpDoc:
title: mainrparties.php - analyse la possibilité d'obtenir une seule org. responsable appelée mainResponsibleParties 
name: mainrparties.php
doc: |
  Logique de choix si possible d'un seul organisme responsable principal (mainResponsibleParties).
    1) Suppression de la Covadis
    2) si les organismes ont des roles différents alors choix de ceux qui ont le rôle le plus pertinent
    3) si les organismes ont des statuts différents alors choix DREAL > DIR > DDT > DG
  Voir analyse du résultat dans mainrparties.yaml
journal: |
  15/11/2021:
    - modif du nom de doublon.php en mainrparties.php
  13-14/11/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orgarbo.inc.php';

use Symfony\Component\Yaml\Yaml;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mainrparties</title></head><body>\n";

if (!isset($_GET['server'])) { // Choix du serveur 
  echo "<h2>Serveur</h2><ul>\n";
  echo "<li><a href='?server=local'>local</a></li>\n";
  echo "<li><a href='?server=distant'>distant</a></li>\n";
  die("</ul>\n");
}

if (!isset($_GET['cat'])) { // choix du catalogue
  if (!isset($_GET['action'])) { // liste des catalogues pour en choisir un
    echo "Catalogues:<ul>\n";
    foreach (array_merge(['agg'],array_keys($cats)) as $catid) {
      echo "<li><a href='?server=$_GET[server]&amp;cat=$catid'>$catid</a></li>\n";
    }
    echo "</ul>\n";
  }
  die();
}
  
if (!isset($_GET['action'])) { // choix d'une action 
  echo "<h2>Actions</h2><ul>\n";
  echo "<li><a href='?server=$_GET[server]&amp;cat=$_GET[cat]&amp;action=create'>(re)création de la table mainrp</a></li>\n";
  echo "<li><a href='?server=$_GET[server]&amp;cat=$_GET[cat]&amp;action=list'>affichage de la table mainrp</a></li>\n";
  echo "<li><a href='?server=$_GET[server]&amp;cat=$_GET[cat]&amp;action=testMainRParties'>testMainRParties</a></li>\n";
  die("</ul>\n");
}

if (!CatInPgSql::chooseServer($_GET['server'])) { // Sélection du serveur 
  die ("Erreur: paramètre serveur incorrect !<br>\n");
}

if ($_GET['action'] == 'create') { // création de la table mainrp avec le nborg par fiche pour celles en ayant au moins 2 
  PgSql::query("drop table if exists mainrp$_GET[cat]");
  $sql = "create table mainrp$_GET[cat] as
  select id, count(org) nborg
  from catalog$_GET[cat] join catorg$_GET[cat] using(id)
  where perimetre='Min'
  group by id
  having count(org) > 1";
  PgSql::query($sql);
  die("ok\n");
}

function mainRParties(array $responsibleParties): array { // cherche à réduire le nombre de parties
  static $arboOrgs = [];
  if (!$arboOrgs)
    $arboOrgs = ['PMin'=> new OrgArbo('orgpmin.yaml'), 'HMin'=> new OrgArbo('orghorspmin.yaml')];

  // construction de [{role} => [{orgShort}]]
  $roles = []; // liste des organisations structurées par role - [{role} => [{orgplabel}]]
  foreach($responsibleParties as $party) {
    $sname = 'Absent des référentiels';
    foreach ($arboOrgs as $arboType => $arboOrg) {
      if ($s = $arboOrg->short($party)) {
        $sname = "$s ($arboType)";
        break;
      }
    }
    //echo "sname=$sname<br>\n";
    if (!$sname || ($sname == 'COVADIS')) continue;
    $role = $party['role'] ?? 'undef';
    if (!isset($roles[$role]))
      $roles[$role] = [$sname];
    elseif (!in_array($sname, $roles[$role]))
      $roles[$role][] = $sname;
  }
  /*echo '<pre>',Yaml::dump([
      'responsibleParty'=> $responsibleParties,
      'roles'=> $roles,
    ]),"</pre>\n";*/
  
  $orderedRoles = [
    'distributor','publisher','custodian','processor','resourceProvider','pointOfContact',
    'originator','owner','author','principalInvestigator','user',
  ];
  foreach($orderedRoles as $role) {
    if (isset($roles[$role])) {
      $mainRParties = $roles[$role];
      break;
    }
  }
  //echo '<pre>',Yaml::dump(['$responsibleParties'=> $responsibleParties]),"</pre>\n";
  if (count($mainRParties)==1)
    return $mainRParties;

  //echo '<pre>',Yaml::dump(['mainRParties'=> $mainRParties,]),"</pre>\n";
  
  // à ce stade ttes les parties ont même role
  
  $mainRPartiesPerType = [];
  foreach ($mainRParties as $mainRParty) {
    foreach (['0DR'=>'(DREAL|DRIEAT|Deal)', '1DIR'=> 'DIR', '2DD'=>'DDT', '3DG'=>''] as $type => $pattern) {
      if (preg_match("!$pattern!", $mainRParty)) {
        $mainRPartiesPerType[$type][] = $mainRParty;
        break;
      }
    }
  }
  ksort($mainRPartiesPerType);
  
  /*echo '<pre>',Yaml::dump([
      '$mainRPartiesPerType'=> $mainRPartiesPerType,
      'array_values($mainRPartiesPerType)[0]'=> array_values($mainRPartiesPerType)[0],
    ]),"</pre>\n";*/

  if (count(array_values($mainRPartiesPerType)[0])==1)
    return array_values($mainRPartiesPerType)[0];

  return array_values($mainRPartiesPerType)[0];
}

if ($_GET['action'] == 'testMainRParties') { // Test de la fonction mainRParties() sur des cas particuliers
  $records = <<<EOT
- fileIdentifier:
    - fr-120066022-jdd-7afc1296-11bf-4b91-a93d-f74b529279d7
  'dct:title':
    - 'Nouvelle-Aquitaine : Zones vulnérables 2015 à la pollution par les nitrates d''origine agricole du bassin Adour-Garonne - Périmètres (surfacique)'
  responsibleParty:
    - { organisationName: 'DREAL Nouvelle-Aquitaine / MiCAT / PIG', role: pointOfContact, electronicMailAddress: pig.micat.dreal-na@developpement-durable.gouv.fr }
    - { organisationName: 'DREAL Midi-Pyrénées', role: resourceProvider, electronicMailAddress: dreal-midi-pyrenees@developpement-durable.gouv.fr }
    - { organisationName: 'DREAL Aquitaine Limousin Poitou-Charentes', role: owner, electronicMailAddress: pig.micat.dreal-alpc@developpement-durable.gouv.fr }
    - { organisationName: 'Agence de l''eau Adour-Garonne', role: resourceProvider, electronicMailAddress: geocatalogue@eau-adour-garonne.fr }
  mdContact:
    - { organisationName: 'DREAL Nouvelle-Aquitaine / MiCAT / PIG', electronicMailAddress: pig.micat.dreal-na@developpement-durable.gouv.fr }
EOT;
  $records = Yaml::parse($records); 
  foreach ($records as $record) {
    $mainRParties = mainRParties($record['responsibleParty'] ?? []);
    echo '<pre>',Yaml::dump([
        'fileIdentifier'=> $record['fileIdentifier'][0],
        'title'=> $record['dct:title'],
        'responsibleParty'=> $record['responsibleParty'] ?? [],
        'mainRParties'=> $mainRParties,
      ]),"</pre>\n";
  }
}

if ($_GET['action'] == 'list') { // affichage de la table mainrp en essayant de réduire le nombre de respParties
  $sql = "select ".($_GET['cat']=='agg'?'cat,':'')."id, title, record, nborg
    from catalog$_GET[cat] join mainrp$_GET[cat] using(id)
    order by nborg desc";
  //echo "<pre>$sql</pre>\n";
  echo "<ul>\n";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    $mainRParties = mainRParties($record['responsibleParty'] ?? []);
    $bold = (count($mainRParties) > 1);
    echo "<li><a href='?server=$_GET[server]&amp;cat=$_GET[cat]&amp;action=showOne&amp;id=$tuple[id]'>",
         ($bold ?'<b>':'')."$tuple[title]</a> ($tuple[nborg] -> ",count($mainRParties),")",($bold?'</b>':''),"</li>\n";
    if (count($mainRParties) > 1)
      echo '<pre>',Yaml::dump([
        'cat'=> $tuple['cat'] ?? $_GET['cat'],
          'id'=> $tuple['id'],
          'title'=> $tuple['title'],
          'responsibleParty'=> $record['responsibleParty'] ?? [],
          'mainRParties'=> $mainRParties,
        ]),"</pre>\n";
  }
  die("</ul>\n");
}

if ($_GET['action']=='showOne') { // affiche un fiche de MDD avec calcul de mainRParties
  $sql = "select ".($_GET['cat']=='agg'?'cat,':'')."title,record from catalog$_GET[cat] where id='$_GET[id]'";
  $tuple = PgSql::getTuples($sql)[0];
  echo "<a href='a.php?cat=$_GET[cat]&amp;action=showPg&amp;id=$_GET[id]'>$tuple[title]</a></a>\n";
  $record = json_decode($tuple['record'], true);
  $mainRParties = mainRParties($record['responsibleParty'] ?? []);
  echo '<pre>',Yaml::dump([
      'cat'=> $tuple['cat'] ?? $_GET['cat'],
      'responsibleParty'=> $record['responsibleParty'] ?? [],
      'mainRParties'=> $mainRParties,
    ]),"</pre>\n";
}
