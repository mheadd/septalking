<?php

// Base URL for the Next to Arrive API.
$nta_base_url = "http://www3.septa.org/hackathon/NextToArrive/";

// URL to SEPTA stations grammar.
$grammar_url = "septa-stops.xml";

// Voice to use when rendering TTS.
$tts_voice = "Victor";

// Number of train departures to return to user.
$num_trains = 1;

/**
 * Function to format train information for direct routes.
 */
function sayDirect($template, $train, $from, $to, $voice) {

	$delay = ($train->orig_delay == "On time") ? "  on schedule" : $train->orig_delay . " late.";
	$say = str_replace(
		array('%train_num%', '%from%', '%departure_time%', '%to%', '%arrive_time%', '%delay%'), 
		array(implode(" ", str_split($train->orig_train)), $from, trim($train->orig_departure_time), $to, trim($train->orig_arrival_time), $delay), 
		$template);
	say($say, array("voice" => $voice));	
}

/**
 * Function to format train information for indirect (multi-leg) routes.
 */
function sayInDirect($template, $train, $from, $to, $voice) {

	// Say connecting station.
	say("This trip has a connection at " . $train->Connection. ".", array("voice" => $voice));
	
	// Say first leg of trip.
	$delay = ($train->orig_delay == "On time") ? "  on schedule" : $train->orig_delay . " late.";
	$say1 = str_replace( 
		array('%train_num%', '%from%', '%departure_time%', '%to%', '%arrive_time%', '%delay%'), 
		array(implode(" ", str_split($train->orig_train)), $from, trim($train->orig_departure_time), $train->Connection, trim($train->orig_arrival_time), $delay), 
		$template);
	say($say1, array("voice" => $voice));	
	
	// Say second leg of trip.
	$delay = ($train->term_delay == "On time") ? "  on schedule" : $train->orig_delay . " late.";
	$say2 = str_replace( 
		array('%train_num%', '%from%', '%departure_time%', '%to%', '%arrive_time%', '%delay%'), 
		array(implode(" ", str_split($train->term_train)), $train->Connection, trim($train->term_depart_time), $to, trim($train->term_arrival_time), $delay), 
		$template);
	say($say2, array("voice" => $voice));	
	
}

// Settings based on channel used.
$timeout = ($currentCall->channel == "TEXT") ? 60.0 : 10.0;
$attempts = ($currentCall->channel == "TEXT") ? 1 : 3;

// Message templates to use when rendering train info. on voice / text channels.
$voice_template = "Train %train_num%, Leaving from %from% at %departure_time%, arriving at %to% at %arrive_time%, currently running %delay%";
$text_template = "Train %train_num% from %from% (%departure_time%) to %to% (%arrive_time%): %delay%.";
$template = ($currentCall->channel == "TEXT") ? $text_template : $voice_template;

// Options to use when asking the caller for input.
$options = array("choices" => $grammar_url, "attempts" => $attempts, "bargein" => false, "timeout" => $timeout, "voice" => $tts_voice);


if(!$currentCall->initialText) {

	// Greeting.
	say("Welcome to sep talking.  Use your voice to catch your train.", array("voice" => $tts_voice));

	// Get the name of the station caller is leaving from.
	$leaving_from = ask("What station are you leaving from?", $options);

}
else {
	$leaving_from = ask("", array("choices" => $grammar_url, "attempts" => $attempts, "bargein" => false, "timeout" => $timeout));
}

// Get the name of the station the caller is going to
$going_to = ask("What station are you going to?", $options);

// Fetch next to arrive information.
$url = $nta_base_url . str_replace(" ", "%20", $leaving_from->value) . "/" . str_replace(" ", "%20", $going_to->value)."/$num_trains";
$train_info = json_decode(file_get_contents($url));

// Iterate over train info array and return departure information.
if(count($train_info)) {
	for($i=0; $i < count($train_info); $i++) {
		if($train_info[$i]->isdirect == "true") {
			sayDirect($template, $train_info[$i], $leaving_from->value, $going_to->value, $tts_voice);
		}
		else {
			sayInDirect($template, $train_info[$i], $leaving_from->value, $going_to->value, $tts_voice);
		}
	}
}

// If an empty array is returned from NTA API.
else {
say("I could not find any transit information for those stops.  Please try again later.", array("voice" => $tts_voice));
}

// Always be polite and say goodbye before hanging up. ;-)
if($currentCall->channel == "VOICE") {
	say("Thank you for using sep talking.  Goodbye.", array("voice" => $tts_voice));
}
hangup();

?>
