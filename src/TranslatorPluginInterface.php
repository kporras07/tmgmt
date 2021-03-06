<?php

/**
 * @file
 * Contains \Drupal\tmgmt\TranslatorPluginInterface.
 */

namespace Drupal\tmgmt;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for service plugin controllers.
 *
 * @ingroup tmgmt_translator
 */
interface TranslatorPluginInterface extends PluginInspectionInterface {

  /**
   * Checks whether a translator is available.
   *
   * @param TranslatorInterface $translator
   *   The translator entity.
   *
   * @return bool
   *   TRUE if the translator plugin is available, FALSE otherwise.
   */
  public function isAvailable(TranslatorInterface $translator);

  /**
   * Return a reason why the translator is not available.
   *
   * @param TranslatorInterface $translator
   *   The translator entity.
   *
   * Might be called when isAvailable() returns FALSE to get a reason that
   * can be displayed to the user.
   *
   * @todo Remove this once http://drupal.org/node/1420364 is done.
   */
  public function getNotAvailableReason(TranslatorInterface $translator);

  /**
   * Check whether this service can handle a particular translation job.
   *
   * @param TranslatorInterface $translator
   *   The Translator entity that should handle the translation.
   * @param \Drupal\tmgmt\JobInterface $job
   *   The Job entity that should be translated.
   *
   * @return bool
   *   TRUE if the job can be processed and translated, FALSE otherwise.
   */
  public function canTranslate(TranslatorInterface $translator, JobInterface $job);

  /**
   * Return a reason why the translator is not able to translate this job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job entity.
   *
   * Might be called when canTranslate() returns FALSE to get a reason that
   * can be displayed to the user.
   *
   * @todo Remove this once http://drupal.org/node/1420364 is done.
   */
  public function getNotCanTranslateReason(JobInterface $job);

  /**
   * Specifies default mappings for local to remote language codes.
   *
   * This method can be used in case we know in advance what language codes are
   * used by the remote translator and to which local language codes they
   * correspond.
   *
   * @return array
   *   An array of local => remote language codes.
   *
   * @ingroup tmgmt_remote_languages_mapping
   */
  public function getDefaultRemoteLanguagesMappings();

  /**
   * Gets all supported languages of the translator.
   *
   * This list of all language codes used by the remote translator is then used
   * for example in the translator settings form to select which remote language
   * code correspond to which local language code.
   *
   * @param TranslatorInterface $translator
   *   Translator entity for which to get supported languages.
   *
   * @return array
   *   An array of language codes which are provided by the translator
   *   (remote language codes).
   *
   * @ingroup tmgmt_remote_languages_mapping
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator);

  /**
   * Returns all available target languages that are supported by this service
   * when given a source language.
   *
   * @param TranslatorInterface $translator
   *   The translator entity.
   * @param $source_language
   *   The source language.
   *
   * @return array
   *   An array of remote languages in ISO format.
   *
   * @ingroup tmgmt_remote_languages_mapping
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language);

  /**
   * Returns supported language pairs.
   *
   * This info may be used by other plugins to find out what language pairs
   * can handle the translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   *
   * @return array
   *   List of language pairs where a pair is an associative array of
   *   source_language and target_language.
   *   Example:
   *   array(
   *     array('source_language' => 'en-US', 'target_language' => 'de-DE'),
   *     array('source_language' => 'en-US', 'target_language' => 'de-CH'),
   *   )
   *
   * @ingroup tmgmt_remote_languages_mapping
   */
  public function getSupportedLanguagePairs(TranslatorInterface $translator);


  /**
   * @abstract
   *
   * Submits the translation request and sends it to the translation provider.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job that should be submitted.
   *
   * @ingroup tmgmt_remote_languages_mapping
   */
  public function requestTranslation(JobInterface $job);

  /**
   * Aborts a translation job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job that should have its translation aborted.
   *
   * @return bool
   *   TRUE if the job could be aborted, FALSE otherwise.
   */
  public function abortTranslation(JobInterface $job);

  /**
   * Defines default settings.
   *
   * @return array
   *   An array of default settings.
   */
  public function defaultSettings();

  /**
   * Returns if the translator has any settings for the passed job.
   */
  public function hasCheckoutSettings(JobInterface $job);

  /**
   * Accept a single data item.
   *
   * @todo Using job item breaks the current convention which uses jobs.
   *
   * @param $job_item
   *   The Job item the accepted data item belongs to.
   * @param $key
   *   The key of the accepted data item.
   *   The key is an array containing the keys of a nested array hierarchy path.
   *
   * @return
   *   TRUE if the approving was succesfull, FALSE otherwise.
   *   In case of an error, it is the responsibility of the translator to
   *   provide informations about the failure by adding a message to the job
   *   item.
   */
  public function acceptedDataItem(JobItemInterface $job_item, array $key);

  /**
   * Returns the escaped #text of a data item.
   *
   * @param array $data_item
   *   A data item with a #text and optional #escape definitions.
   *
   * @return string
   *   The text of the data item with translator-specific escape patterns
   *   applied.
   */
  public function escapeText(array $data_item);

  /**
   * Removes escape patterns from an escaped text.
   *
   * @param string $text
   *   The text from which escape patterns should be removed.
   *
   * @return string
   *   The unescaped text.
   */
  public function unescapeText($text);

}
