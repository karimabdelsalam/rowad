<?php

class buycats extends buycats_model
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

  public function multiadd()
  {
    if($_POST){
      foreach($_POST['title'] as $name){
        if(empty($name)) continue;
        $data = array();
        $data['title'] = $name;
        $recordid = $this->addrecord($data);
        $this->registerLog($recordid);
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Output();
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $buycatsid = $this->addrecord($data);
      $this->registerLog($buycatsid);
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
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}