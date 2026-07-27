<?php

namespace ownerarea;

class transactions_model extends \core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function listTypes()
  {
    return $this->db->get_results('SELECT * FROM transaction_types');
  }

  public function hasAuthStatIn($id)
  {
    return $this->db->get_var("SELECT statement_in.id FROM statement_in
      INNER JOIN building ON(building.id=statement_in.buildid)
      WHERE JSON_SEARCH(building.owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL AND statement_in.id = $id");
  }

  public function ownerBuilds()
  {
    return $this->db->get_results("SELECT id,title FROM building WHERE JSON_SEARCH(owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL");
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    $where[] = "transactions.ownerid='$_SESSION[ownerid]'";
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
    INNER JOIN transaction_types ON(transaction_types.id=transactions.typeid) $inner WHERE 1 $where GROUP BY transactions.id ORDER BY transactions.paydate, transactions.id ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    if($results){
      $results[0]['totalAmounts'] = 0;
      $results[0]['total_credit'] = $results[0]['total_debit'] = 0;
      $totals = $this->db->get_results("SELECT SUM(transactions.`amount`) AS total,transactions.`direction` FROM transactions WHERE 1 $where GROUP BY transactions.`direction`");
      foreach($totals as $total){
        $results[0]['total_' . $total['direction']] = $total['total'];
      }
      $results[0]['totalAmounts'] = $results[0]['total_credit'] + $results[0]['total_debit'];
    }
    return $results;
  }

}
