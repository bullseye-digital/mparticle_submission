<?php

namespace Drupal\mparticle_submission\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mparticle_submission\MparticleSubmissionInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Serialization\Json;

/**
 * Webform example handler.
 *
 * @WebformHandler(
 *   id = "webform_mparticle_submission",
 *   label = @Translation("Mparticle Submission"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Submit webform data to Mparticle."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 * )
 */
class MparticleSubmissionHandler extends WebformHandlerBase {

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;


  /**
   * Mparticle submission service.
   *
   * @var \Drupal\mparticle_submission\MparticleSubmissionInterface
   */
  protected $mparticleService;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, WebformTokenManagerInterface $token_manager, MparticleSubmissionInterface $mparticleservice) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->tokenManager = $token_manager;
    $this->mparticleService = $mparticleservice;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('webform.token_manager'),
      $container->get('mparticle_submission.mparticle_submission_service'),
      $container->get('logger.factory')
    );
  }

  /**
   * Default configuration.
   */
  public function defaultConfiguration() {
    return [
      'api_endpoint' => '',
      'api_key' => '',
      'api_secret' => '',
      'environment' => '',
      'event_name' => '',
      'custom_data' => '',
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Additional.
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional settings'),
    ];

    $form['additional']['api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mparticle API Endpoint'),
      '#description' => $this->t('Enter Mparticle track endpoint url.'),
      '#default_value' => $this->configuration['api_endpoint'],
    ];

    $form['additional']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mparticle API Key'),
      '#description' => $this->t('Enter API Key'),
      '#default_value' => $this->configuration['api_key'],
    ];

    $form['additional']['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mparticle API Secret'),
      '#description' => $this->t('Enter API Secret'),
      '#default_value' => $this->configuration['api_secret'],
    ];

    $form['additional']['environment'] = [
      '#type' => 'select',
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#title' => $this->t('Environment'),
      '#description' => $this->t('Select environment'),
      '#default_value' => $this->configuration['environment'],
    ];

    $form['additional']['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event name'),
      '#description' => $this->t('Enter event name'),
      '#default_value' => $this->configuration['event_name'],
    ];

    $form['additional']['custom_data'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Custom data'),
      '#description' => $this->t('Enter custom data that will be included in all remote post requests.<br/>'),
      '#default_value' => $this->configuration['custom_data'],
      '#suffix' => $this->t('<div role="contentinfo" class="messages messages--info">
        field_mparticle_attribute: field_webform_key
      </div>'),
    ];

    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, posted submissions will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
    // Cast debug.
    $this->configuration['debug'] = (bool) $this->configuration['debug'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];
    return [
      '#settings' => $settings,
    ] + parent::getSummary();

  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $custom_data = (!empty($this->configuration['custom_data'])) ? $this->configuration['custom_data'] : '';
    if (!empty($custom_data)) {
      $data_replace = $this->replaceTokens($custom_data, $webform_submission);
      $data_replace_decode = Yaml::decode($data_replace);
      $data = $webform_submission->getData();
      if ($this->configuration['debug']) {
        $this->loggerFactory->get('mparticle_submission')->info('Config data: @config_data, Data: @data', [
          '@config_data' => Json::encode($data_replace_decode),
          '@data' => Json::encode($data),
        ]);
      }

      // Check if offer is accepted.
      $accept_offer = (isset($data['offer']) && !empty($data['offer']) && $data['offer'] == '1') ? TRUE : FALSE;

      if ($accept_offer) {
        // Send data to Mparticle.
        if (!empty($data_replace_decode)) {
          $mparticlesubmitted = $this->mparticleService->mparticleSubmissionWebformSendMparticle($data_replace_decode, $this->configuration);
          if ($mparticlesubmitted) {
            $this->loggerFactory->get('mparticle_submission')->info('Mparticle submission success for data: @data', [
              '@data' => Json::encode($data_replace_decode),
            ]);
          }
        }
      }
      else {
        $this->loggerFactory->get('mparticle_submission')->warning('Client not accepting offer. No data sent to Mparticle.');
      }
    }
    else {
      $this->loggerFactory->get('mparticle_submission')->error('Custom data is not configured.');
    }

  }

}
