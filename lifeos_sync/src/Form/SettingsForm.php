<?php

namespace Drupal\lifeos_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the administrator-selected public feed.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lifeos_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['lifeos_sync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('lifeos_sync.settings');
    $form['feed_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Public JSON feed URL'),
      '#default_value' => $config->get('feed_url'),
      '#maxlength' => 2048,
      '#description' => $this->t('Use an HTTP(S) URL with no credentials. The server fetches this address. Only use a feed intended for public sharing. Leave empty to disable fetching. Redirects are not followed.'),
    ];
    $form['sync_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum sync interval'),
      '#options' => [
        300 => $this->t('5 minutes'),
        900 => $this->t('15 minutes'),
        3600 => $this->t('1 hour'),
        21600 => $this->t('6 hours'),
        86400 => $this->t('1 day'),
      ],
      '#default_value' => $config->get('sync_interval'),
      '#description' => $this->t('Drupal cron must run separately. Existing queued records finish before another snapshot is fetched.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $url = trim($form_state->getValue('feed_url'));
    $form_state->setValue('feed_url', $url);
    if ($url !== '' && (!in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], TRUE) || parse_url($url, PHP_URL_USER) !== NULL || parse_url($url, PHP_URL_PASS) !== NULL)) {
      $form_state->setErrorByName('feed_url', $this->t('Use an HTTP(S) URL without credentials.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('lifeos_sync.settings')
      ->set('feed_url', $form_state->getValue('feed_url'))
      ->set('sync_interval', (int) $form_state->getValue('sync_interval'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
