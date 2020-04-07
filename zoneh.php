<?php
require 'simple_html_dom.php';

if(!isset($argv[1]) || !isset($argv[2]) || !isset($argv[3])) die('Syntax: ./script <COOKIE_PHPSESSID> <COOKIE_ZONEH> <DOMAIN> <PAGE_NUMBER>');
//test change
$COOKIE_PHPSESSID = $argv[1];
$COOKIE_ZONEH = $argv[2];
$DOMAIN_SEARCH = $argv[3];
$url = 'http://www.zone-h.org/archive/filter=1/domain='.$DOMAIN_SEARCH.'/fulltext=1/page='.$argv[4];
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// get headers too with this line
curl_setopt($ch, CURLOPT_URL, $url );
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0 );
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36" );
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: max-age=0','Cookie: ZHE='.$COOKIE_ZONEH.'; PHPSESSID='.$COOKIE_PHPSESSID,'Host: www.zone-h.org','Referer: http://www.zone-h.org/archive/']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1 );
curl_setopt($ch, CURLOPT_TIMEOUT, 20 );
$response = curl_exec($ch);
curl_close($ch);

$dataShow = [];
$responseData = [];
$total = 1;
try {
	$html = str_get_html($response);
	$table = $html -> find('table[id="ldeface"]',0);
	$rows = $table -> find('tr');
	$totalRows = count($rows);
	foreach($rows as $row)
	{
		$cells = $row -> find('td');
		foreach($cells as $cell) {
			$dataShow[$total][] = trim($cell->innertext);
		}
		$total++;
	}
	unset($dataShow[count($dataShow)]); // removes the latest comment from zone-h
	$pages = explode(',',trim(str_replace(['|',' '],['',','],preg_replace('#<[^>]+>#','|',$dataShow[count($dataShow)][0])))); // get pages of current proccess
	unset($dataShow[count($dataShow)]);

	// build from here normal array
	unset($dataShow[1]); // removing the titles

	foreach($dataShow as $row) {
		$responseData[] = [
			'published_at' => $row[0],
			'published_by' => strip_tags($row[1]),
			'zoneh_profile' => 'https://www.zone-h.org/archive/notifier='.urlencode(strip_tags($row[1])),
			'location_ip' => $row[5],
			'url' => $row[7],
			'dns_info' => ['hostname' => gethostbyaddr(gethostbyname(parseUrl($row[7])['host'])),'NS' => dns_get_record(parseUrl($row[7])['host'],DNS_NS)],
			'server_os' => $row[8],
			'deface_url' => 'https://www.zone-h.org'.str_replace(['<a href="','">mirror</a>'],'',$row[9]),
			'defacements' => [
				'homepage' => (!empty($row[2])),
				'server_mass' => (!empty($row[3])),
				'site_mass' => (!empty($row[4])),
				'ip_addr_location' => (!empty($row[4]))
			]
		];
	}


	$responseData['pages'] = $pages[count($pages)-1];
	echo json_encode($responseData);
} catch (Exception $e) {
	die('Error:'.$e->getMessage());
}



// functions
function parseUrl($url) {
    $url = (isset(parse_url($url)['scheme']) ? $url : 'http://'.$url);
    return parse_url($url);
}
