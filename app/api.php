<?php

class Api extends Controller{

	 
		private $feedUrls = null;
		private $itemsToShow = null;
		private $cacheSeconds = null;
		private $allowImages = null;
		private $isFacebook = null;
		private $resizeImages = null;
		private $maxWidth = null;
		private $maxHeight = null;
		private $orientation = null;

	function getNewFeed() {
		$f3 = $this->framework;
		$db = $this->db;
		$feed_id = $f3->get('PARAMS.feedID');
		$results = $db->exec("SELECT a.*, group_concat(b.feed_url,' ') as urls FROM feeds as a JOIN feedUrls as b ON a.id = b.feed_id where a.id=" . $feed_id . " GROUP BY a.id;");
		$row = $results[0];
		$this->feedUrls = explode(' ',$row['urls']);
		$this->itemsToShow = $row['itemCount'];
		$this->cacheSeconds = $row['cacheTime'] * 60;
		$this->allowImages = $row['allowImages'] == 1 ? true : false;
		$this->isFacebook = $row['isFacebook'] == 1 ? true : false;
		$this->resizeImages = $row['resizeImages'] == 1 ? true : false;
		$this->maxWidth = $row['maxWidth'];
		$this->maxHeight = $row['maxHeight'];
		$this->orientation = $row['orientation'];
		$this->buildFeed();
	}

	function getFeed() {
		$f3 = $this->framework;
		$db = $this->db;
		require_once 'feedFunctions.php';
		parseUrlParams();
		$this->feedUrls = isset($_GET['feedUrl']) ? (is_array($_GET['feedUrl']) ? $_GET['feedUrl'] : array($_GET['feedUrl'])) : null;
		$this->itemsToShow = max(isset($_GET['itemCount']) && is_numeric($_GET['itemCount']) ? intval($_GET['itemCount']) : 10, 1); // Default: 10 items. Minimum: 1 item.
		$this->cacheSeconds = max(isset($_GET['cacheMinutes']) && is_numeric($_GET['cacheMinutes']) ? intval($_GET['cacheMinutes']) : 15, 1) * 60; // Default: 15 minutes. Minumum: 1 minute.
		$this->allowImages = isset($_GET['allowImages']) ? $_GET['allowImages'] : false;
		$this->isFacebook =  isset($_GET['isFacebook']) ? $_GET['isFacebook'] : false;
		$this->resizeImages = isset($_GET['resizeImages']) ? $_GET['resizeImages'] : false;
		$this->maxWidth = isset($_GET['maxWidth']) ? $_GET['maxWidth'] : null;
		$this->maxHeight = isset($_GET['maxHeight']) ? $_GET['maxHeight'] : null;
		$this->orientation = isset($_GET['orientation']) ? $_GET['orientation'] : null;

		for ($i = 0; $i < count($this->feedUrls); $i++) {    
		    $this->feedUrls[$i] = base64_decode($this->feedUrls[$i]);
		}

		$this->buildFeed();
	}

	private function buildFeed() {
		require_once 'feedFunctions.php';
		$f3 = $this->framework;
		$db = $this->db;
		$IMAGE_CACHE_URL = $f3->get('image_cache_url');

		if (empty($this->feedUrls)) {
	    	header('HTTP/1.0 400 Bad Request');
	    
	    	// Set the expires header to a very short period of time in case the CDN decides to cache a bad request
	    	setCacheHeaders(30);
	    	echo 'The feedUrl parameter is required.';
	    	exit;
		}

		

		// Set the expires header so the CDN will cache the response for the appropriate amount of time
		setCacheHeaders($this->cacheSeconds);

		include_once('SimplePie/autoloader.php');

		$feed = new SimplePie();
		$feed->set_feed_url($this->feedUrls); // set the URL we're going to load
		$feed->set_timeout(5); // set a 5 second timeout
		$feed->enable_cache(true); // enable caching
		$feed->set_cache_duration($this->cacheSeconds);
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
		foreach ($feed->get_items(0, $this->itemsToShow) as $item) {
		    // Remove/fix any HTML in the title
		    $title = strip_tags(html_entity_decode($item->get_title()));
		    
		    // Remove/fix any HTML in the description and trim it to 200 chars
		    
		    //$description = strip_tags(html_entity_decode(convertDoc2HTML($item->get_description())));
		    $description = html_entity_decode(convertDoc2HTML($item->get_description()));
		    $imageOut = '';
		    
		    if ($this->allowImages) {
		    
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
		         
         			$simpleImage = new SimpleImage();
	                $simpleImage->load($image);
	                $w = $simpleImage->getWidth();
	                $h = $simpleImage->getHeight();
	                $newOrientation = "";
		                if($w > $h) 
		                	$newOrientation = 'LANDSCAPE';
		                else if ($h > $w) 
		                	$newOrientation = 'PORTRAIT';
		                else 
		                	$newOrientation = 'SQUARE';
	                
	                if (!$this->orientation || $this->orientation == 'BOTH' || $this->orientation == $newOrientation || $newOrientation == 'SQUARE'   ) {

			            $parse = parse_url($image);
			            //$domain = $parse['host'];
			            //$domain = str_ireplace('.', '_', $domain);
			            $pathList = explode("/",$parse['path']);
			            $file = $pathList[count($pathList) -1];
			            $resizeAddition = '';
			            $ratio;

			            //if it's a facebook feed, replace _s(small) with _b(big)
			            
			            if ($this->isFacebook) {
			                $image = preg_replace("/_s/", "_b", $image);
			            }

			            if ($this->resizeImages ) {

			                if ($w > $h) {
			                	$ratio = $h / $w;
			                	$newWidth = $this->maxWidth;
			                	$newHeight = round($newWidth * $ratio);
			                } else {
			                	$ratio = $w / $h;
			                	$newHeight = $this->maxHeight;
			                	$newWidth = round($newHeight * $ratio);
			                }

			            	$resizeAddition = '_' . $newWidth . 'x'. $newHeight . '_' .$newOrientation;
			            }

			            $fileExt = explode(".",$file);

			            
			            $fileName = $f3->get('image_cache_directory') . md5($image) . $resizeAddition . "." . $fileExt[1];
				        
			            $postImages[] = $IMAGE_CACHE_URL . $fileName;
			            
			            
			            if (!file_exists($fileName)) {
			            	if ($this->resizeImages) {
				                $simpleImage->resize($newWidth,$newHeight);
				                $simpleImage->save($fileName);
				            } else {
				                $simpleImage->save($fileName);
			                }

			                $file = "imageCache.log";
			                $fh = fopen($file, 'a') or die("cannot open file");
			                $logData = $image . " :: " . $fileName . "\n";
			                fwrite($fh,$logData);
			                fclose($fh);
			            }
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

	}

	function  afterRoute() {

	}
}