<?php

class rights extends rights_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module);
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['cycle'] = $_POST['cycle'];
      $data['content'] = json_encode($this->cleanArray($_POST['content']), JSON_UNESCAPED_UNICODE);
      $rightsid = $this->addrecord($data);
      $this->registerLog($rightsid);
      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $rightsid, 'title' => $_POST['title'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['cycle'] = $_POST['cycle'];
      $data['content'] = json_encode($this->cleanArray($_POST['content']), JSON_UNESCAPED_UNICODE);
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "title LIKE '%$_GET[title]%'";
    }
    if($_GET['cycle']){
      $where[] = "cycle='$_GET[cycle]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}