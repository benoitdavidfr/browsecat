<?php
/*PhpDoc:
title: record.inc.php - gère la conversion d'une fiche DCAT en fiche Inspire
name: record.inc.php
classes:
doc: |
  Le code initial de browsecat a été écrit pour utiliser une fiche Inspire définie comme array par mdvars2.inc.php
  La création d'objet Record simule un array en lecture en créant à la volée les champs Inspire à partir des champs DCAT
journal: |
  22/11/2021:
    - création
includes:
*/

/*PhpDoc: classes
title: Record - classe utilisée pour Inspire simulant un array en lecture
name: Record
doc: |
*/
use Symfony\Component\Yaml\Yaml;

class Record implements ArrayAccess {
  protected array $record; // stockage de la fiche comme array Php structuré comme dans PgSql
  
  static function create(string $record): Record {
    $record = json_decode($record, true);
    if (!isset($record['standard']))
      return new Record($record);
    else
      return new RecordDcat($record);
  }

  function __construct(array $record) { $this->record = $record; }
  function asArray(): array { return $this->record; }
  
  function offsetExists($offset) {
    return isset($this->record[$offset]);
  }

  function offsetSet($offset, $value) {
    if ($offset == 'keyword')
      $record['keyword'] = $value;
    else
      throw new Exception("Record::offsetSet(offset=$offset) interdit");
  }

  function offsetUnset($offset) { throw new Exception("Record::offsetUnset(offset=$offset) interdit"); }
  
  function offsetGet($offset) {
    return isset($this->record[$offset]) ? $this->record[$offset] : null;
  }
};

/*PhpDoc: classes
title: KnownThemes - Définition d'étiquettes de certains concepts utilisés dans les thèmes
name: KnownThemes
doc: |
*/
class KnownThemes {
  const PREFLABELS = [
    'http://publications.europa.eu/resource/authority/data-theme/AGRI'=> "Agriculture, pêche, sylviculture et alimentation",
    'http://publications.europa.eu/resource/authority/data-theme/EDUC'=> "Éducation, culture et sport",
    'http://publications.europa.eu/resource/authority/data-theme/ECON'=> "Économie et finances",
    'http://publications.europa.eu/resource/authority/data-theme/ENER'=> "Énergie",
    'http://publications.europa.eu/resource/authority/data-theme/ENVI'=> "Environnement",
    'http://publications.europa.eu/resource/authority/data-theme/GOVE'=> "Gouvernement et secteur public",
    'http://publications.europa.eu/resource/authority/data-theme/HEAL'=> "Santé",
    'http://publications.europa.eu/resource/authority/data-theme/INTR'=> "Questions internationales",
    'http://publications.europa.eu/resource/authority/data-theme/JUST'=> "Justice, système juridique et sécurité publique",
    'http://publications.europa.eu/resource/authority/data-theme/OP_DATPRO'=> "Données provisoires",
    'http://publications.europa.eu/resource/authority/data-theme/REGI'=> "Régions et villes",
    'http://publications.europa.eu/resource/authority/data-theme/SOCI'=> "Population et société",
    'http://publications.europa.eu/resource/authority/data-theme/TECH'=> "Science et technologie",
    'http://publications.europa.eu/resource/authority/data-theme/TRAN'=> "Transports",
  ];
};

/*PhpDoc: classes
title: RecordDcat - classe pour DCAT effectuant certaines conversions
name: Record
doc: |
*/
class RecordDcat extends Record implements ArrayAccess {
  const EXT = ['responsibleParty', 'keyword']; // liste des propriétés ajoutées
  function __construct(array $record) { $this->record = $record; }
  
  function offsetExists($offset) {
    return isset($this->record[$offset]) || in_array($offset, self::EXT);
  }

  function offsetSet($offset, $value) {
    if ($offset == 'keyword')
      $this->set_keyword($value);
    else
      throw new Exception("RecordDcat::offsetSet(offset=$offset) interdit");
  }

  function offsetUnset($offset) {
    if ($offset == 'keyword')
      $this->unset_keyword();
    else
      throw new Exception("Record::offsetUnset(offset=$offset) interdit");
  }

  function offsetGet($offset) {
    if (in_array($offset, self::EXT)) {
      $methodName = "get_$offset";
      return $this->$methodName();
    }
    else
      return  $this->record[$offset] ?? null;
  }
  
  function get_responsibleParty(): array { // simule le champ responsibleParty
    //echo "<pre>"; print_r($this); echo "</pre>\n";
    $organisationName =
      isset($this->record['publisher'][0]['org_title']) ? $this->record['publisher'][0]['org_title'] : 'UNDEFINED';
    $electronicMailAddress =
      isset($this->record['contactPoint'][0]['hasURL']) ? $this->record['contactPoint'][0]['hasURL'] : 'UNDEFINED';
    return [[
      'organisationName'=> $organisationName,
      'role'=> 'publisher',
      'electronicMailAddress'=> $electronicMailAddress,
    ]];
  }
  
  function get_keyword(): array { // convertit les keyword et theme DCAT en keyword ISO
    $kws = [];
    foreach ($this->record['keyword'] ?? [] as $kw)
      $kws[] = ['value'=> $kw];
    foreach ($this->record['theme'] ?? [] as $theme) {
      if (!($prefLabel = $theme['prefLabel'] ?? null)) {
        if (!($prefLabel = KnownThemes::PREFLABELS[$theme['@id']] ?? null))
          throw new Exception("prefLabel absent dans theme pour @id='".$theme['@id']."'");
      }
      $kws[] = array_merge(
        ['@id'=> $theme['@id']],
        ['value'=> $prefLabel],
        isset($theme['inScheme']) ? ['thesaurusId'=> $theme['inScheme']] : []
      );
    }
    //echo Yaml::dump(['get_keyword()'=> $kws]);
    return $kws;
  }
  
  function set_keyword(array $kws) { // décompose les keyword ISO en keyword et theme DCAT
    $this->record['keyword'] = [];
    $this->record['theme'] = [];
    foreach ($kws as $kw) {
      if (isset($kw['thesaurusId'])) {
        $this->record['theme'][] = [
          '@id'=> $kw['thesaurusId'].'/'.urlencode($kw['value']),
          'prefLabel'=> $kw['value'],
          'inScheme'=> $kw['thesaurusId'],
        ];
      }
      else {
        $this->record['keyword'][] = $kw['value'];
      }
    }
  }
  
  function unset_keyword() {
    unset($this->record['keyword']);
    unset($this->record['theme']);
  }
};
