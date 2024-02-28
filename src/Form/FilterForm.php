<?php

namespace Drupal\poeditor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for filtering and displaying translation content.
 */
class FilterForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'poeditor_filter_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        // Build filter form.
        $form['filter_fieldset'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Filters and Search'),
            '#attributes' => ['class' => ['filter-container']],
        ];
        // Add the search field.
        $form['filter_fieldset']['search'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Search'),
            '#placeholder' => $this->t('Search by msgid or msgstr'),
        ];

        // Add the select field to filter by msgid.
        $form['filter_fieldset']['filter'] = [
            '#type' => 'select',
            '#title' => $this->t('Filter'),
            '#options' => [
                'all' => $this->t('All'),
                'translated' => $this->t('Translated'),
                'untranslated' => $this->t('Untranslated'),
            ],
            '#default_value' => 'all',
        ];

        // Add the submit button for filtering.
        $form['filter_fieldset']['submit_filter'] = [
            '#type' => 'submit',
            '#value' => $this->t('Filter'),
        ];

        // Add the submission callback for the filter form.
        $form['#submit'][] = '::filterSubmit';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Validate filter form...
        // You can add validation logic here if needed.
        // For example, you might want to ensure that the search field is not empty.
        $values = $form_state->getValues();
        $search_query = $values['search'];
//        if (empty($search_query)) {
//            $form_state->setErrorByName('search', $this->t('The search field cannot be empty.'));
//        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Handle filter form submission...
        // You can add your logic here to process the submitted values.
        // For example, you might want to retrieve the filter values and perform actions based on them.
        $values = $form_state->getValues();
        $search_query = $values['search'];
        $filter_option = $values['filter'];

        // Perform actions based on the submitted values, such as filtering the translation content, etc.
    }

    /**
     * Submission callback for the filter form.
     */
    public function filterSubmit(array &$form, FormStateInterface $form_state) {
        // This function is called when the filter form is submitted.
        // You can use it to perform specific actions when the form is submitted.
        // For example, you might want to process the form values here.

        // Retrieve the value of the filter selection field.
        $filter_value = $form_state->getValue('filter');

        // Perform actions based on the selected filter value.
        // You can customize this part based on your requirements.
        switch ($filter_value) {
            case 'all':
                // If 'All' is selected, you might want to display all translation content.
                // Add your logic here...
                break;
            case 'translated':
                // If 'Translated' is selected, you might want to display only translated content.
                // Add your logic here...
                break;
            case 'untranslated':
                // If 'Untranslated' is selected, you might want to display only untranslated content.
                // Add your logic here...
                break;
            default:
                // Handle default case.
                break;
        }
    }


}
