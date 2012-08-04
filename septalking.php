<?php

// Base URL for the Next to Arrive API.
define("NTA_BASE_URL", "http://www3.septa.org/hackathon/NextToArrive/");

// Base URL for Coordinates API.
define("COORDINATES_BASE_URL", "http://setpalocations.phpfogapp.com/index.php");

// Base URL for the System Location API.
define("LOCATION_BASE_URL", "http://www3.septa.org/hackathon/locations/get_locations.php");

// URL to SEPTA stations grammar.
define("GRAMMAR_URL", "https://raw.github.com/mheadd/septalking/master/septa-stops.xml");

// Voice to use when rendering TTS.
define("TTS_VOICE_NAME", "Vanessa");

// Number of train departures to return to user.
define("NUM_TRAINS", 1);

// Confidence level to use for confirming input.
define("CONFIDENCE_LEVEL", .43);

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

/**
 * Function to ask caller for station name.
 */
function getStationName($prompt, $options) {
	$station = ask($prompt, $options);

	if($station->value == 'NO_MATCH') {
		say("Sorry, I dont recognize that station.", array("voice" => $options["voice"]));
		return getStationName($prompt, $options);
	}

	// Attempts over.
	if($station->value == '') {
		say("Sorry, I did not get your response. Please try again later. Goodbye", array("voice" => $options["voice"]));
		hangup();
	}

	if($station->choice->confidence < CONFIDENCE_LEVEL) {
		say("I think you said, " . $station->value . ".", array("voice" => $options["voice"]));
		if(confirmEntry($options["voice"])) {
			return $station->value;
		}
		else {
			_log("*** Caller rejected recognized input. ***");
			return getStationName($prompt, $options);
		}
	}
	else {
		return $station->value;
	}
}

/**
 * Helper function to confirm entry.
 */
function confirmEntry($voice) {
	$confirm = ask("Is that correct?", array("choices" => "yes,no", "voice" => $voice));
	return $confirm->value == "yes" ? true : false;
}

/**
 * Helper function to format station names for NTA API
 */

function formatStationName($name) {
	return str_replace(" ", "%20", ucwords($name));
}

// Settings based on channel used.
$timeout = ($currentCall->channel == "TEXT") ? 60.0 : 10.0;
$attempts = ($currentCall->channel == "TEXT") ? 1 : 3;
$choices = ($currentCall->channel == "TEXT") ? "[ANY]" : GRAMMAR_URL ;

// Message templates to use when rendering train info. on voice / text channels.
$voice_template = "Train %train_num%, Leaving from %from% at %departure_time%, arriving at %to% at %arrive_time%, currently running %delay%";
$text_template = "Train %train_num% from %from% (%departure_time%) to %to% (%arrive_time%): %delay%.";
$template = ($currentCall->channel == "TEXT") ? $text_template : $voice_template;

// Options to use when asking the caller for input.
$options = array("choices" => $choices, "attempts" => $attempts, "bargein" => false, "timeout" => $timeout, "voice" => TTS_VOICE_NAME);


if(!$currentCall->initialText) {

	// Greeting.
	say("Welcome to sep talking.  Use your voice to catch your train.", array("voice" => TTS_VOICE_NAME));

	// Get the name of the station caller is leaving from.
	$leaving_from = getStationName("What station are you leaving from?", $options);

}
else {
	$leaving_from = ask("", array("choices" => "[ANY]", "attempts" => $attempts, "bargein" => false, "timeout" => $timeout));
}

$leaving = is_object($leaving_from) ? $leaving_from->value : $leaving_from;

// Get the name of the station the caller is going to
$going_to = getStationName("What station are you going to?", $options);

// NTA API requires all station names to be proper cased and URL encoded.
$departing_station = formatStationName($leaving);
$arriving_station =  formatStationName($going_to);

// Fetch next to arrive information.
$train_info = json_decode(file_get_contents(NTA_BASE_URL . $departing_station . "/" . $arriving_station."/". NUM_TRAINS));

// Iterate over train info array and return departure information.
if(count($train_info) > 0) {
	
	for($i=0; $i < count($train_info); $i++) {
		if($train_info[$i]->isdirect == "true") {
			sayDirect($template, $train_info[$i], $leaving, $going_to, TTS_VOICE_NAME);
		}
		else {
			sayInDirect($template, $train_info[$i], $leaving, $going_to, TTS_VOICE_NAME);
		}
	}

	// Look up coordinates of departing regional rail station.
	if($currentCall->channel == "VOICE") {
		say("Please hold on to hear the closest sales location.");
	}
	
	$coordinates = json_decode(file_get_contents(COORDINATES_BASE_URL . '?station_name=' . $departing_station));

	// Look up sales locations near departing regional rail station.
	$locations = json_decode(file_get_contents(LOCATION_BASE_URL . '?lon=' . $coordinates->stop_lon . '&lat=' . $coordinates->stop_lat . '&radius=1&type=sales_locations'));
	
	// Say details of closest sales location.
	$closest_location = $locations[0]->location_name;
	
	if($currentCall->channel == "VOICE") {
		say('The closest sales location to your departing station is, ' . $closest_location);
	}
	else {
		say('Buy tickets here: ' . $closest_location);
	}

}

// If an empty array is returned from NTA API.
else {
	say("I could not find any transit information for trains running from " . $leaving . " to " . $going_to . ".  Please try again later.",  array("voice" => TTS_VOICE_NAME));
}

// Always be polite and say goodbye before hanging up. ;-)
if($currentCall->channel == "VOICE") {
	say("Thank you for using sep talking.  Goodbye.", array("voice" => TTS_VOICE_NAME));
}
hangup();

?>
