<?php

namespace Drupal\mparticle_submission;

/**
 * Interface MparticleSubmissionInterface.
 */
interface MparticleSubmissionInterface {

  /**
   * Submit data to Mparticle using global config.
   *
   * @param array $data
   *   Array submission data.
   *
   * @return bool
   *   Boolean Mparticle send status.
   */
  public function mparticleSubmissionSendMparticle(array $data);

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
  public function mparticleSubmissionWebformSendMparticle(array $data, array $configs);

}
