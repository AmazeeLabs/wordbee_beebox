<?php


class TmgmtWordbeeFileformatXLIFF extends TMGMTFileformatXLIFF{
    
    /**
     * Same as TMGMTFileformatXLIFF::addTransUnit() exept it leaves the target element empty
     * 
     * @param type $key
     * @param type $element
     */
    protected function addTransUnit($key, $element, TMGMTJob $job) {

        $key_array = tmgmt_ensure_keys_array($key);

        $this->startElement('trans-unit');
        $this->writeAttribute('id', $key);
        $this->writeAttribute('resname', $key);

        $this->startElement('source');
        $this->writeAttribute('xml:lang', $this->job->getTranslator()->mapToRemoteLanguage($this->job->source_language));

        if ($job->getSetting('xliff_processing')) {
            $this->writeRaw($this->processForExport($element['#text'], $key_array));
        }
        else {
            $this->text($element['#text']);
        }

        $this->endElement();
        $this->startElement('target');
        $this->endElement();
        if (isset($element['#label'])) {
            $this->writeElement('note', $element['#label']);
        }
        $this->endElement();
  }
  
  /**
   * Same as TMGMTFileformatXLIFF::import() exept it takes a XML string as parameter
   * 
   * @param type $xml_string
   * @return type
   */
  public function import($xml_string) {
    $xml = simplexml_load_string($xml_string);

    // Register the xliff namespace, required for xpath.
    $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

    $data = array();
    foreach ($xml->xpath('//xliff:trans-unit') as $unit) {
      $data[(string) $unit['id']]['#text'] = (string) $unit->target;
    }
    return tmgmt_unflatten_data($data);
  }
  
  /**
   * Same as TMGMTFileformatXLIFF::validateImport() exept it takes a XML string as parameter
   * 
   * @param type $xml_string
   * @return boolean
   */
  public function validateImport($xml_string) {
    $xml = simplexml_load_string($xml_string);

    if (!$xml) {
      return FALSE;
    }

    // Register the xliff namespace, required for xpath.
    $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

    // Check if our phase information is there.
    $phase = $xml->xpath("//xliff:phase[@phase-name='extraction']");
    if ($phase) {
      $phase = reset($phase);
    }
    else {
      return FALSE;
    }

    // Check if the job can be loaded.
    if (!isset($phase['job-id']) || (!$job = tmgmt_job_load((string) $phase['job-id']))) {
      return FALSE;
    }

    // Compare source language.
    if (!isset($xml->file['source-language']) || $job->getTranslator()->mapToRemoteLanguage($job->source_language) != $xml->file['source-language']) {
      return FALSE;
    }

    // Compare target language.
    if (!isset($xml->file['target-language']) || $job->getTranslator()->mapToRemoteLanguage($job->target_language) != $xml->file['target-language']) {
      return FALSE;
    }

    // Validation successful.
    return $job;
  }
}
