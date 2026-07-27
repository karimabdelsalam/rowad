<?php

class taxes_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function getBuilding($id)
  {
    return $this->db->get_row("SELECT id,title FROM building WHERE id='$id'");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $wheresql = 'WHERE transactions.typeid IN(\'tax\',\'owner_tax\')';
    if(! empty($where)){
      $wheresql .= 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT transactions.*,transaction_types.type AS paymenttype,building.owner,building.title AS build FROM transactions
    INNER JOIN building ON(building.id=transactions.buildid)
    INNER JOIN transaction_types ON(transaction_types.id=transactions.typeid) $inner $wheresql ORDER BY transactions.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = $this->db->get_var("SELECT SUM(amount) FROM transactions $inner $wheresql");
    }
    return $results;
  }

}
