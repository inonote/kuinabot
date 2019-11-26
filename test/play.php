<?php
  require_once '../src/play.php';
  $out = '';
  if (pd_play_with_kuuchan($argv[1], $argv[2], 0, $argv[3], $out)){
    print($out);
  }
  else{
    print('?');
  }
  pd_save();