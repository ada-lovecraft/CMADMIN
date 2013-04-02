<?php

$f3=require('lib/base.php');


// Initialize CMS
$f3->config('app/config.ini');

// Define routes
$f3->config('app/routes.ini');

$f3->route('GET /getFeed/@feedID', 'Api->getNewFeed');

$f3->route('GET /getFeed/feedUrl=@feedStuff', 'Api->getFeed');
$f3->route('GET /editFeed/@id','ToolBox->showEditFeed');
$f3->route('POST /editFeed/@id','ToolBox->updateFeed');


$f3->route('GET /listFeeds [ajax]','ToolBox->listFeeds');
$f3->route('GET /listPolls [ajax]','ToolBox->listPolls');


function getBaseUrl() {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")) . $s;
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
        return urldecode($protocol . "://" . $_SERVER['SERVER_NAME'] . $port . F3::get('BASE').'/');
    }

// Execute application
$f3->run();

/*

$f3->route('GET /',
	function($f3) {
		if ($f3->get('SESSION.auth') == 'loggedin') {
			$f3->set('inc','views/index.htm');
			echo Template::instance()->render('views/layout.htm');	
		}
		else
			echo View::instance()->render('views/login.htm');
	}
);

$f3->route('POST /',
	function($f3) {
		$USER = 'admin';
		$PASSWORD = 'password';
		error_log($f3->get('POST.user'));
		error_log($f3->get('POST.password'));
		if ($f3->get('POST.user') == $USER && $f3->get('POST.password') == $PASSWORD) {
			$f3->set('SESSION.auth','loggedin');
			echo Template::instance()->render('views/layout.htm');	
		} else {
			echo View::instance()->render('views/login.htm');	
		}
			
	}
);

$f3->route('GET /feedgen', 
	function() {
			echo Template::instance()->render('views/layout.htm');	
	}
);

$f3->route('POST /feedgen',
	function($f3) {
		$feedURLs = array();
		foreach($f3->get('POST.feedUrl') as $feedUrl) {
			if (!empty($feedUrl))
				array_push($feedURLs, base64_encode(($feedUrl)));
		}

		$url = 'feedUrl='.implode(',',$feedURLs);

		$itemCount = $f3->get('POST.itemCount');
		$cacheMinutes = $f3->get('POST.cacheMinutes');
		$allowImages = $f3->get('POST.allowImages');
		$isFacebook = $f3->get('POST.isFacebook');
		$resizeImages = $f3->get('POST.resizeImages');
		$maxWidth = $f3->get('POST.maxWidth');
		$maxHeight = $f3->get('POST.maxHeight');

		if($itemCount) 
			$url .= ';itemCount=' . $itemCount;

		if ($cacheMinutes)
			$url .= ';cacheMinutes=' . $cacheMinutes;

		if ($allowImages)
			$url .= ';allowImages=' . $allowImages;

		if ($isFacebook)
			$url .= ';isFacebook=' . $isFacebook;

		if ($resizeImages) {
			$url .= ';resizeImages='.$resizeImages;
			if ($maxWidth)
				$url .= ';maxWidth='.$maxWidth;
			if($maxHeight)
				$url .= ';maxHeight='.$maxHeight;
		}
		
		echo '<a href="http://admin.cmads.dev/getFeed/' . $url . '">Click</a>';

	}
);

$f3->route('GET /getFeed/@feedID', function($f3) {
	echo 'New Feed Style: ' . $f3->get('PARAMS.feedID');
});

$f3->route('GET /getFeed/feedUrl=@feedStuff', function($f3) {
	require_once 'feedFunctions.php';
	$IMAGE_CACHE_URL = "http://cmadsapi.technoratimedia.com/";
	parseUrlParams();
	$feedUrls = isset($_GET['feedUrl']) ? (is_array($_GET['feedUrl']) ? $_GET['feedUrl'] : array($_GET['feedUrl'])) : null;
	$itemsToShow = max(isset($_GET['itemCount']) && is_numeric($_GET['itemCount']) ? intval($_GET['itemCount']) : 10, 1); // Default: 10 items. Minimum: 1 item.
	$cacheSeconds = max(isset($_GET['cacheMinutes']) && is_numeric($_GET['cacheMinutes']) ? intval($_GET['cacheMinutes']) : 15, 1) * 60; // Default: 15 minutes. Minumum: 1 minute.
	$allowImages = isset($_GET['allowImages']) ? $_GET['allowImages'] : false;
	$isFacebook =  isset($_GET['isFacebook']) ? $_GET['isFacebook'] : false;
	$resizeImages = isset($_GET['resizeImages']) ? $_GET['resizeImages'] : false;
	$maxWidth = isset($_GET['maxWidth']) ? $_GET['maxWidth'] : null;
	$maxHeight = isset($_GET['maxHeight']) ? $_GET['maxHeight'] : null;



	if (empty($feedUrls)) {
    	header('HTTP/1.0 400 Bad Request');
    
    	// Set the expires header to a very short period of time in case the CDN decides to cache a bad request
    	setCacheHeaders(30);
    	echo 'The feedUrl parameter is required.';
    	exit;
	}

	for ($i = 0; $i < count($feedUrls); $i++) {    
	    $feedUrls[$i] = base64_decode($feedUrls[$i]);
	}

	// Set the expires header so the CDN will cache the response for the appropriate amount of time
	setCacheHeaders($cacheSeconds);

	include_once('SimplePie/autoloader.php');

	$feed = new SimplePie();
	$feed->set_feed_url($feedUrls); // set the URL we're going to load
	$feed->set_timeout(5); // set a 5 second timeout
	$feed->enable_cache(true); // enable caching
	$feed->set_cache_duration($cacheSeconds);
	$feed->set_cache_location('./feedCache');
	$feed->init(); // get the feed

	// This makes sure that the content is sent to the browser as application/rss+xml and the UTF-8 character set (since we didn't change it).
	$feed->handle_content_type('application/rss+xml');


	// Generate the feed
	$output = '<?xml version="1.0" encoding="UTF-8" ?>
	    <rss version="2.0">
	        <channel>
	        <title><![CDATA[Technorati CMAD Feed]]></title>
	        <link>'.htmlspecialchars('http://cmadsapi.technoratimedia.net'.$_SERVER['REQUEST_URI']).'</link>
	        <description><![CDATA[Conversational Ad Posts]]></description>
	        <pubDate>Tue, 015 Mar 2011 00:0:01 +0000</pubDate>
	        <generator>ScriveCMS</generator>
	        <language>en-us</language>
	        <docs>http://blogs.law.harvard.edu/tech/rss</docs>
	    ';

	// Cycle through the feed and display only the number of items that were requested
	foreach ($feed->get_items(0, $itemsToShow) as $item) {
	    // Remove/fix any HTML in the title
	    $title = strip_tags(html_entity_decode($item->get_title()));
	    
	    // Remove/fix any HTML in the description and trim it to 200 chars
	    
	    //$description = strip_tags(html_entity_decode(convertDoc2HTML($item->get_description())));
	    $description = html_entity_decode(convertDoc2HTML($item->get_description()));
	    
	    
	    if ($allowImages) {
	    
	      //use md5 and log image names
	    
	       $doc = new DOMDocument();
	       @$doc->loadHTML($description);
	       $tags = $doc->getElementsByTagName('img');
	    
	       $images = array();
	       $postImages = array();
	        //$images = "<images>\n";
	        foreach ($tags as $tag) {
	               $images[] =  $tag->getAttribute('src');
	        }
	        //$images .= "\t\t\t</images>";
	        
	        foreach ($images as $image) {
	         
	         
	            $parse = parse_url($image);
	            //$domain = $parse['host'];
	            //$domain = str_ireplace('.', '_', $domain);
	            $pathList = explode("/",$parse['path']);
	            $file = $pathList[count($pathList) -1];
	            $resizeAddition = '';
	            $ratio;

	            //if it's a facebook feed, replace _s(small) with _b(big)
	            
	            if ($isFacebook) {
	                $image = preg_replace("/_s/", "_b", $image);
	            }

	            if ($resizeImages) {
	            	$resizeAddition = '_resized';
	            }

	            $fileExt = explode(".",$file);

	            
	            $fileName = "imageCache/" . md5($image) . $resizeAddition . "." . $fileExt[1];
		        
	            $postImages[] = $IMAGE_CACHE_URL . $fileName;
	            
	            
	            if (!file_exists($fileName)) {
	            	if ($resizeImages) {
		                $simpleImage = new SimpleImage();
		                $simpleImage->load($image);
		                $w = $simpleImage->getWidth();
		                $h = $simpleImage->getHeight();
		                if ($w > $h) {
		                	$ratio = $h / $w;
		                	$newWidth = $maxWidth;
		                	$newHeight = $newWidth * $ratio;
		                } else {
		                	$ratio = $w / $h;
		                	$newHeight = $maxHeight;
		                	$newWidth = $newHeight * $ratio;
		                }
		                $simpleImage->resize($newWidth,$newHeight);
		                $simpleImage->save($fileName);
		            } else {
	                	$content = file_get_contents($image);
	                	file_put_contents($fileName,$content);
	                }

	                $file = "imageCache.log";
	                $fh = fopen($file, 'a') or die("cannot open file");
	                $logData = $image . " :: " . $fileName . "\n";
	                fwrite($fh,$logData);
	                fclose($fh);
	                
	                
	            }
	            
	            
	        }	        
	        
	        $imageOut = "\n\t\t<images>\n";
	        foreach ($postImages as $image)
	        {
	            $imageOut .= "<image>".  $image .  "</image>\n";
	        }
	        $imageOut .="\t\t</images>";
	    }
	    
	    
	    
	    $description = strip_tags($description);
	    
	    if (strlen($description) > 200)
	        $description = substr($description, 0, 200).'...';

	    $output = $output .'
	        <item>
	            <title><![CDATA[' . $title . ']]></title>
	            <link>' . htmlspecialchars($item->get_link()) . '</link>
	            <guid>' . htmlspecialchars($item->get_id()) . '</guid>
	            <description><![CDATA[' . $description . ']]></description>
	            ' . $imageOut . '
	            <pubDate>' . $item->get_date() . '</pubDate>
	        </item>';
	}

	// Close off the open tags
	$output = $output . '</channel></rss>';

	echo $output;

});


$f3->run();
*/
