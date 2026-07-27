<?php

namespace clientarea;

class support extends \clientarea\support_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function send()
  {
    if($_POST){
      $this->checkExist(['buildid','subject','message']);

      $data = array();
      $data['type'] = $_POST['type'];
      $data['ownerid'] = $_SESSION['ownerid'];
      $data['buildid'] = $_POST['buildid'];
      $data['subject'] = $_POST['subject'];
      $data['message'] = $_POST['message'];
      $data['code'] = 'TEMP';
      $data['read_state'] = 0;

      $id = $this->addrecord($data);

      $idCode = strtoupper($this->AlphaSalt('3')) . '-' . ($id+1000);
      $this->updaterecord(['code' => $idCode], $id);

      $this->addNotification($this->vgettext('طلب جديد'), $_SESSION['userinfo']['name'] .' ' . $this->vgettext('ارسل طلب جديد للدعم الفني'), 'alert', 0);

      $this->showmsg(gettext('تم استقبال طلبكم بنجاح و سيتم الرد عليكم في اقرب وقت ممكن. يمكنكم المتابعة برقم الطلب #') . $idCode, 'success');
    } else {
      $this->Smarty->assign('builds', $this->clientBuilds());
      $this->Output();
    }
  }

}
