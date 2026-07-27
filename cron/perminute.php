<?php

if(!defined('CRONJOB_ENABLED')) die();

//Send email queue
$emails = $this->db->get_results('SELECT * FROM email_queue WHERE sendtime<' . TIMENOW);
if($emails){
  foreach($emails as $email){
    $this->sendEmail($email['receiver'], $email['subject'], $email['message'], $email['sender']);
    $this->db->query("DELETE FROM email_queue WHERE id='$email[id]'");
  }
  unset($emails);
}
//Send email queue