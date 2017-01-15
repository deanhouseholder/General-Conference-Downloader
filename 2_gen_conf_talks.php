<?php

function file_get_contents_utf8($fn) {
	$content = file_get_contents_utf8($fn);
	return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

$mysqli = new mysqli("localhost", "root", "", "conference");

/* check connection */
if ($mysqli->connect_errno) {
	printf("Connect failed: %s\n", $mysqli->connect_error);
	exit();
}

$mysqli->set_charset('utf8');

$article = array();

// Start with list of conferences
$conferences_list_url = "https://www.lds.org/general-conference/conferences?lang=eng";

// Fetch that page as html
$html_of_list_of_conferences = file_get_contents_utf8($conferences_list_url);

// Search that html for each conference
preg_match_all('~<a href="([^"]*)" class="year-line__link">~i', $html_of_list_of_conferences, $matches);

// Grab the matches for each conference
$urls_of_conferences = $matches[1];

// Loop through match and refer to each of them as "$url_of_conference"  Example: https://www.lds.org/general-conference/2016/04?lang=eng
foreach($urls_of_conferences as $url_of_conference){

	// Grab the url for this conference and fetch that page as html
	$html_of_conference = file_get_contents_utf8("https://www.lds.org" . $url_of_conference);

	// Search that html for each speaker
	preg_match_all('~<a href="([^"]*)" class="lumen-tile__link">~i', $html_of_conference, $matches);

	// Loop through each speaker
	foreach ($matches[1] as $i=>$url_of_article){

		// Get contents of article/talk as html
		$html = file_get_contents_utf8("https://www.lds.org" . $url_of_article);

		// Search html for Article Date
		preg_match('~<a class="sticky-banner__link" href=".*">([^<]*)</a>~i', $html, $matches);
		// Set an array date element to the match of what was searched for.  Etc. below
		$date = $matches[1];
		$article[$date][$i]['date'] = $matches[1];

		// Get article_id using the "data-doc-aid" in the <html> tag
		preg_match('~<html data-doc-aid="([^"]*)"~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['article_id'] = $matches[1];
		} else {
			$article[$date][$i]['article_id'] = null;
		}

		// Search html for Article Title
		preg_match('~<h1 class="title"><div>(.*)</div></h1>~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['title'] = $matches[1];
		} else {
			$article[$date][$i]['title'] = null;
		}

		// Search html for Article Author Title
		preg_match('~<p class="article-author__title">([^<]+)</p>~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['author_title'] = $matches[1];
		} else {
			$article[$date][$i]['author_title'] = "";
		}

		// Search html for Article Author
		preg_match('~<a class="article-author__name" href="[^"]*">.*By ([^<]*)</a>~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['author'] = $matches[1];
		} else {
			$article[$date][$i]['author'] = "";
		}

		// Search html for Article Subtitle
		preg_match('~<p class="kicker"[^>]*>(.*)</p>~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['subtitle'] = $matches[1];
		} else {
			$article[$date][$i]['subtitle'] = "";
		}

		// Search html for Article Body
		preg_match('~<div class="article-content">([\W\w]*</p></div>)~i', $html, $matches);
		if (isset($matches[1])){
			$article[$date][$i]['body'] = $matches[1];
		} else {
			$article[$date][$i]['body'] = "";
		}

		$article_id = $mysqli->real_escape_string($article[$date][$i]['article_id']);
		$newdate = date("Y-m-d", strtotime($article[$date][$i]['date']));
		$title = $mysqli->real_escape_string($article[$date][$i]['title']);
		$subtitle = $mysqli->real_escape_string($article[$date][$i]['subtitle']);
		$author_title = $mysqli->real_escape_string($article[$date][$i]['author_title']);
		$author = $mysqli->real_escape_string($article[$date][$i]['author']);
		$body = $mysqli->real_escape_string($article[$date][$i]['body']);

		$sql  = "INSERT INTO conference_articles (id, conference_date, article_title, article_subtitle, article_author_title, article_author_name, article_body) VALUES (";
		$sql .= "'" . (isset($article_id) ? $article_id : NULL) . "',";
		$sql .= "'" . $newdate . "', ";
		$sql .= "'" . strip_tags($title) . "', ";
		$sql .= "'" . strip_tags($subtitle) . "', ";
		$sql .= "'" . strip_tags($author_title) . "', ";
		$sql .= "'" . strip_tags($author) . "', ";
		$sql .= "'" . $body . "');\n\n";

		if ($result = $mysqli->query($sql)) {
			print "Added " . $article[$date][$i]['title'] . " to the DB\n";
		} else {
			print "Failed to add " . $article[$date][$i]['title'] . " to the DB\n";
		}
	}
}

