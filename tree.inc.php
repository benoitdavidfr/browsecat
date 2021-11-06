<?php
/*PhpDoc:
name: tree.inc.php
title: tree.inc.php - arbre de noeuds
classes:
doc: |
  Créé pour simplifier arbo.inc.php
journal: |
  6/11/2021:
    - création
*/

abstract class RootOrNode { // Classe commune pour la racine et les noeuds intermédiaires
  protected array $children=[]; // tableau asso. des noeuds de 1er niveau, chacun comme Node ou objet d'une sous-classe

  function children(): array { return $this->children; } // enfants 
  abstract function node(array $path): Node; // accès à un noeud par son chemin comme liste de clés
};

class Node extends RootOrNode { // Noud intermédiaire
  protected array $path; // chemin d'accès dans l'arbre comme liste de clés
  protected array $children; // tableau associatif des enfants, chacun comme Node ou objet d'une sous-classe, avec un clé

  function path(): array { return $this->path; }
  function pathAsString(): string { return '/'.implode('/', $this->path); }

  function node(array $path): Node { // accès à un noeud par son chemin comme liste de clés
    //print_r($path);
    $first = array_shift($path);
    if (!isset($this->children[$first]))
      throw new Exception("Erreur dans Concept::getChild() sur '$first'");
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

class Tree extends RootOrNode { // 
  
  function node(array $path): Node { // accès à un noeud par son chemin comme liste de clés
    //print_r($path);
    if (count($path)==1)
      return $this->children[$path[0]];
    else {
      $first = array_shift($path);
      return $this->children[$first]->node($path);
    }
  }
  
  // retourne la liste des noeuds (mais pas la racine) sous la forme [pathAsString => Concept] en parcourant l'arbre
  function nodes(): array {
    $nodes = [];
    foreach($this->children as $id => $child) {
      $nodes = array_merge($nodes, $child->nodes());
    }
    return $nodes;
  }
  
};
