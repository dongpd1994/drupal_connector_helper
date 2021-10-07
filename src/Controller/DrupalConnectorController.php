<?php

/**
 * @file
 * Contains \Drupal\drupal_connector_helper\Controller\DrupalConnectorController.
 */

namespace Drupal\drupal_connector_helper\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\NodeType;

class DrupalConnectorController extends ControllerBase
{
  /**
   * Callback for `api/get-all-content-type` API method.
   */
  public function get_all_content_type(Request $request)
  {
    $data = [];
    $all_content_types = NodeType::loadMultiple();
    foreach ($all_content_types as $machine_name => $content_type) {
      $label = $content_type->label();
      $data[] = (object) ['type' => $machine_name, 'label' => $label];
    }

    return new JsonResponse($data);
  }

  /**
   * Callback for `api/verify` API method.
   */
  public function verify(Request $request)
  {
    $response['data'] = "";
    return new JsonResponse($response);
  }

  /**
   * Callback for `api/get-media-fields/{contentType}` API method.
   */
  public function getMediaFields(string $contentType) {
    $media_fields = array_filter(
      \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $contentType),
      function ($fieldDefinition) {
        return $fieldDefinition instanceof \Drupal\field\FieldConfigInterface && in_array($fieldDefinition->getType(), ['image', 'file', 'entity_reference']);
      }
    );
    $response['data'] = implode(",",array_keys($media_fields));
    return new JsonResponse($response);
  }
}