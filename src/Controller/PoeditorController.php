<?php

namespace Drupal\poeditor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\poeditor\Form\FilterForm;
use Drupal\poeditor\Form\UploadForm;

class PoeditorController extends ControllerBase {
  public function displayUploadAndFilterForms() {
    $filter_form = $this->formBuilder()->getForm(FilterForm::class);
    $upload_form = $this->formBuilder()->getForm(UploadForm::class);

    // Total number of items and items per page.
    $total_items = 100; // Example total count of items.
    $items_per_page = 10; // Number of items per page.

    // Current page (assuming it comes from a request parameter).
    $current_page = \Drupal::request()->query->get('page') ?? 0;

    // Get the pager Manager service statically.
    $pager_manager = \Drupal::service('pager.manager');

    // Initialize the pager with current page.
    $pager = $pager_manager->createPager($total_items, $items_per_page, 0, $current_page);

    // Build the pager items.
    $pager_items = [];
    for ($page = 0; $page < $pager->getTotalPages(); $page++) {
      $url = Url::fromRoute('<current>', [], ['query' => ['page' => $page]]);
      $pager_items[] = Link::fromTextAndUrl($page + 1, $url)->toRenderable();
    }

    // Render the pager.
    $pager_html = [
      '#theme' => 'item_list',
      '#items' => $pager_items,
      '#attributes' => ['class' => ['pager']],
    ];

    // Build the render array.
    $build = [
      '#theme' => 'upload_and_filter_forms',
      '#upload_form' => $upload_form,
      '#filter_form' => $filter_form,
      '#pager' => $pager_html,
    ];

    return $build;
  }
}
