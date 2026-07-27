<?php

namespace clientarea;

class sell_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function hasAuthContract($id)
  {
    return $this->db->get_var("SELECT sell_contracts.id FROM sell_contracts
      INNER JOIN building ON(building.id=sell_contracts.buildid)
      WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL AND sell_contracts.id = $id");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "JSON_SEARCH(sell_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL";
    $where[] = "sell_contracts.archive='0'";
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT sell_contracts.*,building.title AS build FROM sell_contracts INNER JOIN building ON(building.id=sell_contracts.buildid) $inner $where ORDER BY sell_contracts.id DESC";
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
            $results[$key]['buyer']['name'][$key2] = $buyername['fullname'];
          }
        }
      }
    }
    return $results;
  }

}
