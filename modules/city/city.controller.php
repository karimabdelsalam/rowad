<?php

class city extends city_model
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
      foreach($_POST['name'] as $name){
        if(empty($name)) continue;
        $data = array();
        $data['name'] = $name;
        $data['countryid'] = $this->config['country'];
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
      $data['name'] = $_POST['name'];
      $data['countryid'] = $this->config['country'];
      $cityid = $this->addrecord($data);
      $this->registerLog($cityid);
      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $cityid, 'title' => $_POST['name'], 'element' => $_POST['elementID'])), 'AutoAdder');
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
      $data['name'] = $_POST['name'];
      $data['countryid'] = $this->config['country'];
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
    if($_GET['name']){
      $where[] = "name='$_GET[name]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}