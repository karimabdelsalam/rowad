<?php

class task extends task_model
{

  public function __construct()
  {
    parent::__construct();
    $manager = $this->auto_load('manager');
    $this->Smarty->assign('managers', $manager->getManagers(array(), false));
  }

  public function active()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasDone(0, $ids);
    } else{
      $this->markasDone($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
  }

  public function inactive()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasUndone(0, $ids);
    } else{
      $this->markasUndone($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module);
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

  public function edit()
  {
    if($_POST){
      $data = array();
      $data['tomanagerid'] = $_POST['tomanagerid'];
      $data['content'] = $_POST['content'];
      $this->saveTask($data, $_POST['id']);
      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Smarty->assign('data', $this->readTask($_GET['id']));
      $this->Output();
    }
  }

  public function add()
  {
    if($_POST){
      $data = array();
      $data['managerid'] = $_SESSION['userid'];
      $data['tomanagerid'] = $_POST['tomanagerid'];
      $data['content'] = $_POST['content'];
      $data['timepost'] = TIMENOW;
      $this->addTask($data);

      $this->addNotification($this->vgettext('مهمة جديدة'), $_SESSION['userinfo']['name'] .' ' . $this->vgettext('اضاف مهمة جديدة لك'), 'info', $_POST['tomanagerid']);

      $this->showmsg(CPURL . '/' . Module, 1);
    } else{
      $this->Output('edit');
    }
  }

  public function index()
  {
    $where = array();
    if($_GET['tomanagerid']){
      $where[] = "task.tomanagerid='$_GET[tomanagerid]'";
    }
    if($_GET['done'] == 1){
      $where[] = "task.done='1'";
    } elseif($_GET['done'] == 2){
      $where[] = "task.done='0'";
    }
    $results = $this->listTask($where);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
