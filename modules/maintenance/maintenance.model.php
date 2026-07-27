<?php

class maintenance_model extends core
{
  public $sitemap;
  public $modifieddate;
  public $changefreq;

  public function __construct()
  {
    parent::__construct();
  }

  public function startBackup()
  {
    global $db_config;
    include('backup.class.php');
    $backup = new MyBackUp();
    $backup->server = $db_config['dbhost'];
    $backup->usern = $db_config['dbuser'];
    $backup->userp = $db_config['dbpass'];
    $backup->dbase = $db_config['dbname'];

    $backup->mailFrom = $this->config['email'];
    $backup->mailTo = $this->config['email'];
    $backup->body = 'This a backup copy form your database in the date ' . DATENOW;
    $backup->isDel = true;

    $backup->filename = ROOT_DIR . '/' . UPLOAD_DIR . '/db_backup_' . date("y_m_d") . '.sql';
    $backup->BackUp();
  }

}