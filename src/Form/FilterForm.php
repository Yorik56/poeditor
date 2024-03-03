<?php

namespace Drupal\poeditor\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FilterForm class.
 *
 * Defines a form for filtering and searching content.
 */
class FilterForm extends FormBase {

    protected CacheBackendInterface $cacheBackend;

    /**
     * Constructs a FilterForm object.
     *
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   The cache backend service.
     */
    public function __construct(CacheBackendInterface $cache_backend) {
        $this->cacheBackend = $cache_backend;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): FilterForm {
        // Use the create() method for dependency injection.
        return new static(
            $container->get('cache.default')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'poeditor_filter_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
        // Retrieve filtered values from cache.
        $cached_values = $this->cacheBackend->get('poeditor_filtered_values')->data ?? [];

        // Set default values for search and filter fields based on cached values.
        $default_search = $cached_values['search'] ?? '';
        $default_filter = $cached_values['filter'] ?? 'all';

        // Build filter form.
        $form['filter_fieldset'] = [
            '#type' => 'details',
            '#title' => $this->t('Filters and Search'),
            '#attributes' => ['class' => ['filter-container']],
            '#open' => TRUE
        ];

        // Add the search field.
        $form['filter_fieldset']['search'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Search'),
            '#placeholder' => $this->t('Search by msgid or msgstr'),
            '#default_value' => $default_search, // Set default value
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
            '#default_value' => $default_filter, // Set default value
        ];

        // Add the "Results per page" field.
        $form['filter_fieldset']['results_per_page'] = [
            '#type' => 'select',
            '#title' => $this->t('Results per page'),
            '#options' => [
                10 => $this->t('10'),
                20 => $this->t('20'),
                30 => $this->t('30'),
                50 => $this->t('50'),
                100 => $this->t('100'),
            ],
            '#default_value' => $cached_values['results_per_page'] ?? 10, // Set default value
        ];

        // Add the submit button for filtering.
        $form['filter_fieldset']['submit_filter'] = [
            '#type' => 'submit',
            '#value' => $this->t('Filter'),
        ];

        // Add the "Reset" button.
        $form['filter_fieldset']['reset_filter'] = [
            '#type' => 'submit',
            '#value' => $this->t('Reset'),
            '#submit' => ['::resetFilter'], // Define a custom submit handler
        ];

        // Set the complete form state in the form render array.
        $form['#form_state'] = $form_state;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Validate search field.
        $search = $form_state->getValue('search');
        // Use Drupal's validation method to sanitize submitted data.
        $search = \Drupal\Component\Utility\Xss::filter($search);
        if (strlen($search) > 255) {
            $form_state->setErrorByName('search', $this->t('Search value must be less than 255 characters.'));
        }

        // Validate filter field.
        $filter = $form_state->getValue('filter');
        $allowed_filters = ['all', 'translated', 'untranslated'];
        if (!in_array($filter, $allowed_filters)) {
            $form_state->setErrorByName('filter', $this->t('Invalid filter value.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Get the filtered values from the form submission.
        $filtered_values = [
            'search' => $form_state->getValue('search'),
            'filter' => $form_state->getValue('filter'),
            'results_per_page' => $form_state->getValue('results_per_page'),
        ];

        // Store the filtered values in the cache.
        $this->cacheBackend->set('poeditor_filtered_values', $filtered_values, Cache::PERMANENT, ['poeditor']);
    }

    /**
     * Custom submit handler for the "Reset" button.
     */
    public function resetFilter(array &$form, FormStateInterface $form_state) {
        // Clear the cached filtered values.
        $this->cacheBackend->delete('poeditor_filtered_values');

        // Redirect back to the current page.
        $form_state->setRedirect('<current>');
    }
}
