<?php

class inbox extends inbox_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function read()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasRead(0, $ids);
    } else{
      $this->markasRead($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module . '/inbox/');
  }

  public function unread()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->markasunRead(0, $ids);
    } else{
      $this->markasunRead($_GET['id']);
    }
    $this->redirect(CPURL . '/' . Module . '/inbox/');
  }

  public function delete()
  {
    if($_GET['ids']){
      $ids = implode(',', $_GET['ids']);
      $this->deleteExec(0, $ids);
      $this->redirect(CPURL . '/' . Module . '/inbox/');
    } else{
      $this->deleteExec($_GET['id']);
    }
  }

  public function reply()
  {
    if($_POST){
      $this->checkExist(array('email', 'subject', 'message'));
      $this->sendEmail($_POST['email'], $_POST['subject'], $_POST['message']);
      $this->showmsg(CPURL . '/' . Module . '/inbox/', 1);
    } else{
      $message = $this->readInboxMsg($_GET['id']);
      $this->Smarty->assign('message', $message);
      $this->Output();
    }
  }

  public function view()
  {
    $this->markasRead($_GET['id']);
    $message = $this->readInboxMsg($_GET['id']);
    $this->Smarty->assign('message', $message);
    $this->Output();
  }

  public function index()
  {
    $where = array();
    if($_GET['subject']){
      $where[] = "subject LIKE '%$_GET[subject]%'";
    }
    if($_GET['email']){
      $where[] = "email='$_GET[email]'";
    }
    if($_GET['name']){
      $where[] = "name LIKE '%$_GET[name]%'";
    }
    $results = $this->getInboxMsgs($where);
    $this->Smarty->assign('results', $results);
    $this->Output();
  }

}
