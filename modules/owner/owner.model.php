<?php

class owner_model extends core
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
    } else {
      $this->verifyDelete("SELECT id FROM statement_in WHERE from_id='$id' AND from_type='buildowner'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE from_id='$id' AND from_type='buildowner'");
      $this->verifyDelete("SELECT id FROM sell_contracts WHERE JSON_CONTAINS(JSON_EXTRACT(buyer, '$.id[*]'), '\"$id\"', '$')");
      $this->registerLog($id, 'owner', 'id');
      $this->db->query("DELETE FROM owner WHERE id='$id'");
      $this->db->query("DELETE FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");

      $owner = $this->db->get_row("SELECT userid FROM owner WHERE id='$id'");
      $this->deleteUser($owner['userid']);
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,fname,lname,fathname,famname FROM owner WHERE (fname LIKE '%$q%' OR lname LIKE '%$q%' OR fathname LIKE '%$q%' OR famname LIKE '%$q%' OR email='$q' OR id='$q')"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['fname'] . ' ' . $result['fathname'] . ' ' . $result['lname'] . ' ' . $result['famname']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('owner', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('owner', $data, array('id' => $id));
  }

  public function getBuildsOwns($ownerid)
  {
    $results = $this->db->get_results("SELECT id FROM building WHERE JSON_CONTAINS(JSON_EXTRACT(owner, '$.id[*]'), '\"$ownerid\"', '$')");
    $buildIDs = [];
    if($results){
      foreach($results as $result){
        $buildIDs[] = $result['id'];
      }
    }
    return implode(',', $buildIDs);
  }

  public function readrecord($id)
  {
    $owner = $this->db->get_row("SELECT owner.*,country.name AS national FROM owner LEFT JOIN country ON(country.id=owner.nationality) WHERE owner.id='$id'");
    $owner['fullname'] = $owner['fname'] . ' ' . $owner['fathname'] . ' ' . $owner['lname'] . ' ' . $owner['famname'];
    $owner['surname'] = $owner['fname'] . ' ' . $owner['fathname'];
    if($owner['idtype'] == 'idcard'){
      $owner['idtypename'] = gettext('هوية وطنية');
    } elseif($owner['idtype'] == 'passport'){
      $owner['idtypename'] = gettext('جواز سفر');
    } elseif($owner['idtype'] == 'visa'){
      $owner['idtypename'] = gettext('إقامة');
    }
    $owner['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    return $owner;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)) {
      $where = '';
    } else {
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT owner.*,COUNT(building.id) AS buildsCount FROM owner
    LEFT JOIN building ON(JSON_SEARCH(building.owner, 'one', owner.id, null, '$.id[*]') IS NOT NULL)
    $inner $where GROUP BY owner.id ORDER BY owner.id DESC";
    if($paging) {
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
