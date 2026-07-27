<?php

class citizen extends citizen_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function delattach()
  {
    if($_GET['id']){
      $this->deleteAttachFile($_GET['id']);
      $this->redirect();
    }
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

  public function search()
  {
    if(strlen(utf8_decode($_GET['q'])) < 1){
      //return false;
    }
    $results = $this->searchQuery($_GET['q']);
    echo json_encode($results);
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idsource'] = $_POST['idsource'];
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $data['idcopy'] = $this->Upload_File($_FILES['idcopy'], 'citizen', 'image');
      }
      $data['idnumber'] = $_POST['idnumber'];
      $data['iddate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['iddate']) : $_POST['iddate'];
      $data['calendar'] = $_POST['calendar'];
      $newid = $this->addrecord($data);
      $this->registerLog($newid);
      $this->moveAttachments($_POST['tempid'], $newid);
      if($_POST['quick_form']){
        $this->showmsg(json_encode(array('id' => $newid, 'title' => $_POST['title'], 'element' => $_POST['elementID'])), 'AutoAdder');
      }
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->loadNationalities();
      $this->Output('edit');
    }
  }

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['title'] = $_POST['title'];
      $data['nationality'] = $_POST['nationality'];
      $data['idtype'] = $_POST['idtype'];
      $data['idsource'] = $_POST['idsource'];
      if(!empty($_FILES['idcopy']['tmp_name'])){
        $data['idcopy'] = $this->Upload_File($_FILES['idcopy'], 'citizen', 'image');
      }
      $data['idnumber'] = $_POST['idnumber'];
      $data['iddate'] = ($_POST['calendar'] == 2) ? uCal::u2g($_POST['iddate']) : $_POST['iddate'];
      $data['calendar'] = $_POST['calendar'];
      $this->updaterecord($data, $_POST['id']);
      $this->registerLog($_POST['id']);
      $this->moveAttachments($_POST['tempid'], $_POST['id']);
      $this->showmsg(CPURL . '/' . Module . '/', 1);
    } else{
      $this->Smarty->assign('data', $this->readrecord($_GET['id']));
      $this->loadNationalities();
      $this->Output();
    }
  }

  public function index()
  {
    $where = array();
    $inner = '';
    if($_GET['title']){
      $where[] = "citizens.title LIKE '%$_GET[title]%'";
    }
    if($_GET['idnumber']){
      $where[] = "citizens.idnumber='$_GET[idnumber]'";
    }
    $results = $this->listrecord($where, true, $inner);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
