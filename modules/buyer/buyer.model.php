<?php

class buyer_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function deleteAttachFile($id)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE id='$id'");
    @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
    $this->registerLog($_GET['id'], 'attachments', 'name');
    $this->db->query("DELETE FROM attachments WHERE id='$id'");
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $ids = explode(',', $group);
      foreach($ids as $id){
        $this->deleteExec($id);
      }
    } else{
      $this->verifyDelete("SELECT id FROM statement_in WHERE from_id='$id' AND from_type='buildrenter'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE from_id='$id' AND from_type='buildrenter'");
      $this->verifyDelete("SELECT id FROM rent_contracts WHERE JSON_CONTAINS(JSON_EXTRACT(buyer, '$.id[*]'), '\"$id\"', '$')");
      $this->verifyDelete("SELECT id FROM letters WHERE renterid='$id'");
      $this->registerLog($id, 'buyer', 'id');
      $this->db->query("DELETE FROM buyer WHERE id='$id'");
      $this->db->query("DELETE FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,fname,lname,fathname,famname,company,type FROM buyer WHERE email='$q' OR id='$q' OR company LIKE '%$q%' OR fname LIKE '%$q%' OR lname LIKE '%$q%' OR fathname LIKE '%$q%' OR famname LIKE '%$q%'"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => $this->formatClientName($result));
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('buyer', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('buyer', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $buyer = $this->db->get_row("SELECT buyer.*,country.name AS national FROM buyer
    LEFT JOIN country ON(country.id=buyer.nationality)
    WHERE buyer.id='$id'");
    if($buyer['type'] == 'person'){
      $buyer['person'] = '';
      $buyer['fullname'] = $buyer['fname'] . ' ' . $buyer['fathname'] . ' ' . $buyer['lname'] . ' ' . $buyer['famname'];
      $buyer['surname'] = $buyer['fname'] . ' ' . $buyer['fathname'];
    } else{
      $buyer['person'] = $buyer['fname'] . ' ' . $buyer['fathname'] . ' ' . $buyer['lname'] . ' ' . $buyer['famname'];
      $buyer['fullname'] = '[' . $buyer['company'] . '] ' . $buyer['fname'] . ' ' . $buyer['fathname'] . ' ' . $buyer['lname'] . ' ' . $buyer['famname'];
      $buyer['surname'] = $buyer['company'];
    }
    if($buyer['idtype'] == 'idcard'){
      $buyer['idtypename'] = gettext('هوية وطنية');
    } elseif($buyer['idtype'] == 'passport'){
      $buyer['idtypename'] = gettext('جواز سفر');
    } elseif($buyer['idtype'] == 'visa'){
      $buyer['idtypename'] = gettext('إقامة');
    }
    $buyer['billing_info'] = json_decode($buyer['billing_info'], true);
    $buyer['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    return $buyer;
  }

  public function checkUsernameExist($username, $exceptID = false)
  {
    $where = '';
    if($exceptID) {
      $where = 'AND id<>' . $exceptID;
    }
    $bool = $this->db->get_var("SELECT id FROM buyer WHERE username='$username' " . $where);
    return $bool && $bool > 0;
  }

  public function listrecord($where = array(), $paging = true, $inner = '', $select = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT buyer.* $select FROM buyer $inner $where GROUP BY buyer.id ORDER BY buyer.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
