<?php

// URL to SEPTA stations grammar.
$grammar_url = "https://raw.github.com/gist/1180658/8d2f11ce4224d9c77626ec56c1988036e151de6b/septa-stops.xml";

// Voice to use when rendering TTS.
$tts_voice = "Victor";

// Set timeout basedon channel used.
$timeout = $currentCall->channel = "TEXT" ? 60.0 : 10.0;
$attempts = $currentCall->channel = "TEXT" ? 1 : 3;

// Options to use when asking the caller for input.
$options = array("choices" => $grammar_url, "attempts" => $attempts, "bargein" => false, "timeout" => $timeout, "voice" => $tts_voice);

if(!$currentCall->initialText) {

  // Greeting.
  say("Welcome to sep talking.  Use your voice to catch your train.", array("voice" => $tts_voice));
  
  // Get the name of the station caller is leaving from.
  $leaving_from = ask("What station are you leaving from?", $options);

}
else {
  $leaving_from = ask("", array("choices" => $grammar_url, "attempts" => 1, "bargein" => false, "timeout" => 60.0));
}

// Get the name of the station the caller is going to
$going_to = ask("What station are you going to?", $options);


// Fetch next to arrive information.
$url = "http://www3.septa.org/hackathon/NextToArrive/" . str_replace(" ", "%20", $leaving_from->value) . "/" . str_replace(" ", "%20", $going_to->value)."/1";
_log("*** $url ***");
$transit_info = json_decode(file_get_contents($url));

// Iterate over transit info array.
if(count($transit_info)) {

	for($i=0; $i < count($transit_info); $i++) {
		say("Train " . implode(" ", str_split($transit_info[$i]->orig_train)) . ".", array("voice" => $tts_voice));
		say("Leaving from " . $leaving_from->value . " at " . $transit_info[$i]->orig_departure_time . ".", array("voice" => $tts_voice));
		say("Arriving at " . $going_to->value . " at " . $transit_info[$i]->orig_arrival_time . ".", array("voice" => $tts_voice));
		if(strlen($transit_info[$i]->orig_delay) > 0) {
      		say("Currently running " .  $transit_info[$i]->orig_delay . " late.", array("voice" => $tts_voice));
		}
	}

}

// If an empty array returned.
else {
	say("I could not find any transit information for those stops.  Please try again later.", array("voice" => $tts_voice));
}

// Always be polite and say goodbye before hanging up.
say("Thank you for using sep talking.  Goodbye.", array("voice" => $tts_voice));
hangup();
?>
