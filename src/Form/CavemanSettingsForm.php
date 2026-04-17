<?php

namespace Drupal\caveman\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Caveman filter settings.
 */
class CavemanSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['caveman.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'caveman_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('caveman.settings');

    $form['caveman_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Caveman filter'),
      '#default_value' => $config->get('caveman_override'),
      '#description' => $this->t('Enable caveman-speak site-wide. When off, only activates on April 1st.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('caveman.settings')
      ->set('caveman_override', (bool) $form_state->getValue('caveman_override'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
