<?php
  require_once '../src/play.php';
  $pc = new PlayingCards();
  $pc->unserialize($argv[1]);
  $o = array();
  echo $pc->check_pokerhand($o);
  var_dump($o);