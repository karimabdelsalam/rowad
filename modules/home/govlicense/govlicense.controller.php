<?php

class govlicense extends govlicense_model
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
      $this->redirect();
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['expiredate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['expiredate']) : $_POST['expiredate'];
      $data['notify'] = $_POST['notify'];
      $data['number'] = $_POST['number'];
      $data['calendar'] = $_POST['calendar'];
      $data['copy'] = $this->Upload_File($_FILES['copy'], 'govlicense', 'all');
      $licid = $this->addrecord($data);
      $this->registerLog($licid);
      $this->showmsg(CPURL . '/' . Module . '/index', 1);
    } else{
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['expiredate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['expiredate']) : $_POST['expiredate'];
      $data['notify'] = $_POST['notify'];
      $data['number'] = $_POST['number'];
      $data['calendar'] = $_POST['calendar'];
      if(!empty($_FILES['copy']['tmp_name'])){
        $data['copy'] = $this->Upload_File($_FILES['copy'], 'govlicense', 'all');
      }
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/index', 1);
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