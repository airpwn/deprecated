<?php
Class Movie{
    protected $_details = array();

    public function  __construct($filename, $cachedir = false, $photoDir = false) {
        $imdbid = $this->_search(basename($filename), true);
        $this->_fetchDetails($imdbid);
    }

    public static function movienameFromFile($file, $useBlackLists = null, $options = array()) {
		$orig_str = $file;
        $str      = $file;

        if (!isset($options['appendYear'])) $options['appendYear'] = true;
        if (!isset($options['appendExtension'])) $options['appendExtension'] = false;

        if ($useBlackLists === null) {
            $useBlackLists = array('*' => true);
        }
        // Crazy regex to avoid matching 1080 or 1920
        $patternYear = '19[4-9]{1}[0-9]{1}|20[0-9]{2}';

        $str = pathinfo($file, PATHINFO_FILENAME);
        $ext = pathinfo($file, PATHINFO_EXTENSION);

		$str = str_replace("_" ," ", $str);
		$str = str_replace("." ," ", $str);

        $blackLists = array();

		$blackLists['Authors'] = array(
            'jamgood',
            'stv 2005 dvdrip xvid internal',
            'LiMiTED',
            'teste divxovore com',
            'done',
            'don',
            'dimension',
            'progress',
            'Asteroids',
            'esir',
            'dc',
            'Os Iluminados',
            'LinkoManija Net',
            'deity',
            'TEAM APEX',
            'bald',
            'KLAXXON',
            'YMG',
            'Dvl',
            'ill',
            'hv',
            'malibu',
            'anarchy',
            'hnm',
            'sinners',
            'DiSSOLVE',
            'hls',
            'Mp3 Beef Stew',
            'tmg',
            'crf',
            'iwok',
            'PerfectionHD',
            'JUST4FUN TEAM',
        );
        $blackLists['Subs'] = array(
            'custom',
            'nlsubbed',
            'Subbed',
            'multisubs',
            'nl',
            'es',
            'eng',
            'dut',
            'ger',
            'fr',
        );

        $blackLists['Source'] = array(
            'dvdrip',
            'rerip',
            'HDDVDRip',
            'HDDVD',
            'xscr',
            'hdtv',
            'dvdscr',
            'tc',
            'ts',
            'kvcd',
            'svcd',
            'vcd',
            'bluray',
            'repack',
        );

        $blackLists['Release'] = array(
            $patternYear,
        );

        $blackLists['Encoding'] = array(
            'divx',
            'xvid',
            'X264',
            'ac3',
            'dd5\ 1',
            'ttf',
            'dts',
            '192k',
            '196k',
            '128k',
            '320k',
        );

        $blackLists['Resolution'] = array(
            '1080p',
            '1080i',
            '720p',
            '720i',
            '1920',
            '1080',
            '720',
        );

        // Remove things enclosed
        while (strBetween($str, "(", ")", false, true)){
            $str = str_replace(strBetween($str,"(",")",true,true) ,"",$str);
        }
        while (strBetween($str,"[","]",false,true)){
            $str = str_replace(strBetween($str,"[","]",true,true) ,"",$str);
        }

		// Remove accents
		$str = htmlentities($str);
		$str = preg_replace("/&([a-z])[a-z]+;/i","$1",$str);

        // Remove words from several blacklists
        foreach ($blackLists as $blackListName=>$blackList) {
            if (!empty($useBlackLists[$blackListName]) || !empty($useBlackLists['*'])) {
                foreach ($blackList as $blackWord) {
                    $str = preg_replace('/(-'.$blackWord.')([\W]|$)/i', '$2', $str);
                    $str = preg_replace('/('.$blackWord.'-)([\W]|$)/i', '$2', $str);
                    $str = preg_replace('/(^|[\W])([\-]*'.$blackWord.')([\W]|$)/i', '$1$3', $str);
                }
            }
        }

		// Ultra trim
        $str = preg_replace('/\s[\s]+/', ' ', $str);
		$str = trim($str);

		// Remove CD number
		$parts = explode(" ",$str);
		$examine = strtolower($parts[count($parts)-1]);
		if (substr($examine, 0, 2) == "cd") {
			$x = array_pop($parts);
			$str = implode(" ",$parts);
		}

		// Remove occasional trailing/heading '-'
		if (substr($str, strlen($str)-1,1) == "-") {
			$str = trim(substr($str,0,strlen($str)-1));
		}
		if (substr($str, 0, 1) == "-") {
			$str = trim(substr($str,1,strlen($str)));
		}

        // Append Year
        if ($options['appendYear']) {
            $pattern =  '/(^|[\W])(' . $patternYear . ')([\W]|$)/';

            if (!preg_match($pattern, $str)) {
                if (preg_match($pattern, $orig_str, $matches)) {
                    $year = $matches[2];
                    $str .= ' ('.$year.')';
                }
            }
        }

        // Append Extension
        if ($options['appendExtension']) {
            $str .= '.'.strtolower($ext);;

        }

		$str = ucwords($str);

		return $str;
    }

    protected function _search($name, $cleanUp = true) {
        if ($cleanUp) {
            $name = self::movienameFromFile($name);
            echo $name."\n";
        }

        $results = array();
        $imdbsearch = new imdbsearch();     // create an instance of the search class
        $imdbsearch->maxresults = 1;

        $imdbsearch->setsearchname($name);  // tell the class what to search for (case insensitive)
        $results = $imdbsearch->results();  // submit the search

        $result = array_shift($results);
        return $result->imdbid();
    }

    protected function _fetchDetails($imdbid) {
        $movie = new imdb($imdbid);
        $keys = array(
            'genres',
            'photo',
            'plot',
            'runtime',
            'tagline',
            'title',
            'votes',
            'year',
            'cast',
            'rating',
            'goofs',
            'comment',
        );

        $this->_details = array();

        foreach ($keys as $key) {
            $this->_details[$key] = call_user_func(array($movie, $key));
        }

        if ($this->_details['year'] == -1)  {
            $this->_details = false;
            return false;
        }

        return true;
    }

    public function getDetails() {
        return $this->_details;
    }
}
?>