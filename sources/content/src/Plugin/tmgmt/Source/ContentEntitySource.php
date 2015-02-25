<?php

/**
 * @file
 * Contains Drupal\tmgmt_content\EntitySourcePluginController.
 */

namespace Drupal\tmgmt_content\Plugin\tmgmt\Source;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\taxonomy\Plugin\views\field\Language;
use Drupal\tmgmt\SourcePluginBase;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\TMGMTException;
use Drupal\Core\Render\Element;

/**
 * Content entity source plugin controller.
 *
 * @SourcePlugin(
 *   id = "content",
 *   label = @Translation("Content Entity"),
 *   description = @Translation("Source handler for entities."),
 *   ui = "Drupal\tmgmt_content\ContentEntitySourcePluginUi"
 * )
 */
class ContentEntitySource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel(JobItem $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      return $entity->label();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(JobItem $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      return $entity->urlInfo();
    }
  }

  /**
   * Implements TMGMTEntitySourcePluginController::getData().
   *
   * Returns the data from the fields as a structure that can be processed by
   * the Translation Management system.
   */
  public function getData(JobItem $job_item) {
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    if (!$entity) {
      throw new TMGMTException(t('Unable to load entity %type with id %id', array('%type' => $job_item->getItemType(), $job_item->getItemId())));
    }
    $languages = \Drupal::languageManager()->getLanguages();
    $id = $entity->language()->getId();
    if (!isset($languages[$id])) {
      throw new TMGMTException(t('Entity %entity could not be translated because the language %language is not applicable', array('%entity' => $entity->language()->getId(), '%language' => $entity->language()->getName())));
    }
    $field_definitions = $entity->getFieldDefinitions();

    if (!$entity->hasTranslation($job_item->getJob()->getSourceLangcode())) {
      throw new TMGMTException(t('The entity %id with translation %lang does not exist.', array('%id' => $entity->id(), '%lang' => $job_item->getJob()->getSourceLangcode())));
    }

    // @todo Expand this list or find a better solution to exclude fields like
    //   content_translation_source.
    $exclude_field_types = ['language'];
    $translatable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($exclude_field_types) {
      return $field_definition->isTranslatable() && !in_array($field_definition->getType(), $exclude_field_types);
    });

    $data = array();
    $translation = $entity->getTranslation($job_item->getJob()->getSourceLangcode());
    foreach ($translatable_fields as $key => $field_definition) {
      $field = $translation->get($key);
      $data[$key]['#label'] = $field_definition->getLabel();
      foreach ($field as $index => $field_item) {
        $data[$key][$index]['#label'] = t('Delta #@delta', array('@delta' => $index));
        /* @var FieldItemInterface $field_item */
        foreach ($field_item->getProperties() as $property_key => $property) {
          // Ignore computed values.
          $property_definition = $property->getDataDefinition();
          if (($property_definition->isComputed())) {
            continue;
          }
          // Ignore values that are not primitves.
          if (!($property instanceof PrimitiveInterface)) {
            continue;
          }

          $translate = TRUE;
          // Ignore properties with limited allowed values or if they're not strings.
          if ($property instanceof OptionsProviderInterface || !($property instanceof StringInterface)) {
            $translate = FALSE;
          }
          $data[$key][$index][$property_key] = array(
            '#label' => $property_definition->getLabel(),
            '#text' => $property->getValue(),
            '#translate' => $translate,
          );
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTranslation(JobItem $job_item) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    $job = $job_item->getJob();

    // If the translation for this language does not exist yet, initialize it.
    if (!$entity->hasTranslation($job->getTargetLangcode())) {
      $entity->addTranslation($job->getTargetLangcode(), $entity->toArray());
    }

    $translation = $entity->getTranslation($job->getTargetLangcode());
    $data = $job_item->getData();
    foreach ($data as $name => $field_data) {
      foreach (Element::children($field_data) as $delta) {
        $field_item = $field_data[$delta];
        foreach (Element::children($field_item) as $property) {
          $property_data = $field_item[$property];
          if (isset($property_data['#translation']['#text'])) {
            $translation->get($name)->offsetGet($delta)->set($property, $property_data['#translation']['#text']);
          }
        }
      }
    }

    $translation->save();
    $job_item->accepted();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypes() {
    $entity_types = \Drupal::entityManager()->getDefinitions();
    $types = array();
    $content_translation_manager = \Drupal::service('content_translation.manager');
    foreach ($entity_types as $entity_type_name => $entity_type) {
      if ($content_translation_manager->isEnabled($entity_type->id())) {
        $types[$entity_type_name] = $entity_type->getLabel();
      }
    }
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemTypeLabel($type) {
    return \Drupal::entityManager()->getDefinition($type)->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(JobItem $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      $bundles = entity_get_bundles($job_item->getItemType());
      $entity_type = $entity->getEntityType();
      $bundle = $entity->bundle();
      // Display entity type and label if we have one and the bundle isn't
      // the same as the entity type.
      if (isset($bundles[$bundle]) && $bundle != $job_item->getItemType()) {
        return t('@type (@bundle)', array('@type' => $entity_type->getLabel(), '@bundle' => $bundles[$bundle]['label']));
      }
      // Otherwise just display the entity type label.
      return $entity_type->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLangCode(JobItem $job_item) {
    $entity = entity_load($job_item->getItemType(), $job_item->getItemId());
    return $entity->getUntranslated()->language()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingLangCodes(JobItem $job_item) {
    if ($entity = entity_load($job_item->getItemType(), $job_item->getItemId())) {
      return array_keys($entity->getTranslationLanguages());
    }

    return array();
  }


}
