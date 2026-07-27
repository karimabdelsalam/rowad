<?php

class citizen_model extends core
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
      $this->verifyDelete("SELECT id FROM statement_in WHERE from_id='$id' AND from_type='citizen'");
      $this->verifyDelete("SELECT id FROM statement_out WHERE from_id='$id' AND from_type='citizen'");
      $this->registerLog($id, 'citizens', 'title');
      $this->db->query("DELETE FROM citizens WHERE id='$id'");
    }
  }

  public function searchQuery($q)
  {
    $results = $this->db->get_results($this->Paging("SELECT id,title FROM citizens WHERE title LIKE '%$q%'"));
    $search = array();
    $search['results'] = array();
    if($results){
      foreach($results as $result){
        $search['results'][] = array('id' => $result['id'], 'text' => '#' . $result['id'] . ' - ' . $result['title']);
      }
    }
    $search['pagination'] = ['more' => $_GET['callpage'] >= $this->paging['pages']  ? false : true];
    return $search;
  }

  public function addrecord($data)
  {
    return $this->db->insert('citizens', $data);
  }

  public function updaterecord($data, $id)
  {
    return $this->db->update('citizens', $data, array('id' => $id));
  }

  public function readrecord($id)
  {
    $data = $this->db->get_row("SELECT * FROM citizens WHERE id='$id'");
    $data['files'] = $this->db->get_results("SELECT * FROM attachments WHERE objectid='$id' AND moduleid='" . MODULE_ID . "'");
    return $data;
  }

  public function listrecord($where = array(), $paging = true, $inner = '')
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT citizens.*,country.name AS national FROM citizens INNER JOIN country ON(country.id=citizens.nationality) $inner $where ORDER BY citizens.id DESC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
