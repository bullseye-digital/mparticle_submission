<?php

namespace Drupal\mparticle_submission;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

/**
 * Class MparticleSubmissionService.
 */
class MparticleSubmissionService implements MparticleSubmissionInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new MparticleService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get Mparticle global config.
   */
  public function mparticleSubmissionGetConfigs() {
    $global_configs = $this->configFactory->get('mparticle_submission.mparticlesubmissionconfig');
    $global_api_endpoint = $global_configs->get('api_endpoint');
    $global_group_rest_api_key = $global_configs->get('app_group_rest_api_key');

    return [
      'api_url' => $global_api_endpoint,
      'app_group_key' => $global_group_rest_api_key,
    ];
  }

  /**
   * Submit data to Mparticle using global config.
   *
   * @param array $data
   *   Array submission data.
   *
   * @return bool
   *   Boolean Mparticle send status.
   */
  public function mparticleSubmissionSendMparticle(array $data) {
    $mparticle_configs = $this->mparticleSubmissionGetConfigs();
    $valid_mparticle_config = (isset($mparticle_configs['api_endpoint']) && !empty($mparticle_configs['api_endpoint']) && isset($mparticle_configs['api_key']) && !empty($mparticle_configs['api_key']) && isset($mparticle_configs['api_secret']) && !empty($mparticle_configs['api_secret'])) ? TRUE : FALSE;

    // Send using global config. to global config.
    if ($valid_mparticle_config) {
      return $this->mparticleSubmissionSend($data, $mparticle_configs);
    }
    else {
      $this->loggerFactory->get('mparticle_submission')->error('Global Mparticle config is not configured.');
    }
  }

  /**
   * Submit data to Mparticle using webform config.
   *
   * @param array $data
   *   Associate array between mparticle attributes and data.
   * @param array $configs
   *   Array webform configs.
   *
   * @return bool
   *   Boolean Mparticle send success.
   */
  public function mparticleSubmissionWebformSendMparticle(array $data, array $configs) {
    $mparticle_configs = $this->mparticleSubmissionGetConfigs();
    $valid_webform_config = (isset($configs['api_endpoint']) && !empty($configs['api_endpoint']) && isset($configs['api_key']) && !empty($configs['api_key']) && isset($configs['api_secret']) && !empty($configs['api_secret'])) ? TRUE : FALSE;
    $valid_mparticle_config = (isset($mparticle_configs['api_endpoint']) && !empty($mparticle_configs['api_endpoint']) && isset($mparticle_configs['api_key']) && !empty($mparticle_configs['api_key']) && isset($mparticle_configs['api_secret']) && !empty($mparticle_configs['api_secret'])) ? TRUE : FALSE;

    // Send submission using webform config if set.
    if ($valid_webform_config) {
      return $this->mparticleSubmissionSend($data, $configs);
    }
    else {
      $this->loggerFactory->get('mparticle_submission')->error('Global Mparticlee config is not configured.');
    }

    // Fallback to global config.
    if ($valid_mparticle_config) {
      return $this->mparticleSubmissionSend($data, $mparticle_configs);
    }
    else {
      $this->loggerFactory->get('mparticle_submission')->error('Webform Mparticle config is not configured.');
    }

    return FALSE;
  }

  /**
   * Send request to Mparticle using http client.,.
   *
   * @param array $data
   *   Array of data.
   * @param array $configs
   *   Array if configs.
   *
   * @return boolan
   *   Boolean Mparticle send status.
   */
  public function mparticleSubmissionSend(array $data, array $configs) {
    // Setup headers.
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic ' . $this->mparticleGenerateAuthKey($configs),
    ];

    // Setup JSON string Mparticle payload.
    $mparticlepayload = '{
      "events" :
      [
          {
              "data" : {
                "event_name" : "' . $configs['event_name'] . '",
              },
              "event_type" : "custom_event"
          }
      ],
      "device_info" : {},
      "user_attributes" : ' . Json::encode($data) . ',
      "deleted_user_attributes" : [],
      "user_identities" : {
        "email" : "' . $data['email'] . '"
      },
      "application_info" : {},
      "schema_version": 2,
      "environment" : "' . $configs['environment'] . '",
      "context": {},
    }';

    try {
      $url = Url::fromUri($configs['api_endpoint'], [])->toString();
      $request = $this->httpClient->post($url, [
        'verify' => FALSE,
        'headers' => $headers,
        'body' => $mparticlepayload,
      ]);

      $status_code = $request->getStatusCode();
      if ($status_code >= 200 || $status_code < 300) {
        $this->loggerFactory->get('mparticle_submission')->notice('Response: @response, Data: @data', ['@response' => $status_code, '@data' => $mparticlepayload]);
        return TRUE;
      }
      else {
        $this->loggerFactory->get('mparticle_submission')->error('Error code: @code', ['@code' => $status_code]);
      }
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('mparticle_submission')->error('Error: @error, Data: @data', ['@error' => $e->getMessage(), '@data' => $mparticlepayload]);
    }

    return FALSE;
  }

  /**
   * Generate auth key for authentication.
   *
   * @param array $configs
   *   Mparticle config.
   *
   * @return string
   *   String of auth key.
   */
  public function mparticleGenerateAuthKey(array $configs) {
    $auth_key = '';

    if (isset($configs['api_key']) && !empty($configs['api_key']) && !empty($configs['api_secret']) && !empty($configs['api_secret'])) {
      $key_concat = $configs['api_key'] . ':' . $configs['api_secret'];
      $auth_key = base64_encode($key_concat);
    }

    return $auth_key;
  }

}
