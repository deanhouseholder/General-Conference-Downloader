<?php

$dir_prefix = "by-Conference/";

function file_get_contents_utf8($fn) {
	$content = file_get_contents($fn);
	return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function download_urls($urls_of_conferences) {

	// make variables find and replace global to the function
	global $find, $replace;

	// Loop through match and refer to each of them as "$url_of_conference"
	// Example: https://www.lds.org/general-conference/2016/04?lang=eng
	foreach($urls_of_conferences as $url_of_conference){

		global $dir_prefix;

		// Grab the url for this conference and fetch that page as html
		$html_of_conference = file_get_contents_utf8("https://www.lds.org" . $url_of_conference);

		// Search that html for each speaker
		preg_match_all('~<a href="([^"]*)" class="lumen-tile__link">~i', $html_of_conference, $matches);

		// Loop through each speaker
		$number = 0;
		foreach ($matches[1] as $i=>$url_of_article){

			// Get contents of article/talk as html (now in a double-loop)
			$html = file_get_contents_utf8("https://www.lds.org" . $url_of_article);

			// Search html for Article Date
			preg_match('~<a class="sticky-banner__link" href=".*">([^<]*)</a>~i', $html, $matches);

			// Set an array date element to the match of what was searched for.  Etc. below
			$date = $matches[1];
			$article[$date][$i]['date'] = $matches[1];

			// Search html for Article Title
			preg_match('~<h1 class="title"><div>(.*)</div></h1>~i', $html, $matches);
			if (isset($matches[1])){
				$article[$date][$i]['title'] = strip_tags(str_replace($find, $replace, $matches[1]));
			} else {
				$article[$date][$i]['title'] = null;
			}

			// Search html for Article Author
			preg_match('~<a class="article-author__name" href="[^"]*">([^<]*)</a>~i', $html, $matches);
			if (isset($matches[1])){
				$article[$date][$i]['author'] = str_replace("Bishop ", "", str_replace("President ", "", str_replace("Elder ", "", str_ireplace("By ", "", str_ireplace("Presented ", "", str_replace($find, $replace, $matches[1]))))));
			} else {
				$article[$date][$i]['author'] = null;
			}

			// Search html for download link (url)
			preg_match('~href="([^"]*)">MP3</a>~i', $html, $matches);
			if (isset($matches[1])){
				$article[$date][$i]['link'] = $matches[1];
			} else {
				$article[$date][$i]['link'] = null;
			}

			// If it's not a video
			if (!is_null($article[$date][$i]['author'])){

				// Set some variables
				$number++;
				if ($number <= 9){
					$number = "0" . $number;
				}
				$filedate = date("Y-m", strtotime($date));
				$filename = $number . " - " . $article[$date][$i]['author'] . " - " . $article[$date][$i]['title'] . ".mp3";
				$path = $dir_prefix . $filedate . "/" . $filename;

				// Check if MP3 is already downloaded (proceed if it isn't)
				if (!file_exists($path)){

					// Download MP3 into a variable
					$file = file_get_contents($article[$date][$i]['link']);

					// Check if Directory exists, if not create it
					if (!file_exists($dir_prefix . $filedate . "/")){
						mkdir($dir_prefix . $filedate . "/");
					}

					// Write MP3 variable to the filename we defined above
					file_put_contents($path, $file);

					print "Downloaded $filename\n";
				} else {
					// MP3 was previously downloaded
					print "Skipped $filename\n";
				}
			}
		}
	}
}

$article = array();

require_once('replace_bad_chars.php');

// Start with list of conferences
$conferences_list_url = "https://www.lds.org/general-conference/conferences?lang=eng";

// Fetch that page as html
$html_of_list_of_conferences = file_get_contents_utf8($conferences_list_url);

// Search that html for each conference
preg_match_all('~<a href="([^"]*)" class="year-line__link">~i', $html_of_list_of_conferences, $matches);

// Grab the matches for each conference
$urls_of_conferences = $matches[1];
rsort($urls_of_conferences);

// Check for user input from the command line.
if (isset($argv[1])){
	// Loop through each conference URL
	foreach ($urls_of_conferences as $key=>$url){

		// Parse out date from conference url
		preg_match("~.*([0-9][0-9][0-9][0-9]/[0-9][0-9]).*~", $url, $matches);

		// Check for valid matches
		if (isset($matches[1])){

			// Replace / character with - character
			$date = str_replace("/", "-", $matches[1]);

			// Check if current looping date matches the date input from the user
			if ($date == $argv[1]){

				// Build new array of url's which match the input
				$new_urls_of_conferences[] = $url;
			}
		}
	}
	// If no valid user matches are found display error message
	if (empty($new_urls_of_conferences)){
		print "Your input is not in a Year, Month date stamp. (YYYY-MM)\nOr the date is older than 1971";
		die();
	}

	// Call the function and pass in the array of matches
	download_urls($new_urls_of_conferences);
} else {
	// Call the function and pass in the array of matches
	download_urls($urls_of_conferences);
}
