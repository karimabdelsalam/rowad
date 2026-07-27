<?php

namespace ownerarea;

class rent_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function hasAuthContract($id)
  {
    return $this->db->get_var("SELECT rent_contracts.id FROM rent_contracts
      INNER JOIN building ON(building.id=rent_contracts.buildid)
      WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL AND rent_contracts.id = $id");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT rent_contracts.*,building.title AS build FROM rent_contracts INNER JOIN building ON(building.id=rent_contracts.buildid) $inner $where GROUP BY rent_contracts.id ORDER BY rent_contracts.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $buyerMod = $this->auto_load('buyer');
      foreach($results as $key => $result){
        $results[$key]['buyer'] = json_decode($results[$key]['buyer'], true);
        if(!empty($results[$key]['buyer']['id'])){
          foreach($results[$key]['buyer']['id'] as $key2 => $buyer){
            $buyername = $buyerMod->readrecord($results[$key]['buyer']['id'][$key2]);
            $results[$key]['buyer']['name'][$key2] = '#' . $buyername['id'] . ' - ' . $buyername['fullname'];
          }
        }
      }
    }
    return $results;
  }

}
