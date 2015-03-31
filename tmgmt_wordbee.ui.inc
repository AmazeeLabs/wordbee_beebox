<?php

/**
 * @file
 * File for TMGMT Wordbee Beebox UI controller.
 * 
 * Url: http://www.beeboxlinks.com/
 */

/**
 * TMGMT Wordbee Beebox UI controller.
 */

class TMGMTWordbeeBeeboxUIController extends TMGMTDefaultTranslatorUIController {

    /**
     * Implements TMGMTTranslatorUIControllerInterface::checkoutInfo().
     */
    public function checkoutInfo(TMGMTJob $job) {
        $form = array();

        if ($job->isActive()) {
            $form['actions']['poll'] = array(
                '#type' => 'submit',
                '#value' => t('Refresh job'),
                '#submit' => array('_tmgmt_wordbee_poll_submit'),
                '#weight' => -10,
            );
        }

        return $form;
    }

    /**
     * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
     */
    public function pluginSettingsForm($form, &$form_state, TMGMTTranslator $translator, $busy = FALSE) {
        $form['url'] = array(
            '#type' => 'textfield',
            '#title' => 'Beebox URL',
            '#default_value' => $translator->getSetting('url'),
            '#description' => 'Please enter your the URL of your Beebox',
        );
        $form['projectKey'] = array(
            '#type' => 'textfield',
            '#title' => 'Project Key',
            '#default_value' => $translator->getSetting('projectKey'),
            '#description' => 'Please enter your Beebox Account Key',
        );
        $form['username'] = array(
            '#type' => 'textfield',
            '#title' => 'Username',
            '#default_value' => $translator->getSetting('username'),
            '#description' => 'Please enter your Beebox Username',
        );
        $form['password'] = array(
            '#type' => 'password',
            '#title' => 'Password',
            '#default_value' => $translator->getSetting('password'),
            '#description' => 'Please enter your Beebox password',
        );
        $form['leave_xliff_target_empty'] = array(
            '#type' => 'checkbox',
            '#title' => 'Leave XLIFF files target element empty',
            '#default_value' => $translator->getSetting('leave_xliff_target_empty'),
            '#description' => 'If you don\'t know what to do with this option, just leave it checked ',
        );
		$form['version'] = array(
            '#type' => 'textfield',
            '#title' => 'Plugin version',
            '#default_value' => file_get_contents(drupal_get_path('module','tmgmt_wordbee').'/version.txt'),
			'#disabled' => true,
        );
        return parent::pluginSettingsForm($form, $form_state, $translator);
    }
}
