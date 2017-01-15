<?php

function file_get_contents_utf8($fn) {
	$content = file_get_contents_utf8($fn);
	return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

$pages = file("1_gen_conf_pages.txt");
$i = 0;

require_once('replace_bad_chars.php');

foreach ($pages as $page) {
	$i++;
	for ($j=1; $j<=5; $j++ ){
		$page = trim($page);
		$html = file_get_contents_utf8($page . "&page=" . $j);

		if (preg_match_all('~<a href="(/general-conference/[0-9]+.*)" class="lumen-tile__link">~i', $html, $matches)){
			foreach ($matches[1] as $link) {
				$links[$i][$j][] = "https://www.lds.org" . $link;
			}
		}

		if (preg_match('~<h1 class="title">([^<]+)</h1>~', $html, $matches)){
			$name = $matches[1];
		}

		if (empty($links[$i][$j]) || count($links[$i][$j]) == 0){
			continue;
		}

		print "Downloading " . count($links[$i][$j]) . " MP3's for $name\n";

		foreach ($links[$i][$j] as $link){
			$html = file_get_contents_utf8($link);

			if (preg_match('~sticky-banner__link.*eng">([^<]+)<~i', $html, $matches)){
				$date = date("Y-m", strtotime($matches[1]));
				if (preg_match('~<h1 class="title"><div>([^<]+)<~i', $html, $matches)){
					$title = $matches[1];
					print "Title Before: $title\n";
					$title = str_replace($find, $replace, $title);
					print "Title After:  $title\n";
					if (preg_match('~<source src="(.*\.mp3)">~i', $html, $matches)){
						$file_path = $matches[1];
						$file = file_get_contents($file_path);
//						if (!file_exists($name)){
//							mkdir($name);
//						}
//						file_put_contents($name . "/" . $name . " - " . $date . " - " . $title . ".mp3", $file);
						file_put_contents($name . " - " . $date . " - " . $title . ".mp3", $file);
					}
				}
			}
		}
	}
}
