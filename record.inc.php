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

  function offsetSet($offset, $value) { throw new Exception("RecordInspire::offsetSet() interdit"); }

  function offsetUnset($offset) { throw new Exception("RecordInspire::offsetUnset() interdit"); }
  
  function offsetExists($offset) {
    return isset($this->record[$offset]);
  }

  function offsetGet($offset) {
    return isset($this->record[$offset]) ? $this->record[$offset] : null;
  }
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
  
  function get_keyword(): array {
    $kws = [];
    foreach ($this->record['keyword'] ?? [] as $kw)
      $kws[] = ['value'=> $kw];
    return $kws;
  }
};
