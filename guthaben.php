<?php 

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
  function marktwert($name) {
    if (url_online('http://dl6mhw.de/~corona/sasi/leader.php')) {
      $html = file_get_contents('http://dl6mhw.de/~corona/sasi/index.php');
      $lines = explode("</td><td>", $html); 
      $arr = array();
      $x = 0;
      foreach ($lines as &$value) {
          if ($value==$name) {
            array_push($arr, $lines[$x - 1]);
          }
          $x += 1;
        }  
      if (array_sum($arr) > 0) {
        $konto = 1000000 - array_sum($arr);
        print("Kontostand von $name: $konto\n\n");
        }  
      else {
        print("Diese Person ist nicht bekannt!\n\n");
      }
    }
    else {
      print("nicht online\n");
    }
  }
  $FLAG = true;
  while ($FLAG == true) {
    $person = readline("Kontostand von welcher Person? ('exit' zum Beenden)\n");
    if ($person == "exit") {
      print("Abfrage beendet.\n");
      $FLAG = false;
      break;
    }
    marktwert($person);
  }  

?>