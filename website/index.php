<?php 

// Include Limonade Framwork
require 'classes/limonade.php';

/**
 * Function to read template file return contents.
 */
function readTemplateFile($name) {
	$filename = $name;
	$handle = fopen($filename, "r");
	$contents = fread($handle, filesize($filename));
	fclose($handle);
	return $contents;
}

/**
 * Function to process template.
 */
function processTemplate($path) {
	
	// Get template files.
	$base_template = readTemplateFile("index.tpl");
	$page_contents = readTemplateFile("$path.tpl");
	
	// Build navigation.
	$tokens = array('%content%', "%$path%");
	if($path == 'home') {
		array_push($tokens, '%about%', '%contact%');
	}
	elseif ($path == 'about') {
		array_push($tokens, '%home%', '%contact%');
	}
	else {
		array_push($tokens, '%home%', '%about%');
	}
	
	// Process template and return markup.
	return str_replace($tokens, array($page_contents, 'active', 'inactive', 'inactive'), $base_template);
}

/**
 * Function to render page based on route.
 */
function renderPage() {
	$path = (strlen(params('path')) == 0) ? 'home' : params('path');
	print processTemplate($path);
}

// Route for page requests.
dispatch_get('/:path', 'renderPage');

run();

?>