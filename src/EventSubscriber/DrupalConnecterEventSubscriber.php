<?php

namespace Drupal\drupal_connector_helper\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;

/**
 * Class DrupalConnecterEventSubscriber.
 *
 * @package Drupal\drupal_connector_helper
 */
class DrupalConnecterEventSubscriber implements EventSubscriberInterface
{

  /**
   * @var path.current service
   */
  private $currentPath;
  /**
   * @var jwt.authentication.jwt service
   */
  private $jwtAuth;

  /**
   * Constructor with dependency injection
   */
  public function __construct($currentPath, $JwtAuth)
  {
    $this->currentPath = $currentPath;
    $this->jwtAuth = $JwtAuth;
  }

  /**
   * Add JWT access token to user login API response
   */
  public function onHttpLoginResponse(FilterResponseEvent $event)
  {
    $path = $this->currentPath->getPath();
    // Halt if not user login request
    if ($path !== '/user/login' && $path !== '/api/verify' && !str_starts_with($path, '/jsonapi/node/')) {
      return;
    }
    // Get response
    $response = $event->getResponse();
    // Ensure not error response
    if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 303) {
      return;
    }
    // Get request
    $request = $event->getRequest();
    // Just handle JSON format for now
    if (($path === '/user/login' || $path === '/api/verify') && $request->query->get('_format') !== 'json') {
      return;
    }
    // ADD HTML
    if (str_starts_with($this->currentPath->getPath(), '/jsonapi/node/')) {
      $decoded = Json::decode($response->getContent());
      $contentType = NULL;
      $responeData = json_decode($response->getContent());
      $listItems = [];
      $include = [];
      $html = [];
      // get content type and list nodes
      if (is_array($responeData->data)) {
        $contentType = substr($path, strlen('/jsonapi/node/'));
        $listItems = array_merge($responeData->data);
        $include = $responeData->included;
      } else if (is_object($responeData->data)) {
        $contentType = substr($path, strlen('/jsonapi/node/'), strrpos($path, "/") - strlen('/jsonapi/node/'));
        $listItems[] = $responeData->data;
        $include[] = $responeData->included;
      }
      // get view_display of content type
      $list_entity_view_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->loadByProperties(['targetEntityType' => 'node', 'bundle' => $contentType]);
      // get list fields of content type
      $list_fields = array_filter(
        \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $contentType),
        function ($fieldDefinition) {
          return $fieldDefinition instanceof \Drupal\field\FieldConfigInterface;
        }
      );
      $data_html = [];
      foreach ($listItems as $item_key => $item) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($item->attributes->drupal_internal__nid);
        foreach($list_entity_view_display as $entity_view_display_key => $entity_view_display) {
          $view_html = "<div class='node__content clearfix'>";
          $view_mode = $entity_view_display->get('mode');
          // get fields is displayed in view
          $displayed_fields = array_merge($entity_view_display->get('content'));
          // sort by weight increase
          uasort($displayed_fields, function ($a, $b) {
            return $a['weight'] - $b['weight'];
          });
          foreach($displayed_fields as $field_name => $field_settings) {
            if(in_array($field_name, array_keys($list_fields))) {
              $build = $node->$field_name->view($view_mode);
              $html_of_field = Html::transformRootRelativeUrlsToAbsolute(\Drupal::service('renderer')->renderPlain($build), \Drupal::request()->getSchemeAndHttpHost());
              $view_html.= $html_of_field;
            }
          }
          $view_html.= "</div>";
          $data_html["{$item->attributes->drupal_internal__nid}_{$view_mode}"] = $view_html;
        }
      }
      $decoded['html'] = $data_html;
      $response->setContent(Json::encode($decoded));
    }

    // Decode and add JWT token
    if ($path === '/user/login' || $path === '/api/verify') {
      if ($content = $response->getContent()) {
        if ($decoded = Json::decode($content)) {
          // Add JWT access_token
          $access_token = $this->jwtAuth->generateToken();
          $decoded['access_token'] = $access_token;
          // Set new response JSON
          $response->setContent(Json::encode($decoded));
          $event->setResponse($response);
        }
      }
    }
  }

  /**
   * The subscribed events.
   */
  public static function getSubscribedEvents(): array
  {
    $events = [];
    $events[KernelEvents::RESPONSE][] = ['onHttpLoginResponse'];
    return $events;
  }
}
