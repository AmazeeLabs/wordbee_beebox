<?php

/**
 * @file
 * Plugin for communication with Wordbee Beebox API.
 * 
 * Url: http://www.beeboxlinks.com/
 */

require_once(drupal_get_path('module','tmgmt_wordbee').'/inc/API_calls.class.php');
require_once(drupal_get_path('module','tmgmt_wordbee').'/inc/TMGMTCustomFileformatXLIFF.class.php');

/**
 * Wordbee Beebox translator plugin container.
 */
class TMGMTWordbeeBeeboxPluginController extends TMGMTDefaultTranslatorPluginController {

    /**
     * API_Calls instance
     *
     * @var API_Calls
     */
    protected $apiCalls;

    /**
     * Overrides TMGMTDefaultTranslatorPluginController::isAvailable().
     */
    public function isAvailable(TMGMTTranslator $translator) {
        $this->prepareApiCalls($translator);
        try{
            $connected = $this->apiCalls->connect();
        } catch (Exception $e) {
            $connected = FALSE;
        }
        $this->apiCalls->disconnect();
        return $connected;
    }

    /**
     * Overrides TMGMTDefaultTranslatorPluginController::canTranslate().
     */
    public function canTranslate(TMGMTTranslator $translator, TMGMTJob $job){
        if (!parent::canTranslate($translator, $job)) {
            return FALSE;
        }
        $this->prepareApiCalls($translator);
        try{
            $languages = $this->apiCalls->getProjectLanguages();
            $this->apiCalls->disconnect();
            //var_dump($languages);
        } catch (Exception $e) {
            return FALSE;
        }
        if(!isset($languages[$translator->mapToRemoteLanguage(($job->source_language))])){
            return false;
        }
        elseif(!array_key_exists($translator->mapToRemoteLanguage($job->target_language), $languages[$translator->mapToRemoteLanguage(($job->source_language))])){
            return false;
        }
        return TRUE;
    }
    
    /**
     * Overrides TMGMTDefaultTranslatorPluginController::requestTranslation).
     */
    public function requestTranslation(TMGMTJob $job) {
        if($job->getTranslator()->getSetting('leave_xliff_target_empty')){
            $fileformat = new TMGMTCustomFileformatXLIFF();
        }
        else{
            $fileformat = new TMGMTFileformatXLIFF();
        }

        $filename = $job->tjid . '-drupal_connector.xliff';
        $xliff_file = $fileformat->export($job);

        $translator = $job->getTranslator();

        $this->prepareApiCalls($translator);

        try {
            $this->apiCalls->sendFile($xliff_file, $filename, $translator->mapToRemoteLanguage($job->source_language));
            $this->apiCalls->sendFile('{"locales":["'.$translator->mapToRemoteLanguage($job->target_language).'"]}', $filename.'.beebox', $translator->mapToRemoteLanguage($job->source_language));
            if($this->apiCalls->scanRequired()) {
                $this->apiCalls->scanFiles();
            }
        } catch (Exception $e) {
            watchdog_exception('tmgmt_wordbee', $e);
            $job->rejected('Job has been rejected with following error: @error',
              array('@error' => $e->getMessage()), 'error');
        }
        $this->apiCalls->disconnect();
        $job->submitted('Job has been submitted to Beebox.');
    }
    
    /**
     * Overrides TMGMTDefaultTranslatorPluginController::cancelTranslation().
     *//*
    public function cancelTranslation(TMGMTJob $job){
        $filename = $job->tjid.'-drupal_connector.xliff';
        $this->prepareApiCalls($job->getTranslator());
        try{
            $workprogress = $this->apiCalls->getWorkprogress(array($filename));
            if(count($workprogress)>0){
                $this->apiCalls->deleteFile($filename, $job->source_language);
                $this->apiCalls->deleteFile($filename.'.beebox', $job->source_language);
                $job->cancelled('Job removed');
                return true;
            }
        } catch(Exception $e){
            watchdog_exception('tmgmt_wordbee', $e);
            $job->rejected('Job has not been cancelled with following error: @error',
              array('@error' => $e->getMessage()), 'error');
        }
        
        return false;
    }*/
    
    /**
     * Checks if a job is complete on the Beebox, if it's the case, the job is downloaded and set to 'finished' in tmgmt
     * 
     * @param TMGMTJob $job
     */
    public function updateCompletedJob(TMGMTJob $job){
        $this->prepareApiCalls($job->getTranslator());
        $workprogress = $this->apiCalls->getWorkprogress(array($job->tjid.'-drupal_connector.xliff'));
        $fileformat = new TMGMTCustomFileformatXLIFF();
        foreach ($workprogress['files'] as $work) {
            if ($work['uptodate']) {
                $file = $this->apiCalls->getFile($work['file'], $work['locale']);
                if($fileformat->validateImport($file)){
                    $job->addTranslatedData($fileformat->import($file));
                    $job->finished('Translation done');
                }
            }
        }
        $this->apiCalls->disconnect();
    }

    /**
     * Instanciate 'API_calls' if needed, this method should be called before using $this->apiCalls
     * 
     * @param TMGMTTranslator $translator
     */
    private function prepareApiCalls(TMGMTTranslator $translator) {
        if (!isset($this->apiCalls)){
            $version = file_get_contents(drupal_get_path('module','tmgmt_wordbee').'/version.txt');
            $this->apiCalls = new API_calls('WB-Drupal',$version,$translator->getSetting('url'), $translator->getSetting('projectKey'), $translator->getSetting('username'), $translator->getSetting('password'));
        }
    }
}
