<?php

namespace Drupal\mparticle_submission\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MparticleSubmissionConfigForm.
 */
class MparticleSubmissionConfigForm extends ConfigFormBase {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mparticle_submission.mparticlesubmissionconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mparticle_submission_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mparticle_submission.mparticlesubmissionconfig');
    $form['mparticleconfig'] = [
      '#type' => 'details',
      '#title' => $this->t('Mparticle Configs'),
      '#open' => TRUE,
    ];

    $form['mparticleconfig']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mparticle API Key'),
      '#description' => $this->t('Enter APi Key'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('api_key'),
    ];

    $form['mparticleconfig']['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mparticle API Secret'),
      '#description' => $this->t('Enter API Secret'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('api_secret'),
    ];

    $form['mparticleconfig']['environment'] = [
      '#type' => 'select',
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#title' => $this->t('Mparticle environment'),
      '#description' => $this->t('Enter environment'),
      '#default_value' => $config->get('environment'),
    ];

    $form['mparticleconfig']['api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Endpoint'),
      '#description' => $this->t('Enter API Endpoint'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('api_endpoint'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mparticle_submission.mparticlesubmissionconfig')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_secret', $form_state->getValue('api_secret'))
      ->set('api_endpoint', $form_state->getValue('api_endpoint'))
      ->set('environment', $form_state->getValue('environment'))
      ->save();
  }

}
