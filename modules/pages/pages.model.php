<?php

class pages_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function getPositions()
  {
    return $this->db->get_results("SELECT * FROM pages_position ORDER BY id ASC");
  }

  public function markasActive($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE pages SET active='1' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE pages SET active='1' WHERE id='$id'");
    }
  }

  public function markasinActive($id, $group = array())
  {
    if(!empty($group)){
      $this->db->query("UPDATE pages SET active='0' WHERE id IN($group)");
    } else{
      $this->db->query("UPDATE pages SET active='0' WHERE id='$id'");
    }
  }

  public function deleteExec($id, $group = '')
  {
    if(!empty($group)){
      $this->db->query("DELETE FROM pages WHERE id IN($group)");
    } else{
      $this->db->query("DELETE FROM pages WHERE id='$_GET[id]'");
    }
  }

  public function addPage($data)
  {
    return $this->db->insert('pages', $data);
  }

  public function savePage($data, $id)
  {
    return $this->db->update('pages', $data, array('id' => $id));
  }

  public function readPage($id)
  {
    return $this->db->get_row("SELECT * FROM pages WHERE id='$id'");
  }

  public function listPages($where = array(), $paging = true)
  {
    if(empty($where)){
      $where = '';
    } else{
      $where = 'WHERE ' . implode(' AND ', $where);
    }
    $sql = "SELECT pages.*,pages_position.position,COUNT(pages2.id) AS pagecount FROM pages
    INNER JOIN pages_position ON(pages_position.id=pages.positionid)
    LEFT JOIN pages AS pages2 ON(pages.id=pages2.parent)
     $where GROUP BY pages.id ORDER BY pages.listorder ASC";
    if($paging){
      $sql = $this->Paging($sql);
    }
    $results = $this->db->get_results($sql);
    return $results;
  }

}
