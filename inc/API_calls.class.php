<?php

/**
 * Class used to make calls to the Wordbee Beebox API
 */
class API_calls {

    private $connectorName;
    private $connectorVersion;
    private $token;
    private $url;
    private $projectKey;
    private $username;
    private $password;

    public function __construct($connectorName,$connectorVersion,$url,$projectKey,$username,$password) {
        $this->connectorName = $connectorName;
        $this->connectorVersion = $connectorVersion;
        $this->url = $url;
        $this->projectKey = $projectKey;
        $this->username = $username;
        $this->password = $password;
    }

    private function checkResponse($curl, $content){
        $http_status_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if($http_status_code != 200 && $http_status_code != 204){
            $details = json_decode($content, true);
            $details['http_status_code'] = $http_status_code;
            throw new Exception('Communication error with the Beebox, see the details : ('. var_export($details, true) .')');
        }
    }

    /**
     * Checks if the token is set
     * 
     * @return boolean True if the token is a string, false otherwise
     */
    public function isConnected() {
        return is_string($this->token);
    }

    /**
     * Tries to connect to the Beebox using the plugin parameters
     * 
     * @return boolean true if the connection is successfull, false otherwise
     * @throws Exception This Exception contains details of an eventual error
     */
    public function connect() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,20);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/connect?connector='.urlencode($this->connectorName).
            '&version=' . urlencode($this->connectorVersion) .
            '&project=' . urlencode($this->projectKey) .
            '&login=' . urlencode($this->username) .
            '&pwd=' . urlencode($this->password)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
        $this->token = $content;

        return $this->isConnected();
    }

    public function disconnect(){

        if(!$this->isConnected())
            return;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/disconnect?token=' . urlencode($this->token)
        );
        curl_exec($curl);
        $this->token = null;
    }

    /**
     * Calls the Beebox API to retrieve the Beebox project source and target
     * languages
     *
     * @return array the language pairs available in the Beebox project like 'source' => 'target1' => 1
     *                                                                                   'target2' => 1
     *                                                                                   'targetX' => 1
     * @throws Exception This Exception contains details of an eventual error
     */
    public function getProjectLanguages() {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/details?token=' . urlencode($this->token)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        $array = json_decode($content, true);
        $target = array();
        foreach ($array['targetLocales'] as $num => $targetLocale) {
            $target[$targetLocale] = 1;
        }
        $language_pairs[$array['sourceLocale']] = $target;

        return $language_pairs;
    }
    
    /**
     * Sends 1 file to the Beebox 'in' folder
     * 
     * @param String $fileContent The content of the file you wish to send
     * @param String $filename Name the file will have in the Beebox
     * @param string $source Source language od the file
     * @throws Exception This Exception contains details of an eventual error
     */
    public function sendFile($fileContent, $filename, $source) {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        $fh = fopen('php://temp/maxmemory:256000', 'w');
        if ($fh) {
            fwrite($fh, $fileContent);
        }

        fseek($fh, 0);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/file?token=' . urlencode($this->token) . 
                '&locale=' . $source . 
                '&folder=&filename=' . urlencode($filename)
        );
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_PUT, 1);
        curl_setopt($curl, CURLOPT_INFILE, $fh);
        curl_setopt($curl, CURLOPT_INFILESIZE, strlen($fileContent));
        $content = curl_exec($curl);

        fclose($fh);

        $this->checkResponse($curl, $content);
    }

    /**
     * Deletes the specified file in the Beebox
     * 
     * @param String $filename Name of the file you wish to delete
     * @param String $source SOurce language of the file
     * @throws Exception This Exception contains details of an eventual error
     */
    public function deleteFile($filename, $source) {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/file?token=' . urlencode($this->token) . 
                '&locale=' . $source . '&filename=' . urlencode($filename) . 
                '&folder='
        );
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
    }

    /**
     * Retrives workprogress of the Beebox for the specified files, if no file specified it will retrieve every file finishing by '-wordpress_connector.xliff'
     * 
     * @param mixed $files Can be an array containning a list of filenames or false if you do no want to filter (false by default)
     * @return array corresponding to the json returned by the Beebox API
     * @throws Exception This Exception contains details of an eventual error
     */
    public function getWorkprogress($files = false) {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $json = '{"token":"' . $this->token . '"';
        if ($files) {
            $json.=',"filter":{"filePaths":[';
            $iter = new CachingIterator(new ArrayIterator($files));
            foreach ($files as $filename) {
                $json.='{"Item1":"","Item2":"' . $filename . '"}';
                if ($iter->hasNext()) {
                    $json.=',';
                }
            }
            $json.=']}';
        } else {
            $json.=',"filter":{"patterns":{"fpath":"-wordpress_connector\\\.xliff$"}}';
        }
        $json.='}';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/workprogress/translatedfiles'
        );

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        return json_decode($content, true);
    }

    /**
     * Downloads the specified file
     * 
     * @param String $filename Name of the file you wish to retrieve
     * @param String $folder Name of the folder where the file is located (usually the target language)
     * @return String The content of the file
     * @throws Exception This Exception contains details of an eventual error
     */
    public function getFile($filename, $folder) {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/file?token=' . urlencode($this->token) . 
                '&locale=&folder=' . urlencode($folder) . 
                '&filename=' . urlencode($filename)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        return $content;
    }

    /**
     * Tells the Beebox to scan its files
     * 
     * @throws Exception This Exception contains details of an eventual error
     */
    public function scanFiles(){
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/operations/scan?token=' . urlencode($this->token)
        );
        curl_setopt($curl, CURLOPT_PUT, 1);
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
    }
    
    /**
     * Asks to the Beebox if a scan is required
     * 
     * @return boolean True if a scan is required, false otherwise
     * @throws Exception This Exception contains details of an eventual error
     */
    public function scanRequired(){
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,5);
        curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/status?token='.urlencode($this->token)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        $array = json_decode($content, true);
        if (is_array($array) && isset($array['scanRequired'])) {
            return (boolean)$array['scanRequired'];
        }
        else{
            throw new Exception('unexpected result from: scan required');
        }
    }
}
