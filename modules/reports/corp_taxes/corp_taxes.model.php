<?php

class corp_taxes_model extends core
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
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT transactions.*,transaction_types.type AS transtype,building.owner,building.title AS build
    ,owner.fname,owner.lname,owner.fathname,owner.famname,payment_types.title AS paytype,payments.paydate AS paymentdate
    FROM transactions
    LEFT JOIN owner ON(owner.id=transactions.ownerid)
    LEFT JOIN payments ON(payments.id=transactions.paymentid)
    LEFT JOIN payment_types ON(payment_types.name=payments.type)
    INNER JOIN building ON(building.id=transactions.buildid)
    INNER JOIN transaction_types ON(transaction_types.id=transactions.typeid) $inner WHERE 1 $where GROUP BY transactions.id ORDER BY transactions.gone_date ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = $this->db->get_var("SELECT SUM(amount) FROM transactions $inner WHERE 1 $where");
    }
    return $results;
  }

}
