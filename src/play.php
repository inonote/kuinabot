<?php
  $playdata = null;
  $playdata_modified = false;
  $playdata_path = dirname(__FILE__).'/private/playdata.dat';

  $msg_playnow = '今遊んでいる最中ですが... 他の遊びをしたいときは「やめたい」と言ってくださいね';

  //プレイデーターの構造
  //account_id kind data...

  //プレイデーター読み込み
  function pd_load(){
    global $playdata, $playdata_modified, $playdata_path;
    if ($playdata !== null)
      return true;
    $dat = file($playdata_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($dat === false)
      return false;
    
    $playdata = array();
    foreach ($dat as $v) {
      $sp = explode("\t", $v, 5);
      $playdata[(string)$sp[0]] = array('kind' => $sp[1], 'reply_id' => $sp[2], 'time' => $sp[3], 'data' => $sp[4]);
    }
    return true;
  }

  //プレイデーター保存
  function pd_save(){
    global $playdata, $playdata_modified, $playdata_path;
    if ($playdata === null)
      return false;
    if (!$playdata_modified) //データーが変わってなければ保存する必要なし。
      return true;
    
    $dat = '';
    foreach ($playdata as $key => $v) {
      $dat.=$key."\t".$v['kind']."\t".$v['reply_id']."\t".$v['time']."\t".$v['data'].PHP_EOL;
    }
    file_put_contents($playdata_path, $dat);
    $playdata_modified = false;
    return true;
  }

  //今何してるのか確認
  function pd_doing($user_id){
    global $playdata;
    if (!pd_load())
    return false;
    if (!isset($playdata[$user_id]))
      return false;
    return $playdata[$user_id]['kind'];
  }
  
  //プレイデーター取得
  function pd_getdata($user_id){
    global $playdata;
    if (!pd_load())
    return false;
    if (!isset($playdata[$user_id]))
      return false;
    return $playdata[$user_id];
  }

  //今何してるのか設定
  function pd_do($user_id, $kind, $reply_id, $data){
    global $playdata, $playdata_modified;
    if (!pd_load())
      return false;
    $playdata[$user_id] = array('kind' => $kind, 'reply_id' => $reply_id, 'time' => time(), 'data' => $data);
    $playdata_modified = true;
    return true;
  }

  //やっぱやめる
  function pd_cancel($user_id){
    global $playdata, $playdata_modified;
    if (!isset($playdata[$user_id]))
      return false;
    unset($playdata[$user_id]);
    $playdata_modified = true;
    return true;
  }

  //くーちゃんと遊ぶ
  function pd_play_with_kuuchan($user_id, $user_name, $reply_id, $reply_text, &$output_text){
    global $msg_playnow;
    $pd = pd_getdata($user_id);
    if ($pd!==false && ($pd['time']+1200<time())){
      pd_cancel($user_id);
      return false;
    }
    
    //名前を10文字に抑える
    $user_name_len = mb_strlen($user_name);
    $user_name_sh = mb_substr($user_name, 0, 10).($user_name_len > 10?'...':'');
    
    if (strpos($reply_text, 'やめる')!==false || strpos($reply_text, 'やめたい')!==false){
      if ($pd === false){ //すでにやめてる
        $output_text = '何をですか...？';
      }
      else{
        $output_text = '分かりました。 また今度遊びましょうね！';
        pd_cancel($user_id);
      }
      return true;
    }
    if (strpos($reply_text, '遊ぼう')!==false){
      if ($pd!==false){
        $output_text = $msg_playnow;
      }
      else{
        //遊びの候補を言う
        $output_text = 'いいですね！何して遊びましょうか...'.PHP_EOL.PHP_EOL.
        '・じゃんけん'.PHP_EOL.
        '・ババ抜き(ひととせ荘の住人と)'.PHP_EOL.
        '・ポーカー';
      }
      return true;
    }
    else if (strpos($reply_text, 'じゃんけん')!==false){
      if ($pd!==false){
        $output_text = $msg_playnow;
      }
      else{
        pd_do($user_id, 'janken', $reply_id, '');
        $output_text = 'やりましょう！'.PHP_EOL.PHP_EOL.'※「グー」か「チョキ」か「パー」で返信してください。';
      }
      return true;
    }
    else if (strpos($reply_text, 'ババ抜き')!==false){
      if ($pd!==false){
        $output_text = $msg_playnow;
      }
      else{
        //トランプを用意
        $player_hand = new PlayingCards();
        $player_hand->reset();
        $player_hand->shuffle();
        //ひととせ荘の住人 (0 ひな子 1 くいな 2 真雪 3 千秋)
        $hitotose_hands = $player_hand->slice(4);
        $player_hand->discard_duplicate();
        $shands = array();
        foreach($hitotose_hands as $v){
          $v->discard_duplicate();
          array_push($shands, $v->serialize());
        }
        //セーブ
        pd_do($user_id, 'baba', $reply_id, $player_hand->serialize().','.implode(',', $shands).'#');
        $gameman = new OldMaid();
        $gameman->register_player('夏川くいな', $hitotose_hands[0]);
        $gameman->register_player(null, $player_hand);
        $gameman->register_player('桜木ひな子', $hitotose_hands[1]);
        $gameman->register_player('柊真雪', $hitotose_hands[2]);
        $gameman->register_player('萩野千秋', $hitotose_hands[3]);
        $output_text = 'せっかくなのでひととせ荘の住人みんなでしましょう！'.PHP_EOL.
        $user_name_sh.'さんが先に私のを引いてください。'.PHP_EOL.
        $gameman->show().
        '※操作「左から○番目を引く」';
      }
      return true;
    }
    else if (strpos($reply_text, 'ポーカー')!==false){
      if ($pd!==false){
        $output_text = $msg_playnow;
      }
      else{
        //トランプを用意
        $table_card = new PlayingCards();
        $table_card->reset(true);
        $table_card->shuffle();
        $player_card = new PlayingCards();
        $player_card->empty();
        $kuina_card = new PlayingCards();
        $kuina_card->empty();
        //5枚ずつ取り出す
        for($i=0; $i < 5; $i++){
          $player_card->put($table_card->draw(0));
          $kuina_card->put($table_card->draw(0));
        }

        $shands = array();
        array_push($shands, $table_card->serialize());
        array_push($shands, $player_card->serialize());
        array_push($shands, $kuina_card->serialize());
        //セーブ
        pd_do($user_id, 'poker', $reply_id, implode(',', $shands));

        $output_text = '賭けは無しでいきましょう！ドローは1回までです。'.PHP_EOL.PHP_EOL.
        '【あなたの手札】'.PHP_EOL.
        $player_card->visualize(false).PHP_EOL.PHP_EOL.
        '【夏川くいなの手札】'.PHP_EOL.
        $kuina_card->visualize(true).PHP_EOL.PHP_EOL.
        '※操作「○、○番目を交換する」「全部交換する」「交換しない」';
      }
      return true;
    }
    if ($pd['reply_id'] == $reply_id){
      switch ($pd['kind']){
        case 'janken': //ジャンケン
          $player_hand = 0;
          if (strpos($reply_text, 'ぐー')!==false || strpos($reply_text, 'グー')!==false){
            $player_hand = 0;
          }
          else if (strpos($reply_text, 'ちょき')!==false || strpos($reply_text, 'チョキ')!==false){
            $player_hand = 1;
          }
          else if (strpos($reply_text, 'ぱー')!==false || strpos($reply_text, 'パー')!==false){
            $player_hand = 2;
          }
          else {
            $output_text = '「グー」か「チョキ」か「パー」で返信してください...';
            return true;
          }
          $janprm = random_int(0, 100);
          $kuina_iswin = false;
          if ($janprm <= 10){ //私の勝ち！
            $kuina_iswin = true;
          }
          $output_text = array('パー', 'チョキ', 'グー', 'パー', 'チョキ', 'グー')[$player_hand*2 + !$kuina_iswin].'！'.PHP_EOL.($kuina_iswin?'私の勝ちです！':'...私の負けです...');
          pd_cancel($user_id);
          return true;
          break;
        case 'baba': //ババ抜き
          if (preg_match('/([0-9]*?)(番|枚)目/', $reply_text, $mtcs)){
            //手札をロード
            $sv = explode('#', $pd['data']);
            $d = explode(',', $sv[0]);
            $player_hand = new PlayingCards();
            $player_hand->unserialize($d[0]);
            $hitotose_hands = array();
            unset($d[0]);
            foreach($d as $key => $v){
              $pc = new PlayingCards;
              $pc->unserialize($v);
              array_push($hitotose_hands, $pc);
            }
            $gameman = new OldMaid();
            $gameman->register_player('夏川くいな', $hitotose_hands[0]);
            $gameman->register_player(null, $player_hand);
            $gameman->register_player('桜木ひな子', $hitotose_hands[1]);
            $gameman->register_player('柊真雪', $hitotose_hands[2]);
            $gameman->register_player('萩野千秋', $hitotose_hands[3]);
            //一通りプレイ
            $result = $gameman->play((int)$mtcs[1] - 1);
            if ($result['status'] === false){
              $output_text = '1から'.$result['frange'].'までで選んでください...';
              return true;
            }

            $is_iwon = false; //自分が勝った

            $output_text = '';
            $rbuf = $gameman->show().
            (isset($result['drawcard'])?'引き:'.$result['drawcard']->visualize().PHP_EOL:'').
            (isset($result['drewcard'])?'引かれ:'.$result['drewcard']->visualize().PHP_EOL:'');
            foreach ($result['win'] as $v) {
              if ($v[0]==='#You#'){
                $v[0] = 'あなた';
                $is_iwon = true;
              }
              if (isset($v[2]))
                $output_text.='負け:'.$v[0].'('.(strlen($sv[1])+1).'位)'.PHP_EOL;
              else
                $output_text.='勝抜:'.$v[0].'('.(strlen($sv[1])+1).'位)'.PHP_EOL;
              $sv[1].=$v[1];
            }
            if ($is_iwon && $result['left'] > 1){ //自分が抜けたら、残りの人たちは自動で進める
              $result = $gameman->play(0);
              for($i = 0; $i < 50; $i++){
                foreach ($result['win'] as $v) {
                  if (isset($v[2]))
                    $output_text.='負け:'.$v[0].'('.(strlen($sv[1])+1).'位)'.PHP_EOL;
                  else
                    $output_text.='勝抜:'.$v[0].'('.(strlen($sv[1])+1).'位)'.PHP_EOL;
                  $sv[1].=$v[1];
                }
                if ($result['left'] <= 1){
                  break;
                }
              }
              if ($i>50){ //試行回数を超えた。
                $output_text = '"Error: Exceeded Number of Attempts';
                pd_cancel($user_id);
                return true;
              }
            }
            if ($result['left'] > 1){
              $output_text = $rbuf.$output_text;
              //保存
              $shands = array();
              foreach($hitotose_hands as $v){
                $v->discard_duplicate();
                array_push($shands, $v->serialize());
              }
              pd_do($user_id, 'baba', $reply_id, $player_hand->serialize().','.implode(',', $shands).'#'.$sv[1]);
            }
            else{ //リザルト
              $output_text.=PHP_EOL.'-結果-'.PHP_EOL;
              for($i=0; $i < strlen($sv[1]); $i++){
                $output_text.=($i+1).'位 '.array('夏川くいな', 'あなた', '桜木ひな子', '柊真雪', '萩野千秋')[$sv[1][(int)$i]].PHP_EOL;
              }
              if (strpos($sv[1], '0') === strlen($sv[1]) - 1) //くーちゃんが負けた
                $output_text.=PHP_EOL.'負けちゃいました...';
              else
                $output_text.=PHP_EOL.'楽しかったです。またやりましょう！';
              pd_cancel($user_id);
            }

          }
          return true;
          break;

        case 'poker': //ポーカー
          $mode = 0;
          $drawcardids = array();
          if (preg_match_all('/([0-9]*?)((番|枚)目|、|,)/', $reply_text, $mtcs, PREG_PATTERN_ORDER)){
            //入力チェック
            if (count($mtcs[1]) > 5){
              $output_text = '指定は5つまでです...';
              return true;
            }
            foreach ($mtcs[1] as $v) {
              if ($v < 1 || $v > 5){
                $output_text = '1から5までで指定してください...';
                return true;
              }
            }
            $drawcardids = array_unique($mtcs[1]);
            $drawcardids = array_values($drawcardids);
            foreach($drawcardids as &$v)
              $v--;
            $mode = 1;
          }
          else if (strpos($reply_text, '全部')!==false){
            $drawcardids = array(0, 1, 2, 3, 4);
            $mode = 1;
          }
          else if (strpos($reply_text, '交換しない')!==false){
            $mode = 2;
          }
          if ($mode){
            //カードをロード
            $d = explode(',', $pd['data']);
            $table_card = new PlayingCards();
            $table_card->unserialize($d[0]);
            $player_card = new PlayingCards();
            $player_card->unserialize($d[1]);
            $kuina_card = new PlayingCards();
            $kuina_card->unserialize($d[2]);

            if ($mode === 1){ //カード交換
              $player_card->draw($drawcardids);
              //場から取る
              for($i = 0; $i < count($drawcardids); $i++)
                $player_card->put($table_card->draw(0));
            }
            //くーちゃんはカードを交換するか見極める
            $phand = $kuina_card->check_pokerhand();
            if ($phand <= 3){ //スリーカード以下は交換
              $kuina_d = $kuina_card->nopairlist();
              $kuina_card->draw($kuina_d);
              //場から取る
              $len = 5 - $kuina_card->count();
              for($i = 0; $i < $len; $i++){
                $kuina_card->put($table_card->draw(0));
              }
              
            }

            //勝負
            $phand_jdg = array(array(), array());
            $phand = array(
              $player_card->check_pokerhand($phand_jdg[0]),
              $kuina_card->check_pokerhand($phand_jdg[1])
            );
            $win = 0;
            if ($phand[0] > $phand[1])//プレイヤーの勝ち
              $win = 1;
            else if ($phand[0] < $phand[1])//くーちゃんの勝ち
              $win = 2;
            else{ //引き分けの時は、カードの状態を見る
              if ($phand[0] !== 2){
                //数字で比較
                if ($phand_jdg[0]['num'] > $phand_jdg[1]['num']) //プレイヤーの勝ち
                  $win = 1;
                else if ($phand_jdg[0]['num'] < $phand_jdg[1]['num']) //くーちゃんの勝ち
                  $win = 2;
                //スートで比較
                else if ($phand_jdg[0]['suit'] > $phand_jdg[1]['suit']) //プレイヤーの勝ち
                  $win = 1;
                else if ($phand_jdg[0]['suit'] < $phand_jdg[1]['suit']) //くーちゃんの勝ち
                  $win = 2;
              }
              else{ //ツーペアの時は違った処理
                $num_high = array(
                  max($phand_jdg[0]['num'], $phand_jdg[0]['num2']),
                  max($phand_jdg[1]['num'], $phand_jdg[1]['num2'])
                );
                $num_low = array(
                  min($phand_jdg[0]['num'], $phand_jdg[0]['num2']),
                  min($phand_jdg[1]['num'], $phand_jdg[1]['num2'])
                );
                $suit = array(
                  max($phand_jdg[0]['num'], $phand_jdg[0]['num2']),
                  max($phand_jdg[1]['num'], $phand_jdg[1]['num2'])
                );
                if ($num_high[0] > $num_high[1])
                  $win = 1;
                else if ($num_high[0] < $num_high[1])
                  $win = 2;
                else if ($num_low[0] > $num_low[1])
                  $win = 1;
                else if ($num_low[0] < $num_low[1])
                  $win = 2;
                else if ($suit[0] > $suit[1])
                  $win = 1;
                else if ($suit[0] < $suit[1])
                  $win = 2;
              }
            }

            $hand_names = array(
              '役なし', 'ワンペア', 'ツーペア',
               'スリーカード', 'ストレート', 'フラッシュ',
               'フルハウス', 'フォーカード',
               'ストレートフラッシュ', 'ロイヤルストレートフラッシュ'
              );            
            $player_card->rsort();
            $kuina_card->rsort();
            $output_text = '【あなたの手札】'.PHP_EOL.
            $player_card->visualize(false).PHP_EOL.$hand_names[$phand[0]].PHP_EOL.PHP_EOL.
            '【夏川くいなの手札】'.PHP_EOL.
            $kuina_card->visualize(false).PHP_EOL.$hand_names[$phand[1]].PHP_EOL.PHP_EOL;
            if ($win===1)
              $output_text.='負けました...';
            else if ($win===2)
              $output_text.='私の勝ちです！';
            if ($win===0)
              $output_text.='おあいこですね。';
            pd_cancel($user_id);
          }
          return true;
          break;
      }
    }
    return false;
  }

  
/********************************
  トランプの札を管理するクラス
********************************/
  class PlayingCards{
    private $cards = array();
    private const suit_rank = array('s' => 3, 'h' => 2, 'd' => 1, 'c' => 0);
    private const num_rank = array(null, 13, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

    //元に戻す
    public function reset($exclude_joker = false){
      for ($i = 1; $i <= 13; $i++){
        array_push($this->cards, array($i, 's')); //スペード
        array_push($this->cards, array($i, 'd')); //ダイヤ
        array_push($this->cards, array($i, 'h')); //ハート
        array_push($this->cards, array($i, 'c')); //クラブ
      }
      if (!$exclude_joker)
        array_push($this->cards, array(1, 'j')); //ジョーカー
    }

    public function empty(){
      $this->cards = array();
    }

    //シリアライズ
    public function serialize(){
      $str = '';
      array_walk(
        $this->cards, 
        function($v, $key) use(&$str){
          $str.=sprintf('%02d%s', $v[0], $v[1]);
        }
      );
      return $str;
    }
    
    //アンシリアライズ
    public function unserialize($str){
      for ($i = 0; $i < strlen($str) / 3; $i++){
        $p = $i * 3;
        if (
          $str[$p+2]==='s' ||
          $str[$p+2]==='d' ||
          $str[$p+2]==='h' ||
          $str[$p+2]==='c' ||
          $str[$p+2]==='j'
        ){
          array_push($this->cards, array(min(max((int)($str[$p].$str[$p+1]), 1), 13), $str[$p+2]));
        }
      }
    }

    //シャッフル
    public function shuffle(){
      shuffle($this->cards);
      /*$c = count($this->cards);
      if ($c<10)
        return false;
      //ヒンドゥーシャッフル

      for($i = 0; $i < 20; $i++){
        //適当に下25%前後を取り出して上にもっていく
        $n = random_int(5, 50);
        echo $n.',';
        $p = max(min($c, $c * ($n / 100.0)), 1);
        $this->cards = array_merge(array_slice($this->cards, $c - $p, $p), array_slice($this->cards, 0, $c - $p));
      }
      return true;*/
    }

    //手札の枚数
    public function count(){
      return count($this->cards);
    }

    //ペアカードを捨てる(数字が一致)
    public function discard_duplicate(){
      $cnt = array();
      $discnt = 0;
      array_walk(
        $this->cards, 
        function($v, $key) use(&$cnt){
          if ($v[1]!=='j'){
            if (!isset($cnt[$v[0]]))
              $cnt[$v[0]] = array($key);
            else
              array_push($cnt[$v[0]], $key);
          }
        }
      );
      foreach ($cnt as $v) {
        for($i = 0; $i < ((int)(count($v) / 2)) * 2; $i++){
          unset($this->cards[$v[$i]]);
        }
      }
      $this->cards = array_values($this->cards);
      return $discnt;
    }

    //重複リスト
    public function duplicatelist(){
      $cnt = array();
      $dupcnt = 0;
      array_walk(
        $this->cards, 
        function($v, $key) use(&$cnt){
          if ($v[1]!=='j'){
            if (!isset($cnt[$v[0]]))
              $cnt[$v[0]] = 1;
            else
            $cnt[$v[0]]++;
          }
        }
      );
      return array(
        'list' => array_filter(
          $cnt,
          function($v) use(&$cnt, &$dupcnt){
            if ($v <= 1)
              return false;
            $dupcnt+=$v;
            return true;
          }
        ),
        'num' => $dupcnt
      );
    }
    
    //ノーペアリスト
    public function nopairlist(){
      $cnt = array();
      $ret = array();
      array_walk(
        $this->cards, 
        function($v, $key) use(&$cnt){
          if ($v[1]!=='j'){
            if (!isset($cnt[$v[0]]))
              $cnt[$v[0]] = 1;
            else
              $cnt[$v[0]]++;
          }
        }
      );
      array_walk(
        $this->cards, 
        function($v, $key) use($cnt, &$ret){
          if ($v[1]!=='j'){
            if ($cnt[$v[0]] <= 1){
              array_push($ret, $key);
            }
          }
        }
      );
      return $ret;
    }

    //数字でソート(降順)
    public function rsort(){
      $num = array();
      foreach ($this->cards as $key => $v) {
        $num[$key] = PlayingCards::num_rank[$v[0]];
      }
      array_multisort($num, SORT_DESC, $this->cards);
    }

    //任意のカードを取り出す
    public function draw($index){
      if (is_array($index)){
        foreach($index as $v){
          if (isset($this->cards[$v]))
            unset($this->cards[$v]);
        }
        $this->cards = array_values($this->cards);
        return true;;
      }
      else{
        $s = $this->check($index);
        if ($s === false)
          return false;
        unset($this->cards[$index]);
        $this->cards = array_values($this->cards);
        return $s;
      }
    }

    //任意のカードを取得
    public function check($index){
      if (!isset($this->cards[$index]))
        return false;
      return $this->cards[$index];
    }

    //カードをもらう
    public function put($s){
      if (
        $s[1]==='c' ||
        $s[1]==='s' ||
        $s[1]==='d' ||
        $s[1]==='h' ||
        $s[1]==='j'
      ){
        array_push($this->cards, array(min(max($s[0], 1), 13), $s[1]));
        return true;
      }
      return false;
    }

    //カードをn人にも分ける
    public function slice($n){
      $cards = array_values($this->cards);
      $this->empty();
      $pc = array();
      for($i = 0; $i < $n; $i++){
        $pc[$i] = new PlayingCards;
        $pc[$i]->empty();
      }
      foreach ($cards as $key => $v) {
        if ($key % ($n + 1) === 0)
          $this->put($v);
        else
          $pc[$key % ($n + 1) - 1]->put($v);
      }
      return $pc;
    }

    //ポーカーハンドを確認
    public function check_pokerhand(&$out_power = null){
      if ($this->count() < 5)
        return false;

      $five_cards = new PlayingCards;
      for($i = 0; $i < 5; $i++){
        $five_cards->put($this->cards[$i]);
      }
      $five_cards->rsort();
      $duplicatelist = $five_cards->duplicatelist();
      if ( //ロイヤルストレートフラッシュ
          $five_cards->check(0)[0] === 13 &&
          $five_cards->check(1)[0] === 12 && $five_cards->check(0)[1] === $five_cards->check(1)[1] &&
          $five_cards->check(2)[0] === 11 && $five_cards->check(0)[1] === $five_cards->check(2)[1] &&
          $five_cards->check(3)[0] === 10 && $five_cards->check(0)[1] === $five_cards->check(3)[1] &&
          $five_cards->check(4)[0] === 1 && $five_cards->check(0)[1] === $five_cards->check(4)[1]
        ){
        if ($out_power!==null){
          $out_power = array('num' => 13, 'suit' => PlayingCards::suit_rank[$five_cards->check(0)[1]]);
        }
        return 9;
      }
      else if ( //ストレートフラッシュ
          $five_cards->check(0)[0] ===  $five_cards->check(4)[0] + 4 &&
          $five_cards->check(1)[0] === $five_cards->check(4)[0] + 3 && $five_cards->check(0)[1] === $five_cards->check(1)[1] &&
          $five_cards->check(2)[0] === $five_cards->check(4)[0] + 2 && $five_cards->check(0)[1] === $five_cards->check(2)[1] &&
          $five_cards->check(3)[0] === $five_cards->check(4)[0] + 1 && $five_cards->check(0)[1] === $five_cards->check(3)[1] &&
          $five_cards->check(0)[1] === $five_cards->check(4)[1]
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => max(
              PlayingCards::num_rank[$five_cards->check(0)[0]],
              PlayingCards::num_rank[$five_cards->check(1)[0]],
              PlayingCards::num_rank[$five_cards->check(2)[0]],
              PlayingCards::num_rank[$five_cards->check(3)[0]],
              PlayingCards::num_rank[$five_cards->check(4)[0]]
            ),
            'suit' => PlayingCards::suit_rank[$five_cards->check(0)[1]]
          );
        }
        return 8;
      }
      else if ( //フォーカード
          $five_cards->count() - $duplicatelist['num'] === 1 &&
          count($duplicatelist['list']) === 1
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[0]]
          );
          $out_power['suit'] = 0;
          $num = array_keys($duplicatelist['list'])[0];
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit']);
          }
        }
        return 7;
      }
      else if ( //フルハウス
          array_search(2, $duplicatelist['list']) !== false && array_search(3, $duplicatelist['list']) !== false
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[0]]
          );
          $out_power['suit'] = 0;
          $num = array_search(3, $duplicatelist['list']);
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit']);
          }
        }
        return 6;
      }
      else if ( //フラッシュ
          $five_cards->check(0)[1] === $five_cards->check(1)[1] &&
          $five_cards->check(0)[1] === $five_cards->check(2)[1] &&
          $five_cards->check(0)[1] === $five_cards->check(3)[1] &&
          $five_cards->check(0)[1] === $five_cards->check(4)[1]
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => max(
              PlayingCards::num_rank[$five_cards->check(0)[0]],
              PlayingCards::num_rank[$five_cards->check(1)[0]],
              PlayingCards::num_rank[$five_cards->check(2)[0]],
              PlayingCards::num_rank[$five_cards->check(3)[0]],
              PlayingCards::num_rank[$five_cards->check(4)[0]]
            ),
            'suit' => PlayingCards::suit_rank[$five_cards->check(0)[1]]
          );
        }
        return 5;
      }
      else if ( //ストレート
          $five_cards->check(0)[0] ===  $five_cards->check(4)[0] + 4 &&
          $five_cards->check(1)[0] === $five_cards->check(4)[0] + 3 &&
          $five_cards->check(2)[0] === $five_cards->check(4)[0] + 2 &&
          $five_cards->check(3)[0] === $five_cards->check(4)[0] + 1
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => max(
              PlayingCards::num_rank[$five_cards->check(0)[0]],
              PlayingCards::num_rank[$five_cards->check(1)[0]],
              PlayingCards::num_rank[$five_cards->check(2)[0]],
              PlayingCards::num_rank[$five_cards->check(3)[0]],
              PlayingCards::num_rank[$five_cards->check(4)[0]]
            ),
            'suit' => max(
              PlayingCards::suit_rank[$five_cards->check(0)[1]],
              PlayingCards::suit_rank[$five_cards->check(1)[1]],
              PlayingCards::suit_rank[$five_cards->check(2)[1]],
              PlayingCards::suit_rank[$five_cards->check(3)[1]],
              PlayingCards::suit_rank[$five_cards->check(4)[1]]
            ),
          );
        }
        return 4;
      }
      else if ( //スリーカード
          count($duplicatelist['list']) === 1 &&
          array_search(3, $duplicatelist['list']) !== false
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[0]]
          );
          $out_power['suit'] = 0;
          $num = array_keys($duplicatelist['list'])[0];
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit']);
          }
        }
        return 3;
      }else if ( //ツーペア
          count($duplicatelist['list']) === 2 &&
          array_values($duplicatelist['list'])[0] == 2 &&
          array_values($duplicatelist['list'])[1] == 2
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[0]],
            'num2' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[1]]
          );
          $out_power['suit'] = 0;
          $num = array_keys($duplicatelist['list'])[0];
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit']);
          }
          $out_power['suit2'] = 0;
          $num = array_keys($duplicatelist['list'])[1];
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit2'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit2']);
          }
        }
        return 2;
      }else if ( //ワンペア
          count($duplicatelist['list']) === 1 &&
          array_values($duplicatelist['list'])[0]== 2
          ){
        if ($out_power!==null){
          $out_power = array(
            'num' => PlayingCards::num_rank[array_keys($duplicatelist['list'])[0]],
          );
          $out_power['suit'] = 0;
          $num = array_keys($duplicatelist['list'])[0];
          for($i = 0; $i < $five_cards->count(); $i++){
            $c = $five_cards->check($i);
            if ($c[0] === $num)
              $out_power['suit'] = max(PlayingCards::suit_rank[$c[1]], $out_power['suit']);
          }
        }
        return 1;
      }
      else{ //役なし
        if ($out_power!==null){
          $out_power = array(
            'num' => max(
              PlayingCards::num_rank[$five_cards->check(0)[0]],
              PlayingCards::num_rank[$five_cards->check(1)[0]],
              PlayingCards::num_rank[$five_cards->check(2)[0]],
              PlayingCards::num_rank[$five_cards->check(3)[0]],
              PlayingCards::num_rank[$five_cards->check(4)[0]]
            ),
            'suit' => 0
          );
        }
        return 0;
      }
    }

    //ビジュアライズ
    public function visualize($secret = false){
      $str = '';
      array_walk(
        $this->cards, 
        function($v, $key) use(&$str, $secret){
          if ($secret){
            $str.='# ';
          }
          else{
            switch ($v[1]) {
              case 'c':
                $str.='♣';
                break;
              case 's':
                $str.='♠';
                break;
              case 'd':
                $str.='♦';
                break;
              case 'h':
                $str.='♥';
                break;
              case 'j':
                $str.='{JK} ';
                break;
            }
            if ($v[1]!=='j'){
              if ($v[0]===11){
                $str.='J';
              }
              else if ($v[0]===12){
                $str.='Q';
              }
              else if ($v[0]===13){
                $str.='K';
              }
              else if ($v[0]===1){
                $str.='A';
              }
              else{
                $str.=$v[0];
              }
              $str.=' ';
            }
          }
        }
      );
      return $str;
    }
  }

/*******************
      ババ抜き
*******************/
  class OldMaid{
    private $players = array();
    private $youidx = 0;

    //登録
    public function register_player($name, &$card){
      array_push($this->players, array('name' => ($name===null?'#You#':$name), 'card' => &$card));
      if ($name===null)
        $this->youidx = count($this->players) - 1;
    }

    //出力
    public function show(){
      $str = '';
      $no = 0;
      $o = true;
      
      if ($this->players[$this->youidx]['card']->count() > 0)
        $str.='【あなたの手札】'.PHP_EOL.$this->players[$this->youidx]['card']->visualize(false).PHP_EOL.PHP_EOL;

      foreach($this->players as $key => $v){
        if ($v['card']->count() <= 0)
          continue;

        if ($key === $this->youidx){
          if ($v['card']->count() <= 0)
            $no--;
        }
        else if($o)
          $str.='→'.($no+1).'【'.$v['name'].'】'.PHP_EOL.$v['card']->visualize(true).PHP_EOL.PHP_EOL;
        else
          $str.=($no+1).' '.$v['name'].': '.$v['card']->count().'枚'.PHP_EOL;
        $no++;
        if ($key !== $this->youidx)
          $o = false;
      }
      return $str;
    }

    //一巡プレイ
    public function play($idx){
      $result = array();
      $playable = array(); //プレイ可能なプレイヤー
      $result['status'] = true;
      $result['win'] = array();

      foreach($this->players as $key => $v){
        if ($v['card']->count() > 0){
          $v['idx'] = $key;
          array_push($playable, $v);
        }
      }

      $i = 0;
      while($i < count($playable)){
        $to = $i + 1;
        if ($to === count($playable)){
          $to = 0;
        }
        //print($playable[$to]['name'].' << '. $playable[$i]['name'].PHP_EOL); 
        // $to が $i を引く
        if ($playable[$to]['name']==='#You#'){ //自分が引く
          $r = $playable[$i]['card']->draw($idx);
          if ($r === false){
            return array('status' => false, 'frange' => $playable[$i]['card']->count());
          }
          $result['drawcard'] = new PlayingCards;
          $result['drawcard']->put($r);
        }
        else{
          $r = $playable[$i]['card']->draw(random_int(0, $playable[$i]['card']->count() - 1));        
        }
        $playable[$to]['card']->put($r);
        $playable[$to]['card']->discard_duplicate();
        $playable[$to]['card']->shuffle();
        if ($playable[$i]['name']==='#You#'){ //自分が引かれた
          $result['drewcard'] = new PlayingCards;
          $result['drewcard']->put($r);
        }
        
        if ($playable[$i]['card']->count() <= 0){ //抜け
          array_push($result['win'], array($playable[$i]['name'], $playable[$i]['idx']));
          unset($playable[$i]);
          $playable = array_values($playable);
          $i--;
        }
        
        if (!isset($playable[$to]))
          break;
          
        if ($playable[$to]['card']->count() <= 0){ //抜け
          array_push($result['win'], array($playable[$to]['name'], $playable[$to]['idx']));
          unset($playable[$to]);
          $playable = array_values($playable);
        }
        $i++;
      }
      $result['left'] = count($playable); //残り人数
      if (count($playable)===1){
        array_push($result['win'], array($playable[0]['name'], $playable[0]['idx'], true));
      }
      return $result;
    }
  }