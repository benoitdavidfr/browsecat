<?php
/*PhpDoc:
name:  mdvars2.inc.php
title: mdvars2.inc.php - classe statique Mdvars décrivant la liste des variables des éléments de MD et des traitements associés
classes:
functions:
doc: |
  Script repris de geocat3 et adapté pour geocat4.
journal: |
  17/11/2021:
    - renommage de la classe en Iso19139
  20/2/2021:
    - plusieurs évolutions
  8/2/2021:
    - adaptation pour geocat4 à partir de geocat3/mdvars2.inc.php
  26/9/2015 :
    - correction d'un bug sur les xpath des 2 langues
    - ajout de la lecture d'un éventuel codeSpace d'un URI encodé avec un RS_Identifier
  21/9/2015:
    - correction d'un bug sur les espaces de noms XML dans la méthode extract()
  8/7/2015:
    - ajout du champ aggregationInfo
  1/7/2015:
    - correction d'un bug sur les espaces de noms XML, voir la méthode setNameSpaces()
  30/6/2015:
    - première version béta utilisée dans load2.php
*/

/*PhpDoc: classes
name:  Iso19139
title: Iso19139 - classe statique contenant la description des variables des éléments de MD et l'utilisant pour certains traitements
pubproperties:
methods:
doc: |
  La propriété publique statique mdvars définit la correspondance entre les fiches de MD ISO 19139 et la base de données geocat3. 
  La méthode extract() utilise mdvars pour extraire d'un fragment XML ISO19139 les valeurs pour les différents champs.
  La méthode show() permet de visualiser les données retournées par extract().
*/
class Iso19139 {
  // les prefixes et espaces de noms utilisés dans les xpath des variables sous la forme namespace -> prefix
  static private $namespaces = [
    'http://www.isotc211.org/2005/gmd' => 'gmd',
    'http://www.isotc211.org/2005/gco' => 'gco',
    'http://www.isotc211.org/2005/gmx' => 'gmx',
    'http://www.isotc211.org/2005/srv' => 'srv',
    'http://www.w3.org/1999/xlink' => 'xlink',
  ];
  
  /*PhpDoc: pubproperties
  name:  mdvars
  title: static $mdvars - variable statique publique contenant la description des variables des éléments de MD
  doc: |
    La variable statique mdvars définit la correspondance entre les fiches de MD ISO 19139 et la base de données geocat3.
    Elle est fondée sur:
    - le règlement Inspire métadonnées en français et en anglais pour les titres des variables,
    - le guide Inspire européen des métadonnées, v. 2.0.1, 2/3/2017
      (https://inspire.ec.europa.eu/id/document/tg/metadata-iso19139/2.0.1)
    - (le guide CNIG des métadonnées, v 1.1.1, juillet 2014)
    - Guide CNIG de saisie des éléments de métadonnées INSPIRE Appliqué aux données, Version 2.0 – décembre 2019
    - (la pratique des MD disponibles dans le Géocatalogue)

    Une fiche de métadonnées est composée d'éléments de métadonnées, chacun correspondant à une variable pouvant être simple
    ou complexe.
    Une variable simple correspond à une valeur atomique, une variable complexe correspond à un n-uplet.
    Pour une fiche de métadonnées peut contenir plusieurs éléments correspondant à la même variable.
    Chaque élément de métadonnées correspondra dans la base geocat3 à un enregistrement de mdelement.

    Le tableau mdvars liste les variables avec comme clé un nom court et pour chacune les champs suivants:
     - title-fr : titre de la variable en français
     - title-en : titre de la variable en anglais
     - xpath : xpath ISO 19139 de la valeur pour une variable simple ou du fragment XML correspondant au n-uplet pour
       une variable complexe
    En outre, pour une variable complexe, l'entrée du tableau comporte:
     - les champs svar et au moins un des champs svar0, svar2 ou svar3 qui contiennent chacun:
       - le sous-champ name contenant le nom de la sous-variable,
       - le sous-champ xpath contenant le xpath ISO 19139 de la valeur correspondant dans le fragment XML défini ci-dessus
    Enfin :
    - le champ text signale une variable texte pour laquelle un affichage particulier HTML est effectué
    - le champ stdFunc indique soit le nom de la fonction de standardisation des valeurs
      soit true si cette valeur est standardisée sans appel d'une telle fonction
    - le champ multiplicity comprend potentiellement deux sous-champs data et service indiquant la cardinalité définie par
      le règlement
      si le champ est absent, cela signifie que la variable n'est pas pertinente pour data ou service
      la valeur peut être 1, '0..1', '0..*' ou '1..*' 
    - valueDomain fournit la liste des valeurs autorisées mais n'est pas utilisé à ce stade

    mdvars: [ varname => [
      'title-fr' => titre en français,
      'title-en' => titre en anglais,
      'xpath' => xpath,
      { 'svar' =>  [ 'name' => name, 'xpath' => xpath ], }
      { 'svar0' => [ 'name' => name, 'xpath' => xpath ], }
      { 'svar2' => [ 'name' => name, 'xpath' => xpath ], }
      { 'svar3' => [ 'name' => name, 'xpath' => xpath ], }
      { 'stdFunc' => nom de la fonction de standardisation ou true }
      { 'text' => true }
    ] ]
    La numérotation et les titres en commentaires sont ceux du règlement métadonnées
  */
  static array $mdvars = [
    // fileIdentifier (hors règlement INSPIRE)
    'fileIdentifier' => [
      'title-fr' => "Identificateur du fichier",
      'title-en' => "File identifier",
      'xpath' => '//gmd:MD_Metadata/gmd:fileIdentifier/*',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    // parentIdentifier (hors règlement INSPIRE)
    'parentIdentifier' => [
      'title-fr' => "Identificateur d'un parent",
      'title-en' => "Parent identifier",
      'xpath' => '//gmd:MD_Metadata/gmd:parentIdentifier/*',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // aggregationInfo (hors règlement INSPIRE)
    'aggregationInfo' =>  [
      'title-fr' => "métadonnées agrégées",
      'title-en' => "aggregated metadata",
      'xpath' => '//gmd:identificationInfo/*/gmd:aggregationInfo',
      'svars' => [
        'aggregateDataSetIdentifier' => [
          'xpath' => '//gmd:aggregationInfo/*/gmd:aggregateDataSetIdentifier/*/gmd:code/gco:CharacterString',
        ],
        'associationType' => [
          'xpath' => '//gmd:associationType/*',
        ],
        'initiativeType' => [
          'xpath' => '//gmd:initiativeType/*',
        ],
      ],
      'multiplicity' => [ 'data' => '0..*' ],
    ],
    // 1.1. Intitulé de la ressource - 1.1. Resource title
    'dct:title' => [
      'title-fr' => "Intitulé de la ressource",
      'title-en' => "Resource title",
      'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:title/gco:CharacterString',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    // alternateTitle (hors règlement INSPIRE)
    'dct:alternative' => [
      'title-fr' => "Intitulé alternatif de la ressource",
      'title-en' => "Alternate resource title",
      'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:alternateTitle/gco:CharacterString',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // 1.2. Résumé de la ressource - 1.2. Resource abstract
    'dct:description' => [
      'title-fr' => "Résumé de la ressource",
      'title-en' => "Resource abstract",
      'xpath' => '//gmd:identificationInfo/*/gmd:abstract/gco:CharacterString',
      'text' => 1,
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    // 1.3. Type de la ressource - 1.3. Resource type
    'dct:type' => [
      'title-fr' => "Type de la ressource",
      'title-en' => "Resource type",
      'xpath' => '//gmd:MD_Metadata/gmd:hierarchyLevel/gmd:MD_ScopeCode/@codeListValue',
      'valueDomain' => ['series','dataset','services'],
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    // 1.4. Localisateur de la ressource - 1.4. Resource locator
    'locator'=> [
      'title-fr' => "Localisateur de la ressource",
      'title-en' => "Resource locator",
      'xpath' =>  '//gmd:distributionInfo/*/gmd:transferOptions/*/gmd:onLine',
      'svars' => [
        'url' => [
          'xpath' => '//gmd:onLine/*/gmd:linkage/gmd:URL',
        ],
        'protocol' => [
          'xpath' => '//gmd:onLine/*/gmd:protocol/gco:CharacterString',
        ],
        'name' => [
          'xpath' => '//gmd:onLine/*/gmd:name/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // 1.5. Identificateur de ressource unique - 1.5. Unique resource identifier
    'dct:identifier' => [
      'title-fr' => "Identificateur de ressource unique",
      'title-en' => "Unique resource identifier",
      'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:identifier',
      'svars' => [
        'codeSpace' => [
          'xpath' => '//gmd:identifier/*/gmd:codeSpace/gco:CharacterString',
        ],
        'code' => [
          'xpath' => '//gmd:identifier/*/gmd:code/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*' ],
    ],
    // 1.6.  Ressource Couplée (service) - 1.6. Coupled resource
    'operatesOn' => [
      'title-fr' => "Ressource Couplée",
      'title-en' => "Coupled resource",
      'xpath' => '//gmd:identificationInfo/*/srv:operatesOn',
      'svars'=> [
        'uuidref'=> [
          'xpath' => '//srv:operatesOn/@uuidref',
        ],
        'href'=> [
          'xpath' => '//srv:operatesOn/@xlink:href',
        ],
      ],
      'multiplicity' => [ 'service' => '0..*' ],
    ],
    // 1.7. Langue de la ressource - 1.7. Resource language
    'dct:language' => [
      'title-fr' => "Langue de la ressource",
      'title-en' => "Resource language",
      'xpath' => '//gmd:identificationInfo/*/gmd:language/gmd:LanguageCode',
      'svars'=> [
        'codeList'=> [
          'xpath'=> '//gmd:LanguageCode/@codeList',
        ],
        'codeListValue'=> [
          'xpath'=> '//gmd:LanguageCode/@codeListValue',
        ],
      ],
      'multiplicity' => [ 'data' => '0..*' ],
    ],
    // Encodage (hors règlement INSPIRE)
    'distributionFormat' => [
      'title-fr' => "Encodage",
      'title-en' => "Distribution format",
      'xpath' => '//gmd:distributionInfo/*/gmd:distributionFormat',
      'svars'=> [
        'name'=> [
          'xpath' => '//gmd:distributionFormat/*/gmd:name/gco:CharacterString',
        ],
        'version'=> [
          'xpath' => '//gmd:distributionFormat/*/gmd:version/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*' ],
    ],
    // Encodage des caractères (hors règlement INSPIRE)
    'characterSet' => [
      'title-fr' => "Encodage des caractères",
      'title-en' => "Character set",
      'xpath' => '//gmd:identificationInfo/*/gmd:characterSet/gmd:MD_CharacterSetCode/@codeListValue',
      'multiplicity' => [ 'data' => '0..1' ],
    ],
    // Type de représentation géographique (hors règlement INSPIRE)
    'spatialRepresentationType' => [
      'title-fr' => "Type de représentation géographique",
      'title-en' => "Spatial representation type",
      'xpath' => '//gmd:identificationInfo/*/gmd:spatialRepresentationType/gmd:MD_SpatialRepresentationTypeCode/@codeListValue',
      'multiplicity' => [ 'data' => '1..*' ],
    ],
  
    // 2. CLASSIFICATION DES DONNÉES ET SERVICES GÉOGRAPHIQUES
    // 2.1. Catégorie thématique - 2.1. Topic category
    'topicCategory' => [
      'title-fr' => "Catégorie thématique",
      'title-en' => "Topic category",
      'xpath' => '//gmd:identificationInfo/*/gmd:topicCategory/gmd:MD_TopicCategoryCode',
      'multiplicity' => [ 'data' => '1..*' ],
      'valueDomain' => [
          'farming','biota','boundaries','climatologyMeteorologyAtmosphere','economy','elevation','environment',
          'geoscientificInformation','health','imageryBaseMapsEarthCover','intelligenceMilitary','inlandWaters',
          'location','oceans','planningCadastre','society','structure','transportation','utilitiesCommunication'
      ],
    ],
    // 2.2.  Type de service de données géographiques (service) - 2.2. Spatial data service type
    'serviceType' => [
      'title-fr' => "Type de service de données géographiques",
      'title-en' => "Spatial data service type",
      'xpath' => '//gmd:identificationInfo/*/srv:serviceType/gco:LocalName',
      'multiplicity' => [ 'service' => 1 ],
      'valueDomain' => ['discovery','view','download','transformation','invoke','other']
    ],
  
    // 3. MOT CLÉ - KEYWORD
    // 3.1. Valeur du mot clé - Keyword value
    // 3.2. Vocabulaire contrôlé d’origine - Originating controlled vocabulary
    'keyword' => [
      'title-fr' => "Mot-clé",
      'title-en' => "Keyword",
      'xpath' => '//gmd:identificationInfo/*/gmd:descriptiveKeywords',
      'svars'=> [
        'value'=> [
          'xpath' => '//gmd:descriptiveKeywords/*/gmd:keyword/gco:CharacterString',
        ],
        'thesaurusTitle'=> [
          'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:title/gco:CharacterString',
        ],
        'thesaurusDate'=> [
          'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:date/*/gmd:date/gco:Date',
        ],
        'thesaurusDateType'=> [
          'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:date/*/gmd:dateType'
              .'/gmd:CI_DateTypeCode/@codeListValue',
        ],
        'thesaurusId'=> [
          'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:identifier/*/gmd:code/gmx:Anchor/@xlink:href',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],
  
    // 4. SITUATION GÉOGRAPHIQUE - 4. GEOGRAPHIC LOCATION
    // 4.1. Rectangle de délimitation géographique - 4.1. Geographic bounding box
    // revoir le path pour les services
    'dcat:bbox' => [
      'title-fr' => "Rectangle de délimitation géographique",
      'title-en' => "Geographic bounding box",
      'xpath' => '//gmd:identificationInfo/*/gmd:extent/*/gmd:geographicElement/gmd:EX_GeographicBoundingBox',
      'svars'=> [
        'westLon'=> [
          'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:westBoundLongitude/gco:Decimal',
        ],
        'eastLon'=> [
          'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:eastBoundLongitude/gco:Decimal',
        ],
        'southLat'=> [
          'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:southBoundLatitude/gco:Decimal',
        ],
        'northLat'=> [
          'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:northBoundLatitude/gco:Decimal',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '0..*' ],
    ],
  
    // 5. RÉFÉRENCE TEMPORELLE
    // 5.1. Étendue temporelle - 5.1. Temporal extent
    'dct:temporal' => [
      'title-fr' => "Étendue temporelle",
      'title-en' => "Temporal extent",
      'xpath' => '//gmd:identificationInfo/*/gmd:extent/*/gmd:temporalElement/*/gmd:extent/gml:TimePeriod',
      'svars'=> [
        'begin' => [
          'xpath' => '//gml:TimePeriod/gml:beginPosition',
        ],
        'end' => [
          'xpath' => '//gml:TimePeriod/gml:endPosition',
        ],
      ],
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // 5.2. Date de publication - 5.2. Date of publication
    'dct:issued' => [
      'title-fr' => "Date de publication",
      'title-en' => "Date of publication",
      // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='publication']/*/date
      'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
          ."/@codeListValue='publication']/gmd:CI_Date/gmd:date/gco:Date",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // 5.3. Date de dernière révision - 5.3. Date of last revision
    'dct:modified' => [
      'title-fr' => "Date de dernière révision",
      'title-en' => "Date of last revision",
      // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='revision']/*/date
      'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
          ."/@codeListValue='revision']/gmd:CI_Date/gmd:date/gco:Date",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    // 5.4. Date de création - 5.4. Date of creation
    'dct:created' => [
      'title-fr' => "Date de création",
      'title-en' => "Date of creation",
      // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='creation']/*/date
      'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
          ."/@codeListValue='creation']/gmd:CI_Date/gmd:date/gco:Date",
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],

    // 6. QUALITÉ ET VALIDITÉ - 6. QUALITY AND VALIDITY
    // 6.1. Généalogie - 6.1. Lineage
    'dct:provenance' => [
      'title-fr' => "Généalogie",
      'title-en' => "Lineage",
      'xpath' => '//gmd:dataQualityInfo/*/gmd:lineage/*/gmd:statement/gco:CharacterString',
      'text' => 1,
      'multiplicity' => [ 'data' => 1 ],
    ],
    // 6.2. Résolution spatiale - 6.2. Spatial resolution
    'spatialResolutionScaleDenominator' => [
      'title-fr' => "Résolution spatiale : dénominateur de l'échelle",
      'title-en' => "Spatial resolution: scale denominator",
      'xpath' => '//gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:equivalentScale/*/gmd:denominator/gco:Integer',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],
    'spatialResolutionDistance' => [
      'title-fr' => "Résolution spatiale : distance",
      'title-en' => "Spatial resolution: distance",
      'xpath' => '//gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:distance',
      'svars'=> [
        'unit'=> [
          'xpath' => '//gmd:distance/gco:Distance/@uom',
        ],
        'value'=> [
          'xpath' => '//gmd:distance/gco:Distance',
        ],
      ],
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],

    // 7. CONFORMITÉ - 7. CONFORMITY
    // 7.1. Spécification - 7.1. Specification
    // dataQualityInfo/*/report/*/result/*/specification
    'dct:conformsTo' => [
      'title-fr' => "Spécification",
      'title-en' => "Specification",
      'xpath' => '//gmd:dataQualityInfo/*/gmd:report/*/gmd:result',
      'svars'=> [
        'specificationDate'=> [
          'xpath' => '//gmd:result/*/gmd:specification/*/gmd:date/*/gmd:date/gco:Date',
        ],
        'specificationTitle'=> [
          'xpath' => '//gmd:result/*/gmd:specification/*/gmd:title/gco:CharacterString', // Anchor !!
        ],
        // 7.2. Degré - 7.2. Degree
        // dataQualityInfo/*/report/*/result/*/pass
        'pass'=> [
          'xpath' => '//gmd:result/*/gmd:pass/gco:Boolean',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],

    // 8. CONTRAINTES EN MATIÈRE D’ACCÈS ET D’UTILISATION - 8. CONSTRAINT RELATED TO ACCESS AND USE
    // 8.1. Conditions applicables à l’accès et à l’utilisation - 8.1. Conditions applying to access and use
    'useLimitation' => [
      'title-fr' => "Conditions d'utilisation",
      'title-en' => "Use conditions",
      'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/*/gmd:useLimitation/gco:CharacterString',
      'text' => 1,
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],
  
    // 8.2. Restrictions concernant l’accès public - 8.2. Limitations on public access
    'accessConstraints' => [
      'title-fr' => "Restrictions concernant l’accès public",
      'title-en' => "Limitations on public access",
      'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/gmd:MD_LegalConstraints',
      // identificationInfo[1]/*/resourceConstraints/*/accessConstraints
      'svars'=> [
        'code'=> [
          'xpath' => '//gmd:MD_LegalConstraints/gmd:accessConstraints/gmd:MD_RestrictionCode/@codeListValue',
        ],
        // identificationInfo[1]/*/resourceConstraints/*/otherConstraints
        'others'=> [
          'xpath' => '//gmd:MD_LegalConstraints/gmd:otherConstraints/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],
    // identificationInfo[1]/*/resourceConstraints/*/classification
    'classification' => [
      'title-fr' => "Contrainte de sécurité intéressant la Défense nationale",
      'title-en' => "Classification",
      'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/*/gmd:classification'
          .'/gmd:MD_ClassificationCode/@codeListValue',
      'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
    ],

    // 9. ORGANISATIONS RESPONSABLES DE L’ÉTABLISSEMENT, DE LA GESTION, DE LA MAINTENANCE ET DE LA DIFFUSION DES SÉRIES
    //    ET DES SERVICES DE DONNÉES GÉOGRAPHIQUES
    // 9. ORGANISATIONS RESPONSIBLE FOR THE ESTABLISHMENT, MANAGEMENT, MAINTENANCE AND DISTRIBUTION OF SPATIAL DATA SETS
    //    AND SERVICE
    // 9.1. Partie responsable - 9.1. Responsible party
    'responsibleParty' => [
      'title-fr' => "Partie responsable",
      'title-en' => "Responsible party",
      'xpath' => '//gmd:identificationInfo/*/gmd:pointOfContact',
      'svars' => [
        'organisationName' => [
          'xpath' => '//gmd:pointOfContact/*/gmd:organisationName/gco:CharacterString', // Free Text Element !!!
          //'stdFunc' => 'stdOrganisationName',
        ],
        // 9.2. Rôle de la partie responsable - 9.2. Responsible party role
        'role' => [
          'xpath' => '//gmd:pointOfContact/*/gmd:role/gmd:CI_RoleCode/@codeListValue',
          //'stdFunc' => true,
        ],
        // Ajout 20/2/2021
        'individualName' => [
          'xpath' => '//gmd:pointOfContact/*/gmd:individualName/gco:CharacterString',
        ],
        'electronicMailAddress' => [
          'xpath' => '//gmd:pointOfContact/*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],
  
    // 10. Métadonnées concernant les métadonnées - METADATA ON METADATA
    // 10.1. Point de contact des métadonnées - 10.1. Metadata point of contact
    'mdContact' => [
      'title-fr' => "Point de contact des métadonnées",
      'title-en' => "Metadata point of contact",
      'xpath' => '//gmd:contact',
      'svars'=> [
        'organisationName'=> [
          'xpath'=> '//gmd:contact/*/gmd:organisationName/gco:CharacterString',
        ],
        // Ajout 20/2/2021
        'individualName' => [
          'xpath' => '//gmd:contact/*/gmd:individualName/gco:CharacterString',
        ],
        'electronicMailAddress' => [
          'xpath' => '//gmd:contact/*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
        ],
      ],
      'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
    ],
    // 10.2. Date des métadonnées - 10.2. Metadata date
    'mdDate' => [
      'title-fr' => "Date des métadonnées",
      'title-en' => "Metadata date",
      'xpath' => '//gmd:dateStamp/gco:DateTime',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    // 10.3. Langue des métadonnées - 10.3. Metadata language
    'mdLanguage' => [
      'title-fr' => "Langue des métadonnées",
      'title-en' => "Metadata language",
      'xpath' => '//gmd:MD_Metadata/gmd:language/gmd:LanguageCode',
      'svars'=> [
        'codeList'=> [
          'xpath'=> '//gmd:LanguageCode/@codeList',
        ],
        'codeListValue'=> [
          'xpath'=> '//gmd:LanguageCode/@codeListValue',
        ],
      ],
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
    'mdLanguageAsString' => [
      'title-fr' => "Langue des métadonnées (codé comme gco:CharacterString)",
      'title-en' => "Metadata language",
      'xpath' => '//gmd:MD_Metadata/gmd:language/gco:CharacterString',
      'multiplicity' => [ 'data' => 1, 'service' => 1 ],
    ],
  ];

  // liste des variables correspondant à un fileId sous la forme 'nom de la variable' => 'nom du sous-champ'
  static private $fileIdVariables = [
    'aggregationInfo' => 'val',
    'operatesOn' => 'val',
  ];
  
  /*PhpDoc: methods
  name: setNameSpaces
  title: function setNameSpaces($md) - definit les espaces de noms
  doc: |
    Retourne la table des espaces de noms définis dans le XML.
    SimpleXML nécessite d'enregistrer les espaces de noms avant d'effectuer un xpath. A priori, il faut enregistrer tous
    les espaces de noms utilisés dans le XML.
    Dans la plupart des cas les prefixes et espaces de noms sont identiques car standardisés.
    Il peut cependant arriver qu'un prefixe soit remplacé par le prefixe vide.
    Dans ce cas, SimpleXML impose d'enregistrer un prefixe non vide.
    Pour gérer ces cas, j'ai défini les (prefix,espaces de noms) utilisés dans les xpath dans la variable statique
    namespaces et lorsque pour un espace de nom le prefix n'est pas celui que j'utilise alors je force le prefix que j'utilise.
  */
  static function setNameSpaces(SimpleXMLElement $md) {
    foreach ($md->getDocNamespaces(true) as $prefix => $namespace) {
      //echo "namespace '$prefix' '$namespace'<br>\n";
      $nameSpaces[$prefix] = $namespace;
      if (!isset(self::$namespaces[$namespace]) or ($prefix == self::$namespaces[$namespace]))
        $md->registerXPathNamespace ($prefix, $namespace);
      else {
        //echo "Pour $namespace prefix '$prefix' changé en '",self::$namespaces[$namespace],"'<br>\n";
        $md->registerXPathNamespace (self::$namespaces[$namespace], $namespace);
      }
    }
    return $nameSpaces;
  }

  /*PhpDoc: methods
  name:  extractComplexValue
  title: function extractComplexValue($vardef, $xmlstring) - extrait les éléments pour une variable complexe
  doc: |
    Extrait du fragment XML les valeurs correspondant à une variable complexe et structure le résultat sous la forme
    d'une liste d'éléments
    En cas d'erreur sur la structure XML, l'exception envoyée par new SimpleXMLElement() n'est pas interceptée.
  journal: |
    21/2/2021:
      - chgt de la structure retournée
  */
  static function extractComplexValue(string $varname, array $vardef, string $xmlstring): array {
    //echo "extractComplexValue($varname)\n";
    //echo str_replace('<','&lt;',$xmlstring);
    $subsxe = new SimpleXMLElement($xmlstring);
    self::setNameSpaces($subsxe);
    $vals = []; // [ $svarName => val ]
    foreach ($vardef['svars'] as $svarName => $svar) {
      //echo "xpath sur $varname/$svarName\n";
      $res = @$subsxe->xpath($svar['xpath']);
      if (is_array($res)) {
        foreach ($res as $val) {
          $val = trim($val);
          //echo "val=$val<br>\n";
          if (!$val)
            continue;
          $vals[$svarName] = $val;
        }
      }
    }
    //echo "<pre>vals="; print_r($vals); echo "</pre>\n";
    
    return $vals;
  }
  
  /*PhpDoc: methods
  name:  extract
  title: function extract($xml) - construit à partir d'un fragment XML la fiche sous la forme d'un mdrecord
  doc: |
    Extrait du fragment XML les valeurs qui sont structurées sous la forme d'un mdrecord dont le type est défini par le type du
    résultat de la méthode en fonction de mdvars.
    Dans le résultat:
    - les noms de sous-variables sont renseignés ssi ils sont définis dans mdvars,
    - les sous-valeurs sont définies si (i) les sous-variables sont définies dans mdvars et (ii) une valeur correpond dans le XML
    En cas d'erreur sur la structure XML, l'exception envoyée par new SimpleXMLElement() est transmise.
  journal: |
    21/2/2021:
      - chgt de la structure retournée
    21/9/2015:
      - Prise en compte de l'utilisation ou nom du prefixe csw:
  */
  static function extract(string $id, string $xmlstring): array {
    //echo "extract(id=$id)\n";
    //echo str_replace('<','&lt;', $xmlstring),"<br>\n";
    try {
      $md = new SimpleXMLElement($xmlstring);
    }
    catch (Exception $e) { 
      throw new Exception("Erreur sur new SimpleXMLElement");
    }
    $nameSpaces = self::setNameSpaces($md);
    
    $mdrecord = [];
    // calcul des valeurs à partir des xpath
    foreach (self::$mdvars as $varname => $vardef) {
      //if ($varname <> 'keyword') continue;
      //echo "<pre>varname=$varname\nvardef="; print_r($vardef); echo "</pre>\n";
      if (!isset($vardef['svars'])) { // c'est une variable simple
        $vals = []; // ensemble de valeurs pour cette variable
        $xpatheval = @$md->xpath($vardef['xpath']);
        if ($xpatheval) {
          foreach ($xpatheval as $val) {
            $val = trim($val);
            //echo "$varname -> $val<br>\n";
            if ($val && !in_array($val, $vals))
              $vals[] = $val;
          }
          $mdrecord[$varname] = $vals;
        }
      }
      else { // sinon c'est une variable complexe
        //echo "$varname est une variable complexe<br>\n";
        $xpatheval = @$md->xpath($vardef['xpath']);
        if ($xpatheval) {
          //echo "<pre>xpatheval="; print_r($xpatheval); echo "</pre>\n";
          //echo "<pre>nameSpaces="; print_r($nameSpaces); echo "</pre>\n";
          // 21/9/2015: Prise en compte de l'utilisation ou nom du prefix csw:
          foreach ($xpatheval as $val) {
            $xmlsubstring = (isset($nameSpaces['csw'])? "<csw:Value" : "<Value");
            foreach ($nameSpaces as $k=>$v)
              $xmlsubstring .= ' xmlns'.($k?':'.$k:'')."=\"$v\"\n";
            $xmlsubstring .= '>'.$val->asXML() . (isset($nameSpaces['csw'])? "</csw:Value>" : "</Value>");
            //echo "xmlsubstring=",str_replace('<','&lt;',$xmlsubstring),"<br>\n";
            $mdelements = self::extractComplexValue($varname, $vardef, $xmlsubstring);
            //echo "<pre>mdelements="; print_r($mdelements); echo "</pre>\n";
            if ($mdelements) {
              if (isset($mdrecord[$varname]))
                $mdrecord[$varname][] = $mdelements;
              else
                $mdrecord[$varname] = [$mdelements];
            }
          }
        }
      }
      //echo "mdrecord=<pre>"; print_r($mdrecord); echo "</pre>\n";
    }
    return $mdrecord;
  }
  
  /*PhpDoc: methods
  name:  show
  title: function show($mdrecord, $hreffid=null) - affiche le mdrecord
  doc: |
    Affiche une fiche de MD structurée sous la forme d'un mdrecord.
    Si hreffid est défini, les champs correspondant à un fid sont remplacés par <a href='${hreffid}${fid}'>$fid</a>
  */
  static function show($mdrecord, $hreffid=null) {
//    echo "<pre>"; print_r($mdrecord); echo "</pre>\n";
    if ($hreffid) {
      foreach (self::$fileIdVariables as $var => $val)
        if (isset($mdrecord[$var])) {
          foreach ($mdrecord[$var] as $num => $mdelt) {
            $url = $hreffid.urlencode($mdelt[$val]);
            $mdrecord[$var][$num][$val] = "<a href='$url'>".$mdelt[$val]."</a>";
          }
        }
    }
    
    $fileIdentifier = $mdrecord['fileIdentifier'][0]['val'];
    if (isset($mdrecord['title'][0]['val'])) {
      $title = $mdrecord['title'][0]['val'];
      echo "<h2>$title</h2>\n";
    }
    $mdtype = $mdrecord['type'][0]['val'];
    echo "<table border=1>\n";
// la liste des variables est constituée en premier des variables de mdvars complétée par les autres variables trouvées dans mdrecord
    $varnames = array_keys(self::$mdvars);
    foreach (array_keys($mdrecord) as $varname)
      if (!in_array($varname, $varnames))
        $varnames[] = $varname;
    foreach ($varnames as $varname) {
      $multiplicity = null;
      if (isset(self::$mdvars[$varname]['multiplicity'][($mdtype=='service'?'service':'data')]))
        $multiplicity = self::$mdvars[$varname]['multiplicity'][($mdtype=='service'?'service':'data')];
// j'affiche une ligne ssi soit varname est définie dans mdrecord soit la variable est obligatoire dans mdvars
      if (isset($mdrecord[$varname]) or in_array($multiplicity,[1,'1..*'])) {
        echo "<tr><td>$varname",($multiplicity ? " ($multiplicity)" : ''),"</td><td>\n";
        if (isset($mdrecord[$varname]) and (count($mdrecord[$varname])==1) and !isset($mdrecord[$varname][0]['svar'])) {
// valeur simple mono-valuée
          if (isset(self::$mdvars[$varname]['texte'])) {
            echo str_replace("\n","<br>\n",$mdrecord[$varname][0]['val']);
          } elseif (isset($mdrecord[$varname][0]['origval'])) {
            echo "<u>",$mdrecord[$varname][0]['val'],"</u>",
                 '<strike>',$mdrecord[$varname][0]['origval'],'</strike>';
          } else
            echo $mdrecord[$varname][0]['val'];
        } elseif (isset($mdrecord[$varname]) and !isset($mdrecord[$varname][0]['svar'])) {
// valeur simple multi-valuée
          echo "<table border=1>";
          foreach ($mdrecord[$varname] as $mdelement)
            echo "<tr><td>$mdelement[val]</td></tr>";
          echo "</table>";
        } elseif (isset($mdrecord[$varname])) {
// valeur complexe avec plusieurs sous-valeurs
          $svar = [isset($mdrecord[$varname][0]['svar0']) ? $mdrecord[$varname][0]['svar0'] : null,
                   isset($mdrecord[$varname][0]['svar'])  ? $mdrecord[$varname][0]['svar']  : null,
                   isset($mdrecord[$varname][0]['svar2']) ? $mdrecord[$varname][0]['svar2'] : null,
                   isset($mdrecord[$varname][0]['svar3']) ? $mdrecord[$varname][0]['svar3'] : null];
          echo "<table border=1>",
               ($svar[0] ? "<th>$svar[0]</th>":''),
               "<th>$svar[1]</th>",
               ($svar[2] ? "<th>$svar[2]</th>":''),
               ($svar[3] ? "<th>$svar[3]</th>":''),"\n";
          foreach ($mdrecord[$varname] as $mdelement) {
            echo "<tr>";
            if ($svar[0]) {
              if (isset($mdelement['origsval0']))
                echo "<td><u>",$mdelement['sval0'],'</u><strike>',$mdelement['origsval0'],'</strike>',"</td>";
              elseif (isset($mdelement['sval0']))
                echo "<td>",$mdelement['sval0'],"</td>";
              else
                echo "<td>","</td>";
            }
            if (isset($mdelement['origval']))
              echo "<td><u>",$mdelement['val'],'</u><strike>',$mdelement['origval'],'</strike>',"</td>";
            else
              echo "<td>",$mdelement['val'],"</td>";
            if (isset($mdelement['sval2']))
              echo "<td>".$mdelement['sval2']."</td>";
            if (isset($mdelement['sval3']))
              echo "<td>".$mdelement['sval3']."</td>";
            echo "</tr>";
          }
          echo "</table>";
        }
        echo "</td></tr>\n";
        unset($mdrecord[$varname]);
      }
    }
    echo "</table>\n";
  }
};

// le code suivant n'est exécuté que si ce fichier est directement appelé dans un browser
if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>mdvars</title></head><body>\n";

// showmdvars - affichage de la liste des variables de mdvars

echo "<h2>Eléments de MD</h2>
Champs de métadonnées INSPIRE/ISO 19139 utilisés dans geocat.<br>
Dans le tableau ci-dessous:
<ul>
<li><b>nom</b> est le nom court utilisé dans l'affichage des fiches,
<li><b>titre fr</b> est généralement le titre du champ utilisé dans la version française du règlement Inspire sur les métadonnées,
<li><b>titre en</b> est généralement le titre du champ utilisé dans la version anglaise du règlement Inspire sur les métadonnées,
<li><b>m d</b> est la cardinalité du champ pour les fiches de métadonnées de séries de données ou d'ensembles de séries de données,
<li><b>m s</b> est la cardinalité du champ pour les fiches de métadonnées de service,
<li><b>xpath</b> est le xpath utilisé pour extraire les champs des fiches ISO 19139 moissonnées dans les catalogues.
</ul>
Les champs suivants ont été ajoutés aux champs Inspire : fileIdentifier, parentIdentifier, distributionFormat, characterSet,
spatialRepresentationType.
</p>
<table border=1><th>nom</th><th>titre fr</th><th>titre en</th><th>m d</th><th>m s</th>\n",
       (isset($_GET['withxpath'])? "<th><tt>xpath</tt></th>" : '');
foreach (Mdvars::$mdvars as $varname => $mdvar) {
  echo "<tr><td>$varname</td><td>",$mdvar['title-fr'],"</td><td>",$mdvar['title-en'],"</td>",
       "<td>",(isset($mdvar['multiplicity']['data'])?$mdvar['multiplicity']['data']:''),"</td>",
       "<td>",(isset($mdvar['multiplicity']['service'])?$mdvar['multiplicity']['service']:''),"</td>",
       (isset($_GET['withxpath'])? "<td><tt>$mdvar[xpath]</tt></td>" : ''),
       "</tr>\n";
  if (isset($mdvar['svars'])) {
    foreach ($mdvar['svars'] as $svarName => $svar)
      echo "<tr><td>&nbsp;&nbsp;&nbsp;$svarName</td>",
           (isset($_GET['withxpath'])? "<td colspan=4></td><td><tt>".$svar['xpath']."</tt></td>" : ''),
           "</tr>\n";
  }
}
echo "</table>
<br>Des champs complémentaires peuvent apparaître dans les fiches, ils sont calculés a posteriori. Il s'agit principalement des champs suivants :
<ul>
<li>attributedTo : sélection de responsibleParty selon <a href='attrto.html'>les principes d'affectation d'une ressource</a>.
</ul>
<a href='?withxpath=1'>avec xpath</a>
</body></html>\n";
?>