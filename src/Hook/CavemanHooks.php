<?php

namespace Drupal\caveman\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for the Caveman filter module.
 */
class CavemanHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name) {
    if ($route_name === 'help.page.caveman') {
      return $this->t('This module is a text filter that turns your site into caveman-speak. Ugh! Activates on April 1st or when enabled site-wide at <a href=":url">Caveman settings</a>.', [
        ':url' => Url::fromRoute('caveman.admin_settings')->toString(),
      ]);
    }
  }

}
