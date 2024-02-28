<?php

namespace Drupal\poeditor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poeditor\Form\FilterForm;
use Drupal\poeditor\Form\UploadForm;

class PoeditorController extends ControllerBase {
  /**
   * Displays upload and filter forms.
   *
   * @return array
   *   A render array containing the forms.
   */
  public function displayUploadAndFilterForms() {
    // Get instances of filter and upload forms.
    $filter_form = $this->formBuilder()->getForm(FilterForm::class);
    $upload_form = $this->formBuilder()->getForm(UploadForm::class);

    // Build the render array.
    $build = [
      '#theme' => 'upload_and_filter_forms',
      '#upload_form' => $upload_form,
      '#filter_form' => $filter_form,
    ];

    return $build;
  }
}
