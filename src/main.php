<?php
/*

######################################################################

kuinabot - A Twitter bot acting like Kuina Natsukawa.
Copyright (C) 2019 inonote

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

######################################################################

*/

  $bdir = dirname(__FILE__);
  require_once $bdir.'/config.php';
  require_once $bdir.'/twitteroauth/autoload.php';
  require_once $bdir.'/play.php';
  use Abraham\TwitterOAuth\TwitterOAuth;
  
  $settings = parse_ini_string(file_get_contents($bdir.'/config.ini'));
  $o = new TwitterOAuth
  (
  $sTwitterConsumerKey,
  $sTwitterConsumerSecret,
  $settings['token'],
  $settings['skey']
  );

  //30分おきにランダムツイート
  $tm = (int)date('Hi');
  if (date('m-d H:i')==='01-01 00:00'){ //あけおめツイート
    $o->post('/statuses/update', array(
      'status' => 'あけましておめでとうございます。'.PHP_EOL.'今年もよろしくお願いします！'."٩( 'ω' )و"
    ));
  }
  else if (date('m-d H:i')==='01-01 00:02'){ //あけおめからの寝る
    $o->post('/statuses/update', array(
      'status' => 'では私は先に寝ますね！おやすみなさい～'
    ));
  }
  else if (($tm >= 700 && $tm <= 2300) && (date('i')%30 == 0 || isset($autotw))){
    if ($tm == 700){
      $o->post('/statuses/update', array(
        'status' => 'おはようございます！'
      ));
    }
    else if ($tm == 2300){
      if (date('m-d')!=='12-31'){
        $oyasumi = array('おやすみなさ～い(つω=)', 'おやすみなさ～い(=ω=)｡o', 'おやすみなさ～い( ˘ω˘)ｽﾔｧ');
        $o->post('/statuses/update', array(
          'status' => $oyasumi[rand(0, 2)]
        ));
      }
    }
    else{
      $tw_serifu = file($bdir.'/serifu/tweets_randomized.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      if ($tw_serifu === false || count($tw_serifu) == 0){ //ランダムに並べ替え
        $tw_serifu = file($bdir.'/serifu/tweets.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        shuffle($tw_serifu);
      }
      $o->post('/statuses/update', array(
        'status' => str_replace('/', PHP_EOL, $tw_serifu[0])
      ));
      array_splice($tw_serifu, 0, 1);
      file_put_contents($bdir.'/serifu/tweets_randomized.txt', implode("\n", $tw_serifu));
    }
  }

  //アラーム設定をロード
  // user_id,tweet_id,screen_name,hour,minute
  $alarms = array();
  $alarms_raw = file($bdir.'/private/alarm.dat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($alarms_raw !== false){
    foreach ($alarms_raw as $v) {
      $e = explode(',', $v, 5);
      $alarms[$e[0]] = array(
        'tweet_id' => $e[1],
        'screen_name' => $e[2],
        'hour' => (int)$e[3],
        'minute' => (int)$e[4]
      );
    }
  }

  //自動リプ
  $followers = json_decode(json_encode($o->get('followers/ids', array('user_id' => $botacname, 'count' => '5000', 'stringify_ids' => true))), true);
  $rep_serifu = file($bdir.'/serifu/reply.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $begintime = time() - 60; //1分前のツイートを確認
  // リプ20件取得
  $mentions = json_decode(json_encode($o->get('statuses/mentions_timeline', array('count' => '20'))), true);
  foreach($mentions as $key => $val){
    if($val['user']['screen_name'] != $botacname && strtotime($val['created_at']) > $begintime) {
      $text = '';
      $val['text'] = htmlspecialchars_decode($val['text']);
      $val['user']['name'] = htmlspecialchars_decode($val['user']['name']);
      
      if(preg_match('/(TL|タイムライン|ツイート)を?見て/', $val['text'])){
        $o->post('/lists/members/create', array(
          'list_id' => '1134067047376179200',
          'user_id'=>$val['user']['id_str']
        ));
        $text = '分かりました！'.PHP_EOL.PHP_EOL.'※自動返信はフォロワーのみ対象です。'.PHP_EOL.'※自動返信を止めるには「タイムラインを見ないで」と@ツイートを送ってください。';
      }
      else if(preg_match('/(TL|タイムライン|ツイート)を?見ないで/', $val['text'])){
        $o->post('/lists/members/destroy', array(
          'list_id' => '1134067047376179200',
          'user_id'=>$val['user']['id_str']
        ));
        $text = '分かりました...';
      }
      else if(preg_match('/「([\S]+?)」[\s]*?と[\s]*?「([\S]+?)」[\s]*?(どっち|どちら)/', $val['text'], $mtcs)){
        srand();
        $r = rand(0, 99);
        $text = $mtcs[($r>50)+1].'です！';
      }
      else if(preg_match('/「([\S]*?)」って呼んで/', $val['text'], $mtcs)){
        $text = '分かりました、'.$mtcs[1].'さん！';
      }
      else if(preg_match('/([0-9]*?):([0-9]*?)に(起こして|呼んで|知らせて)/', $val['text'], $mtcs)){
        $hour = (int)$mtcs[1];
        $minute = (int)$mtcs[2];
        if ($hour < 0 || $hour >= 24 || $minute < 0 || $minute >=60){
          $text = '時間は 0 から 23 、分は 0 から 59 までしかないですよ？';
        }
        else {
          $text = '分かりました！少し遅れてしまうかもしれないので、あまり期待しないでくださいね。'.PHP_EOL.PHP_EOL.'※操作: 「アラームをやめる」'.PHP_EOL.'※注意:'.PHP_EOL.'・アラームを設定するためにbot宛に送ったツイートは消さないでください。'.PHP_EOL.'・設定した時刻を変更するには一度アラームをやめてから再登録してください。';
          $alarms[$val['user']['id_str']] = array(
            'tweet_id' => $val['id_str'],
            'screen_name' => $val['user']['screen_name'],
            'hour' => $hour,
            'minute' => $minute
          );
        }
      }
      else if(strpos($val['text'], 'アラームをやめる')!==false){
        if (@$alarms[$val['user']['id_str']] === null){
          $text = 'まだ何も頼まれていないですよ？';
        }
        else {
          $text = '分かりました！';
          unset($alarms[$val['user']['id_str']]);
        }
      }
      else if(strpos($val['text'], 'おみくじ')!==false){
        $text = ['【大凶】','【凶】','【末吉】','【小吉】','【中吉】','【吉】','【大吉】'][rand(0,6)].'です。';
      }
      else if(strpos($val['text'], '[k5375Vy3eWLdsfdRQzQcuNWJ5h8csLJS]')!==false){
        $text = 'status: ok';
      }
      else if(strpos($val['text'], 'ツイートした時刻')!==false){
        $id = (int)$val['in_reply_to_status_id_str'];
        if ($id !== 0){
	        $millsec = (($id >> 22 & 0x000003FFFFFFFFFF) + 1288834974657);
	        $text = date('Y年m月d日 H時i分s秒', $millsec/1000).($millsec%1000).' です！';
        }
        else{
            $text = '自分のツイートに返信する形でメンションを送ってくださいね。';
        }
      }
      else if (strpos($val['text'], '#くいなとじゃんけん')!==false){
        $player_hand = 0;
        if (strpos($val['text'], '#くいなにグーで勝つ')!==false){
          $player_hand = 1;
        }
        else if (strpos($val['text'], '#くいなにチョキで勝つ')!==false){
          $player_hand = 2;
        }
        else if (strpos($val['text'], '#くいなにパーで勝つ')!==false){
          $player_hand = 3;
        }
        if ($player_hand > 0){
          $janprm = random_int(0, 100);
          $kuina_iswin = false;
          if ($janprm <= 60){ //私の勝ち！
            $kuina_iswin = true;
          }
          if ($kuina_iswin){
            $text = 'じゃんけんポン！(真顔)

YOU LOSE(ﾌﾞｳｳｳｳｳｳ!!!!)

私の勝ちです！😁
なんで負けたか、明日まで
考えておいてください。
そしたら何かが見えてくるはずです。

では、いただきまーす(ﾌﾞｼｭｳｳｳｳｳｳｳ)

一日一回勝負。
じゃあ、また明日挑戦してくださいね👋☺️';
          }
          else{
            $text = 'じゃんけんポン！(真顔)

YOU WIN(ﾋﾟｭｩｫｵｵｵｵｫｫｫｫ!!!!)

すごいです！😁
明日は私にリベンジさせてください。

では、これをどうぞ(ﾌﾞｼｭｳｳｳｳｳｳｳ)';
          }
        }
      }
      else{
        foreach($rep_serifu as $row){
          $serifu_data = explode(',', $row);
          //時間指定
          if ($serifu_data[2]!=='all'){
            if (!(date('H') >= substr($serifu_data[2], 0, 2) && date('H') < substr($serifu_data[2], 2, 2)))
              continue; //時間外
          }
          if ($serifu_data[1]==0){
            if(strpos($val['text'], $serifu_data[3])!==false){
              $text = $serifu_data[4];
            }
          }
          else if ($serifu_data[1]==1){
            if(preg_match('/'.$serifu_data[3].'/', $val['text'])){
              $text = $serifu_data[4];
            }
          }
        }
        if ($text==''){
          pd_play_with_kuuchan($val['user']['id_str'], $val['user']['name'], 0, $val['text'], $text);
        }
      }
      if ($text!=''){
        $o->post('/statuses/update', array(
            'status' => '@'.$val['user']['screen_name'].' '.$text,
            'in_reply_to_status_id'=>$val['id_str']
        ));
      }
    }
  }
  
  //TLのツイートに自動リプ
  $begintime = time() - 60; //1分前のツイートを確認
  //ツイート100件取得
  $mentions = json_decode(json_encode($o->get('lists/statuses', array(
    'list_id' => '1134067047376179200',
    'count' => '100',
    'include_entities'=> 'false',
    'include_rts'=>'false'
  ))), true);
  foreach($mentions as $key => $val){
    if($val['user']['screen_name'] != $botacname && strtotime($val['created_at']) > $begintime && strpos($val['text'], '@')===false && array_search($val['user']['id_str'], $followers['ids'])!==false) {
      $text = '';
      foreach($rep_serifu as $row){
        $serifu_data = explode(',', $row);
        if ($serifu_data[0]==1){
          //時間指定
          if ($serifu_data[2]!=='all'){
            if (!(date('H') >= substr($serifu_data[2], 0, 2) && date('H') < substr($serifu_data[2], 2, 2)))
              continue; //時間外
          }
          if ($serifu_data[1]==0){
            if(strpos($val['text'], $serifu_data[3])!==false){
              $text = $serifu_data[4];

            }
          }
          else if ($serifu_data[1]==1){
            if(preg_match('/'.$serifu_data[3].'/', $val['text'])){
              $text = $serifu_data[4];
            }
          }
        }
      }
      if ($text!=''){
        $o->post('/statuses/update', array(
            'status' => '@'.$val['user']['screen_name'].' '.$text,
            'in_reply_to_status_id'=>$val['id_str']
        ));
      }
    }
  }

  //アラーム  
  $tm = (int)date('Hi');
  foreach ($alarms as $key => $v) {
    if (((int)$v['hour'])*100 + ((int)$v['minute']) <= $tm){
      $ret = json_decode(json_encode($o->get('/statuses/lookup', array(
        'id'=>$v['tweet_id']
      ))),true);
      if (count($ret) > 0){
        $o->post('/statuses/update', array(
          'status' => '@'.$v['screen_name'].' 時間ですよ～！',
          'in_reply_to_status_id'=>$v['tweet_id']
        ));
      }
      unset($alarms[$key]);
    }
  }

  pd_save();

  //アラームを設定を保存
  $alarms_raw_t = '';
  foreach ($alarms as $key => $v) {
    $alarms_raw_t.=$key.','.$v['tweet_id'].','.$v['screen_name'].','.$v['hour'].','.$v['minute'].PHP_EOL;
  }
  file_put_contents($bdir.'/private/alarm.dat', $alarms_raw_t);

   