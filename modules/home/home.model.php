<?php

class home_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function updateSetting($data)
  {
    foreach($data as $varname => $value){
      $this->db->query("UPDATE setting SET `value`='$value' WHERE varname='$varname'");
    }
  }

  public function readOwners()
  {
    return $this->db->get_results('SELECT * FROM company_owners ORDER BY id ASC');
  }

  public function updateOwners($data)
  {
    return $this->db->insert('company_owners', $data);
  }

  public function resetOwners()
  {
    $this->db->query("TRUNCATE `company_owners`");
  }

  public function deleteAttachment($id)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE id='$id'");
    if(!empty($fileinfo['tempid'])){
      @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
      $this->db->query("DELETE FROM attachments WHERE id='$id'");
    }
    return $fileinfo;
  }

  public function addAttachment($file, $title, $tempid)
  {
    $data = array();
    $data['path'] = $file;
    $data['name'] = $title;
    $data['tempid'] = $tempid;
    return $this->db->insert('attachments', $data);
  }

  public function delFileUploader($name)
  {
    $fileinfo = $this->db->get_row("SELECT * FROM attachments WHERE moduleid='1' AND name='$name'");
    if(!empty($fileinfo)){
      @unlink(ROOT_DIR . '/' . UPLOAD_DIR . '/' . $fileinfo['path']);
      $this->db->query("DELETE FROM attachments WHERE id='$fileinfo[id]'");
    }
  }

  public function addFileUploader($path, $name)
  {
    $originName = explode('.', $name);
    $uniqueName = true;
    $counter = 1;
    while($uniqueName = true){
      $bool = $this->db->get_var("SELECT id FROM attachments WHERE moduleid='1' AND name='$name'");
      if($bool){
        $name = $originName[0] . '_' . $counter . '.' . $originName[1];
        $counter++;
      } else{
        $uniqueName = false;
        break;
      }
    }
    $data = array();
    $data['path'] = $path;
    $data['name'] = $name;
    $data['moduleid'] = 1;
    return $this->db->insert('attachments', $data);
  }

  public function getUploaderFiles()
  {
    return $this->db->get_results('SELECT * FROM attachments WHERE moduleid=1 ORDER BY id DESC');
  }

}
