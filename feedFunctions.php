<?php
function parseUrlParams() {
	
    $query = trim($_SERVER['REQUEST_URI'], '/');
    $query = explode('/', $query, 2);
    $query = $query[1];
    $query = trim($query, ';');
    $params = explode(';', $query);
    
    
    foreach ($params as $param) {
        list($key, $value) = explode('=', $param, 2);
        $values = explode(',', $value);
        if (count($values) == 1)
            $_GET[$key] = $value;
        else
            $_GET[$key] = $values;
    }
}

function setCacheHeaders($seconds) {
    header('Cache-Control: max-age='.$seconds);
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + $seconds));
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
}

function convertDoc2HTML($txt) { 
        $quotes = array("&#8220;","&#8221;");
        $txt = str_replace($quotes,"\"",$txt);
        return $txt; 
}

?>
