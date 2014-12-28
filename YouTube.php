<?php
/**
 * PHP-YouTube-Wrapper
 * @version 0.5
 * @license GPL v3
 */

class YouTubeException extends Exception {}    



/*
* @depricated
*/
class youtube_downloader   {
    
	private $_url;
        private $_metadata = array();
        
        
	public function __construct($url) {
            // object was supplied with an invalid YouTube link.
            if (!$this->isValidURL($url)) {
                throw new YouTubeException("Invalid YouTube video URL specified",1);
            } else {
                $this->_url = $url;
                
                // get video metdata
                $this->get_video_info();
            }
	}
	
        /**
         * Returns a multidimensional array of all available download links with
         * available formats and quality. 
         */
	public function download_links() {
                // Get the html source from the youtube video page
		$html = $this->get_source($this->_url);
         
                return $this->clean_fmt_map($html);
	}
	
        /**
         *  Use the YouTube Data API to get extensive video information
         *  Returns an array of data that includes the title, author, description,
         *  viewcount, and thumbnail of the video.
         *  
         */
	public function get_video_info() {            
            $videoid  = $this->videoID($this->_url);
            
            $_gdata = "https://gdata.youtube.com/feeds/api/videos/$videoid?v=2";
            $source = $this->get_source($_gdata);
            try {
                $oXML = new SimpleXMLElement($source);
            } catch (Exception $e) {
                // Unable to parse XML
                throw new YouTubeException("Unable to parse metadata XML",2);
            }
            
            // try to see if the youtube returned error in the XML feed itself
            if ($oXML->error) {
                throw new YouTubeException("Invalid XML returned from YouTube. Check if the YouTube link is valid");
            }
            // Namespaces
            $namespaces = $oXML->getDocNamespaces();
            // general video information
            $this->_metadata['title'] = $oXML->title;
            $this->_metadata['author'] = $oXML->author->name;
            $this->_metadata['description'] = $oXML->children($namespaces['media'])->group->description;
            $this->_metadata['thumbnail'] =  $oXML->children($namespaces['media'])->group->thumbnail[3]->attributes()->url;
            
            // statistics
            $this->_metadata['viewcount'] = $oXML->children($namespaces['yt'])->statistics->attributes()->viewCount;
            return true;
	}	
	
        /**
         *  Returns true on a valid YouTube url or else return false
         */
        public function isValidURL($url) {
            return preg_match('/https:\/\/www\.youtube\.com\/watch\?v=[A-Za-z0-9-_]{11}/', $url);
        }


	private function get_source($url) {
            $ch = curl_init($url);
            // we're not passing secure data, so no need to obtain YouTube's certificate if $url is https
            // Setting this true will prevent us from getting video metadata since YouTube gData API uses HTTPS
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            
            // we don't want YouTube to know we're using cURL to download videos. We're just a browser ;-)
            curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 6.0) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.63 Safari/535.7");
            $result = curl_exec($ch);
            if (!$result) {
                throw new YouTubeException(curl_error($ch));
            }
            curl_close($ch);
            return $result;
	}
        
        /**
         * Decodes the encoded url, including \u2006
         */
        private function decode_url($url) {
            $url = str_replace('\u0026', '&', $url);
            return urldecode($url);
        }
        
        /**
         * Scrapes the youtube video page source html for valid video links
         * and returns a multidimensional array
         */
        private function clean_fmt_map($source) {
            
            // Attempt to match the JS Object that contains the download links
            preg_match('/(?<="url_encoded_fmt_stream_map": "url=).+(?=itag=\d{1,3}",)/', $source,$match);
            
            // Unable to find the match. Either YT changed their source code or a wrong page was supplied.
            if (!is_array($match) || empty($match)) {
               throw new YouTubeException("Unable to scrape YouTube video source. Make sure YouTube URL is valid",3);
            }
            // split the format links up
            $match = preg_split('/,/', $match[0]);
            
            //remove residual data
             $match =   preg_replace('/url=/', "", $match);
             
             // Iterate through each download link and clean it
             $i = 0;
             foreach ($match as $link) {
                 // decode  url encoding, inculding \u2006
                 $url = $this->decode_url($link);
        
                                  
                 // get the format for each download link
                 // This must be done before "&quality" is stripped below
                 preg_match('/(?<=video\/)[\w-]{1,6}/',$url,$format);
                 $format[0] = preg_replace('/(x-flv)/', 'flv', $format[0]);

                 // Strip everything starting from &quality query parameter, which is not needed
                 $url = preg_replace('/&quality=.+/', '', $url);
                 
                 // get the quality for each download link
                 preg_match('/(?<=itag=)\d{1,2}/', $url,$quality);


                $url = preg_replace('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', '127.0.0.1',$url);
                // 
                 // Format not found
                 if ($format[0] === null ) $format[0] = "Unknown";
                 // attach the data to output array
                 $download_links[$i]['link'] = $url . '&title=' . $this->title();
                 $download_links[$i]['quality'] = $quality[0];
                 $download_links[$i]['format'] = $format[0];
                 $i++;
             }
             return $download_links;
            
        }
        
        // var_dump() truncates data for some reason. This will do.
        private static function dump_exit($data) {
           if (is_array($data)) {
               foreach ($data as $item) {
                   echo $item . '<br/><br/><br/>';
               }
           } else {
               echo $data .'<br/>';  
           }
            exit();
        } 
        
        //============================
        //      Metadata methods
        //============================
        public function title() {
            return $this->_metadata['title'];
        }
        
        public function viewCount() {
            return $this->_metadata['viewcount'];
        }
        
        public function description() {
            return $this->_metadata['description'];
        }
        
        public function thumbnail() {
            return $this->_metadata['thumbnail'];
        }
        
        public function uploader() {
            return $this->_metadata['author'];
        }
        
        public function videoID() {
            //scrape the video ID from the video link
            preg_match('/(?<=\?v=).{11}/', $this->_url,$id);
            if (empty($id)) return false;
            return $id[0];
        }
        
        public function get_metadata_assoc() {
            //return the entire contents of _metadata
            return $this->_metadata;
        }
}   
?>
<html>
    
<head>
    <title>YouTube Wrapper</title>
    <style type="text/css">
    body {
        font-family: monospace,Consolas,"Courier New";
        background-color: #cccccc;
    }
    
    input[type="text"] {
        width:300px;
    }
    </style>
</head>
<body>
    <h1>PHP YouTube Wrapper</h1>
    <br/><br/>

    <form method="GET" action="YouTube.php">
    <input type="text" name="link" value="Enter a YouTube video link..." />
    <input type="submit" value="Get download links" />
    </form>
    <div id="result">
    <?php
    
    if (isset($_GET['link'])) {
       try {
          $yt = new youtube_downloader($_GET['link']);

       } catch (YouTubeException $e) {
           echo "Whoa  there! " . $e->getMessage() . ' on line ' . $e->getLine();
           exit();
           //echo $e->getMessage();
       }
       

       echo '<h1>Video Information</h1>';
       echo '<strong>Title</strong>: ' . $yt->title() . '<br/>';
       echo '<strong>Description</strong>: ' . $yt->description() . '<br/>';
       echo '<strong>View Count</strong>: ' . $yt->viewCount() . '<br/>';
       echo '<strong>Thumbnail</strong>: <img src="' . $yt->thumbnail() . '" /><br/>';
       echo '<strong>Video ID</strong>: ' . $yt->videoID() . '<br/><br/><br/>';
       $dLinks = $yt->download_links();
        foreach ($dLinks as $link) {
            echo 'Quality: '.$link['quality']. ',' . $link['format'] . 
                ' <br/><a href="' . $link['link'] . '"> Download</a><br/><br/>';
        }
        
        
    }
    ?>

    </div>

</body>
</html>
