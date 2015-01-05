#!/usr/bin/php
<?php
include_once "/hp/bl/ab/rl/www/inc/dbconn.php";
include_once "/hp/bl/ab/rl/www/inc/apiconn.php";
include_once "TwitterAPIExchange.php";

if ($argc > 1) {

  // Twitter settings
  $settings = array(
    "consumer_key" => DNTWITTER_CONSUMER_KEY,
    "consumer_secret" => DNTWITTER_CONSUMER_SECRET,
    "oauth_access_token" => DNTWITTER_OAUTH_ACCESS_TOKEN,
    "oauth_access_token_secret" => DNTWITTER_OAUTH_ACCESS_TOKEN_SECRET
  );
  $url = "https://api.twitter.com/1.1/statuses/update.json";
  $reqMethod = "POST";

  // Database settings
  $dbh = new PDO("mysql:host=" . DNDBHOST . ";dbname=" . DNDBNAME . ";charset=utf8", DNDBUSER, DNDBPASS);

  // Mail setting
  $additional_headers = "From: DARKNEuSS.de (via Twitter) <postfach@darkneuss.de>\r\n" .
                        "Content-Type: text/plain; Charset=UTF-8";

  switch($argv[1]) {

    case "record":
      $stmt = $dbh->prepare("SELECT id, artist, artist_twitter, title, releasedate, medium FROM dncake_records WHERE tweet_id IS NULL AND releasedate >= CURRENT_TIMESTAMP() AND confirmed = 1 AND platform <> 'sze' ORDER BY releasedate ASC LIMIT 1");
      if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        if ($result) {
          // Set point of sale
          $pointOfSale = array("cd" => "im Plattenladen", "mag" => "beim Zeitschriften-Onkel", "dvd" => "in der DVD-Abteilung", "book" => "im Buchhandel");
          // Set name to post
          $nameToPost = ($result->artist_twitter) ? "@" . $result->artist_twitter : $result->artist;
          // Set Twitter status
          $postfields = array(
            "status" => sprintf("bald %s: %s - „%s” (%s) – http://darkneuss.de/veroeffentlichungen", $pointOfSale[$result->medium], $nameToPost, $result->title, date("d.m.Y", strtotime($result->releasedate)))
          );
          // Send tweet
          $twitter = new TwitterAPIExchange($settings);
          $twitter->buildOauth($url, $reqMethod);
          $twitter->setPostfields($postfields);

          if ($response = json_decode($twitter->performRequest())) {
            // Update database table with tweet_id
            $updatestmt = $dbh->prepare("UPDATE dncake_records SET tweet_id = :tweetid WHERE id = :id");
            $updatestmt->bindValue(":tweetid", $response->{'id_str'}); // @TODO: why brackets?
            $updatestmt->bindParam(":id", $result->id);

            if ($updatestmt->execute()) {
              // Send mail notification
              mail("postfach@darkneuss.de", mb_encode_mimeheader($result->artist . " - " . $result->title, "UTF-8") . " (" . date("d.m.Y", strtotime($result->releasedate)) . ")", "https://twitter.com/DARKNEuSSde/status/" . $response->id_str, $additional_headers);
            }
            else {
              // Send mail with error information
              mail("postfach@darkneuss.de", "Fehlerhaftes Record-Update", "id: " . $result->id . "\r\nerrorInfo: " . $updatestmt->errorInfo(), $additional_headers);
            }
          }
        }
      }
      break;

    case "event":
      $stmt = $dbh->prepare("SELECT ev.id, ev.title, ev.date, loc.name
                             FROM dncake_events AS ev, dncake_locations AS loc
                             WHERE ev.tweet_id IS NULL
                             AND ev.location_id = loc.id
                             AND ev.date >= CURRENT_TIMESTAMP()
                             AND ev.confirmed = 1
                             ORDER BY ev.date ASC LIMIT 1"
                           );
      
      if ($stmt->execute()) {
        
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result) {

          // Set Twitter status
          $postfields = array(
            "status" => sprintf("%s (%s, %s) – http://darkneuss.de/kalender/details/%s", $result->title, date("d.m.Y", strtotime($result->date)), $result->name, $result->id)
          );

          // Send tweet
          $twitter = new TwitterAPIExchange($settings);
          $twitter->buildOauth($url, $reqMethod);
          $twitter->setPostfields($postfields);

          if ($response = json_decode($twitter->performRequest())) {
            // Update database table with tweet_id
            $updatestmt = $dbh->prepare("UPDATE dncake_events SET tweet_id = :tweetid WHERE id = :id");
            $updatestmt->bindValue(":tweetid", $response->{'id_str'});
            $updatestmt->bindParam(":id", $result->id);

            if ($updatestmt->execute()) {
              // Send mail notification
              mail("postfach@darkneuss.de", mb_encode_mimeheader($result->title, "UTF-8") . " (" . date("d.m.Y", strtotime($result->date)) . ", " . $result->name . ")", "http://darkneuss.de/kalender/details/" . $result->id . "\r\nhttps://twitter.com/DARKNEuSSde/status/" . $response->id_str, $additional_headers);
            }
            else {
              // Send mail with error information
              mail("postfach@darkneuss.de", "Fehlerhaftes Event-Update", "id: " . $result->id . "\r\nerrorInfo: " . $updatestmt->errorInfo(), $additional_headers);
            }
          }

        }

      }
      break;
      
    case "festival":
      $stmt = $dbh->prepare("SELECT id, title, date, DATEDIFF(date, NOW()) AS days
                             FROM dncake_events
                             WHERE cat = 'Festival'
                             AND tweet_count <= 10
                             AND platform != 'sze'
                             AND confirmed = 1
                             AND date >= CURRENT_TIMESTAMP()
                             ORDER BY RAND() LIMIT 1"
                           );

      if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        if ($result) {
          $postfields = array("status" => sprintf("Festival-Countdown: In %d Tagen (am %s) beginnt das %s – http://darkneuss.de/kalender/details/%s", $result->days, date("d.m.Y", strtotime($result->date)), $result->title, $result->id));
          $twitter = new TwitterAPIExchange($settings);
          $twitter->buildOauth($url, $reqMethod);
          $twitter->setPostfields($postfields);
          if ($response = json_decode($twitter->performRequest())) {
            // Update tweet_count after performing the request
            $updatestmt = $dbh->prepare("UPDATE dncake_events SET tweet_count = tweet_count + 1 WHERE id = :id");
            $updatestmt->bindParam(":id", $result->id);

            if ($updatestmt->execute()) {
              mail("postfach@darkneuss.de", "Neuer Festival-Countdown", "zum " . $result->title . "\r\nhttps://twitter.com/DARKNEuSSde/status/" . $response->id_str, $additional_headers);
            }
          }
        }
      }
      break;

    case "news":
      $stmt = $dbh->prepare("SELECT * FROM dncake_news WHERE id > 13 AND tweet_id IS NULL AND published = 1 ORDER BY created ASC LIMIT 1");
      
      if ($stmt->execute()) {
        
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result) {

          // Set Twitter status
          $postfields = array(
            "status" => sprintf("News: %s – http://darkneuss.de/news/%s", $result->title, $result->url_id)
          );

          // Send tweet
          $twitter = new TwitterAPIExchange($settings);
          $twitter->buildOauth($url, $reqMethod);
          $twitter->setPostfields($postfields);

          if ($response = json_decode($twitter->performRequest())) {
            // Update database table with tweet_id
            $updatestmt = $dbh->prepare("UPDATE dncake_news SET tweet_id = :tweetid WHERE id = :id");
            $updatestmt->bindValue(":tweetid", $response->{'id_str'});
            $updatestmt->bindParam(":id", $result->id);

            if ($updatestmt->execute()) {
              // Send mail notification
              mail("postfach@darkneuss.de", mb_encode_mimeheader($result->title, "UTF-8"), "http://darkneuss.de/news/" . $result->url_id . "\r\n\r\nhttps://twitter.com/DARKNEuSSde/status/" . $response->id_str, $additional_headers);
            }
            else {
              // Send mail with error information
              mail("postfach@darkneuss.de", "Fehlerhaftes News-Update", "id: " . $result->id . "\r\nerrorInfo: " . $updatestmt->errorInfo(), $additional_headers);
            }
          }

        }

      }
      break;

    default:
  }

}
