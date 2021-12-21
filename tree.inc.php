<?php
/*PhpDoc:
name: tree.inc.php
title: tree.inc.php - arbre de noeuds
classes:
doc: |
  Créé pour simplifier arbo.inc.php
  Un arbre est constitué de noeuds.
  Dans un noeud, chaque sous-arbre est identifié par une clé.
  Ainsi, tout noeud est identifié (et peut être accédé) par une liste de clés.
  La racine d'un arbre est un noeud particulier portant des propriétés et méthodes spécifiques.
  Une feuille d'un arbre est un noeud n'ayant pas d'enfants.
  Les 2 classes Node et Tree implémentent:
    - l'accès récursif à un noeud par son chemin dans l'arbre (méthode node())
journal: |
  6/11/2021:
    - création
*/

abstract class RootOrNode { // Classe abstraite commune à la racine et aux autres noeuds
  protected array $children=[]; // tableau asso. des noeuds de 1er niveau, chacun comme Node ou objet d'une sous-classe

  function children(): array { return $this->children; } // enfants 
  abstract function node(array $path): Node; // accès à un noeud par son chemin comme liste de clés
};

class Node extends RootOrNode { // Noud intermédiaire
  protected array $path; // chemin d'accès dans l'arbre comme liste de clés
  //protected array $children; // tableau associatif des enfants, chacun comme Node ou objet d'une sous-classe, avec une clé

  function path(): array { return $this->path; }
  function pathAsString(): string { return '/'.implode('/', $this->path); }

  function node(array $path): Node { // accès à un noeud par son chemin comme liste de clés par desc. récursive dans l'arbre 
    //print_r($path);
    $first = array_shift($path);
    if (!isset($this->children[$first]))
      throw new Exception("Erreur dans Node::node() sur '$first'");
    elseif (!$path)
      return $this->children[$first];
    else
      return $this->children[$first]->node($path);
  }

  function nodes(): array { // retourne le concept + ses descendants sous la forme [{pathAsString} => Concept]
    $nodes = [$this->pathAsString()=> $this]; // le concept lui-même est un descendant
    foreach ($this->children as $id => $child) // plus les descendants de ses enfants
      $nodes = array_merge($nodes, $child->nodes());
    return $nodes;
  }
};

class Tree extends RootOrNode { // Racine de l'arbre représentant l'arbre
  
  function node(array $path): Node { // accès à un noeud par son chemin comme liste de clés
    //print_r($path);
    if (count($path)==1)
      return $this->children[$path[0]];
    else {
      $first = array_shift($path);
      return $this->children[$first]->node($path);
    }
  }
  
  // retourne la liste des noeuds (sauf la racine) sous la forme [pathAsString => Concept] en parcourant l'arbre
  function nodes(): array {
    $nodes = [];
    foreach($this->children as $id => $child) {
      $nodes = array_merge($nodes, $child->nodes());
    }
    return $nodes;
  }
};
