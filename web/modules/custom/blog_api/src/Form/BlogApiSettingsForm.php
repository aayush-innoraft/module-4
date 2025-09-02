<?php

namespace Drupal\blog_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Blog API filters.
 */
class BlogApiSettingsForm extends ConfigFormBase {

  /**
   * Configurable settings.
   */
  protected function getEditableConfigNames(): array {
    return ['blog_api.settings'];
  }

  /**
   * Gets the form ID.
   */
  public function getFormId(): string {
    return 'blog_api_settings_form';
  }

  /**
   * Building config form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('blog_api.settings');

    $form['from_date'] = [
      '#type' => 'date',
      '#title' => $this->t('From Date'),
      '#default_value' => $config->get('from_date') ?: '',
    ];

    $form['to_date'] = [
      '#type' => 'date',
      '#title' => $this->t('To Date'),
      '#default_value' => $config->get('to_date') ?: '',
    ];

    $form['author_uids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific Author UIDs '),
      '#default_value' => implode(',', (array) $config->get('author_uids') ?: []),
      '#description' => $this->t('Enter user IDs separated by commas.'),
    ];

    $form['tag_tids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific Tag TIDs '),
      '#default_value' => implode(',', (array) $config->get('tag_tids') ?: []),
      '#description' => $this->t('Enter taxonomy term IDs separated by commas.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Function to submit form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('blog_api.settings')
      ->set('from_date', $form_state->getValue('from_date'))
      ->set('to_date', $form_state->getValue('to_date'))
      ->set('author_uids', array_filter(array_map('intval', explode(',', $form_state->getValue('author_uids')))))
      ->set('tag_tids', array_filter(array_map('intval', explode(',', $form_state->getValue('tag_tids')))))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
