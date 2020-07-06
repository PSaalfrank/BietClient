<?php 
  date_default_timezone_set('Europe/Berlin'); # Festlegung der Zeitzone für time()
  $apikey='5173761281'; 
  $params="apikey=$apikey";
  $warten = 600; # Wann wird das nächste mal nach Auktionen geschaut in Sekunden

  # Lesen der Preislisten
  $bierpreisliste = file("preisliste_bier.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
  $nudelpreisliste = file("preisliste_nudeln.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $klopreisliste = file("preisliste_klo.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  # Festlegung der jeweiligen Marktwerte und des Maximalgebots pro Auktion
  $bierpreis = marktwert("Bier");
  $nudelpreis = marktwert("Nudeln");
  $klopreis = marktwert("Klopapier");
  $mein_Max = 40000; 
  $erhoehung = 10;

  start:
  # Abfrage/Anzeige des Guthabens und der gewonnenen Auktionen
  $guthaben = api_call($params."&cmd=guthaben");
  $gewonnen = api_call($params."&cmd=gewonnen");
  $gewonnen_begrenzt = maxLines($gewonnen, 5);
  print "\n\n".date("H:i:s")."\n\nGuthaben:\n$guthaben\n\nZuletzt gewonnen (insgesamt ".substr_count($gewonnen, "\n")."):\n$gewonnen_begrenzt\n\n";

  # Abfrage nach aktuellen Angeboten und Errechnung der Restzeit in Sekunden
  $result_bier = api_call($params."&cmd=angebot&produkt=Bier");
  print "Result Bier: $result_bier\n";
  $seconds_bier = dauer($result_bier);

  $result_nudeln = api_call($params."&cmd=angebot&produkt=Nudeln");
  print "Result Nudeln: $result_nudeln\n";
  $seconds_nudeln = dauer($result_nudeln);

  $result_klopapier = api_call($params."&cmd=angebot&produkt=Klopapier");
  print "Result Klopapier: $result_klopapier\n";
  $seconds_klo = dauer($result_klopapier);

  # Test nach der nähesten Aktion über Restsekunden und Aufruf der Bietfunktion mit den Parametern des passenden Produktes
  if ($seconds_bier<$seconds_nudeln && $seconds_bier<$seconds_klo) {
    if (bieten($result_bier, "Bier", $bierpreis) == 1) goto start; # Nach Auktionsende Sprung zum Start (Zeile 18)
  }
  elseif ($seconds_nudeln<$seconds_bier && $seconds_nudeln<$seconds_klo) {
    if (bieten($result_nudeln, "Nudeln", $nudelpreis) == 1) goto start; # Nach Auktionsende Sprung zum Start (Zeile 18)
  }
  elseif ($seconds_klo<$seconds_nudeln && $seconds_klo<$seconds_bier) {
    if (bieten($result_klopapier, "Klopapier", $klopreis) == 1) goto start; # Nach Auktionsende Sprung zum Start (Zeile 18)
  }

  # Falls keine Auktion findbar ist, wird um gewartet und danach zum Start gesprungen (Zeile 15)
  else {
    sleep($warten);
    goto start;	
  }

  # Basisaufruf
  function api_call($params) {
    $query="http://dl6mhw.de/~corona/markt/mservice.php?$params";
    $lines = implode(file($query));
    return($lines);
  }

  # Begrenzung Anzahl lines
  function maxLines($str, $num=10) {
    $lines = explode("\n", $str);
    if (count($lines)<6) {return $str;} 
    else {
      $firsts = array_slice($lines, 0, $num);
      array_push($firsts, "...");
      return implode("\n", $firsts);
    }
  }

  # Dauer zum Auktionsende
  function dauer($result) {
    if ($result !="nix") {

      # Auktionsende als str greppen und von der aktuellen zeit abziehen (Betrag)
      if (preg_match('/ende=(.*);/', $result, $m)) { 
          $ende=$m[1];
          $seconds = abs(time()-strtotime("$ende"));
      } 
    } else $seconds = time();
    return $seconds; 
  }

  # Füllung der Preislisten
  function preisliste($produkt, $preis) {
    global $bierpreisliste, $nudelpreisliste, $klopreisliste;
    if ($preis != "") {
      if ($produkt == "Bier"){
        $my_arr = $bierpreisliste; 
        array_push($my_arr, $preis);
        $content = "$preis\n";
        file_put_contents("preisliste_bier.txt", $content, FILE_APPEND);
      }
      elseif ($produkt == "Nudeln"){
        $my_arr = $nudelpreisliste; 
        array_push($my_arr, intval($preis));
        $content = "$preis\n";
        file_put_contents("preisliste_nudeln.txt", $content, FILE_APPEND);
      } 
      elseif ($produkt == "Klopapier"){
        $my_arr = $klopreisliste; 
        array_push($my_arr, intval($preis));
        $content = "$preis\n";
        file_put_contents("preisliste_klo.txt", $content, FILE_APPEND);
      } 
    }
    else {
      if ($produkt == "bier") {$my_arr = $bierpreisliste;}
      elseif ($produkt == "nudeln") {$my_arr = $nudelpreisliste;} 
      elseif ($produkt == "klopapier") {$my_arr = $klopreisliste;}       
    }      
    return $my_arr;
  }

  # Status einer Website checken
  function url_online( $url ) {
    $timeout = 10;
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);
    $http_respond = curl_exec($ch);
    $http_respond = trim(strip_tags($http_respond));
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (($http_code == "200") || ($http_code == "302")) { return true; } 
    else {return false;}
    curl_close(curl_init());
  }

  # Scrapen der leader.php nach den aktuellen Marktwerten
  function marktwert($produkt) {
    if (url_online('http://dl6mhw.de/~corona/sasi/leader.php')) {
      $html = file_get_contents('http://dl6mhw.de/~corona/sasi/leader.php');
      # Vor Bootstrap
      #$lines = explode("\n", $html); ≈
      #if ($produkt=="Bier") {$marktwert = intval(substr($lines[0], 25));}
      #elseif ($produkt=="Nudeln") {$marktwert = intval(substr($lines[1], 27));}
      #elseif ($produkt=="Klopapier") {$marktwert = intval(substr($lines[2], 30));}
      # Nach Bootstrap
      $lines = explode(">", $html); 
      if ($produkt=="Bier") {$marktwert = intval(substr($lines[20], 26));}
      elseif ($produkt=="Nudeln") {$marktwert = intval(substr($lines[21], 27));}
      elseif ($produkt=="Klopapier") {$marktwert = intval(substr($lines[22], 30));}
      return $marktwert;      
    }
    else {return marktwert_backup($produkt);} 
  }

  # Marktwert Backup (falls Website down)
  function marktwert_backup($produkt) {
    global $bierpreisliste, $nudelpreisliste, $klopreisliste;    
    $arr = array();
    if ($produkt == "Bier") {$arr = $bierpreisliste;}
    elseif ($produkt == "Nudeln") {$arr = $nudelpreisliste;}
    elseif ($produkt == "Klopapier") {$arr = $klopreisliste;}
    $summe = array_sum($arr);
    if (count($arr)>4) {$marktwert = intval($summe / count($arr));}
    else {$marktwert = 30000;}    
    return $marktwert;    
  }

  # Bietfunktion
  function bieten($result, $produkt, $marktpreis) {
    global $warten, $apikey, $params, $bierpreis, $nudelpreis, $klopreis, $mein_Max, $erhoehung;  

    # Greppen nach Restminuten, Preis und Auktionsende
    $restminuten=0;
    $preis=0;
    $ende="";
    if (preg_match('/preis=([0-9]+);/', $result, $m)) {$preis=$m[1];}
    if (preg_match('/ende=(.*);/', $result, $m)) {$ende=$m[1];}
    if (preg_match('/minuten=([0-9]+)/', $result, $m)) {$restminuten=$m[1];}
    print "Aktueller Preis $preis für $produkt, noch $restminuten Minuten.\n";
    print "Durschnittspreis $produkt: $marktpreis\n";

    # Maximalpreis anpassen falls Marktwert zu hoch
    if ($marktpreis>($mein_Max+$erhoehung)) {
      $marktpreis = $mein_Max;
    }
    print "Mein Limit: $marktpreis\n";
    # Verbleibende Zeit in Sekunden/Microsekunden aus Auktionsende ausrechnen
    $seconds = strtotime("$ende"); 
    $t=time();  
    # Falls die Restzeit (-1) > der Wartezeit, wird gewartet
    if (($seconds - $t - 1)>$warten) {
      sleep($warten);
      return 1; # Sprung zurück um ggfs. eine neue Auktion die früher endet aufzufangen
    }

    # Warte- bis 1-2 Sekunden vor Auktionsende (usleep() kann variieren)
    sleep($seconds - $t - 3);
    usleep(900000);
    # sleep($seconds - $t - 60);
    # Test ob eigenes Angebot führt
    $fuehrt = api_call($params."&cmd=status&produkt=$produkt");  

    # Wenn nicht führend, dann Bietvorgang starten
    if ($fuehrt!='OK') { 
      $nresult = api_call($params."&cmd=angebot&produkt=$produkt");

      # Aktuellen Preis greppen
      if (preg_match('/preis=([0-9]+);/',$nresult,$m)) {$npreis=$m[1];}

      # Falls der neue Preis kleiner als der Maximal-/Marktpreis ist wird geboten
      if ($npreis < ($marktpreis + $erhoehung)) {
        $mpreis = $npreis + $erhoehung; # Erhöhung des Preises
        $gebot = api_call($params."&cmd=bieten&produkt=$produkt&preis=$mpreis"); # Abgabe Gebot

        # Falls das Gebot geklappt hat, wird der Preis in die jeweilige Preisliste eingetragen
        if ($gebot=='OK') {
          print "Gebot $mpreis abgegeben: $gebot\n";
          preisliste($produkt, $mpreis);
        } 

        # Falls das Gebot nicht geklappt hat, wird der aktuelle Preis in die jeweillige Preisliste eingetragen
        # und die Auktion in das verloren-log eingetragen
        else {
          print "$gebot\n";
          print "Verloren.";
          $content = $produkt.": ".$npreis." -- Verloren\n";
          file_put_contents("verloren.txt", $content, FILE_APPEND);
          preisliste($produkt, $npreis);
        }
      } 

      # Falls der aktuelle Preis zu hoch ist, wird der ahtuelle Preis in die jeweillige Preisliste eingetragen
      # und die Auktion in das verloren-log eingetragen      
      else {
        print "Preis über $marktpreis: $npreis\n";
        $content = $produkt.": ".$npreis." -- über Maximalpreis\n";
        file_put_contents("verloren.txt", $content, FILE_APPEND);
        preisliste($produkt, $npreis);
      }
    } 
    sleep(10); # Warten um sicher zustellen, dass die Auktion beendet wurde

    # Anpassung des Marktwertes direkt nach Abschluss der Auktion
    if ($produkt == "Bier") {$bierpreis = marktwert("Bier");}
    if ($produkt == "Nudeln") {$nudelpreis = marktwert("Nudeln");}    
    if ($produkt == "Klopapier") {$klopreis = marktwert("Klopapier");}
    return 1; # Sprung zurück 
  }
?>