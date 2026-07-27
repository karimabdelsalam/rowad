<?php

namespace frontend;

class home extends \frontend\home_model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function index()
  {
    $this->Output();
  }

}
