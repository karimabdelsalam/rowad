<?php

class income_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function readmMethods()
  {
    return $this->db->get_results('SELECT * FROM statement_methods');
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'AND ' . implode(' AND ', $where);
    }
    $sql = "SELECT statement_in.*,statement_methods.title AS method,outgoings_types.title AS type FROM statement_in
    INNER JOIN outgoings_types ON(outgoings_types.id=statement_in.typeid)
    INNER JOIN statement_methods ON(statement_methods.name=statement_in.from_type)
    $inner WHERE 1 $where ORDER BY statement_in.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);

    if(!empty($results)){
      $results[0]['totals'] = $this->db->get_row($this->removeSelectStat($sql, 'COUNT(statement_in.id) AS totalCounts, SUM(statement_in.amount) AS totalAmounts'));
    }

    if($results){
      $results[0]['report']['pay_method'] = $this->db->get_results("SELECT statement_in.pay_method AS title, COUNT(statement_in.pay_method) AS counterValue FROM statement_in WHERE 1 $where GROUP BY statement_in.pay_method");
      $results[0]['report']['from_type'] = $this->db->get_results("SELECT statement_methods.title, COUNT(statement_in.from_type) AS counterValue FROM statement_in INNER JOIN statement_methods ON(statement_methods.name=statement_in.from_type) WHERE 1 $where GROUP BY statement_in.from_type");
      $results[0]['report']['typeid'] = $this->db->get_results("SELECT outgoings_types.title, COUNT(statement_in.typeid) AS counterValue FROM statement_in INNER JOIN outgoings_types ON(outgoings_types.id=statement_in.typeid) WHERE 1 $where GROUP BY statement_in.typeid");
    }

    return $results;
  }

}
