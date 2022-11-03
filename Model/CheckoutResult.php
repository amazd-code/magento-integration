<?php

namespace Amazd\Integration\Model;

class CheckoutResult
{
  public $success;
  public $error;

  public function __construct($success = false, $error = null)
  {
    $this->success = $success;
    $this->error = $error;
  }
}