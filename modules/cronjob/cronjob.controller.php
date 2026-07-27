<?php

class cronjob extends cronjob_model {

  public function __construct() {
    parent::__construct();
  }

  public function index() {
    define('CRONJOB_ENABLED', true);
    ignore_user_abort(true);
    @set_time_limit(0);
    @ini_set('memory_limit', '2048M');

    $crons = $this->fetchActiveCrons();
    if($crons){
      foreach($crons as $cron){
        $sendtime = strtotime($cron['starttime']);
        $data = array();
        while($sendtime < TIMENOW){
          $sendtime = strtotime($cron['crontime'], $sendtime);
        }
        $data['starttime'] = date('Y-m-d H:i:s', $sendtime);
        $this->updateCron($data, $cron['id']);
        include(ROOT_DIR.'/cron/'.$cron['file'].'.php');
      }
    }
  }

}
