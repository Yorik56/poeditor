<?php

namespace Drupal\poeditor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poeditor\Form\FilterForm;
use Drupal\poeditor\Form\UploadForm;
use Symfony\Component\HttpFoundation\Request;

class PoeditorController extends ControllerBase {
    /**
     * Displays upload and filter forms.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return array
     *   A render array containing the forms.
     */
    public function displayUploadAndFilterForms(Request $request): array {
        $filter_form = $this->formBuilder()->getForm(FilterForm::class);
        $upload_form = $this->formBuilder()->getForm(UploadForm::class);

        return [
            '#theme' => 'upload_and_filter_forms',
            '#upload_form' => $upload_form,
            '#filter_form' => $filter_form,
        ];
    }
}
