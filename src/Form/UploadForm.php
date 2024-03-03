<?php

namespace Drupal\poeditor\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\Element\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for uploading translation files and managing translations.
 */
class UploadForm extends FormBase {

    private $file = null;

    /**
     * The pager manager service.
     *
     * @var \Drupal\Core\Pager\PagerManagerInterface
     */
    protected PagerManagerInterface $pagerManager;

    /**
     * The cache backend service.
     *
     * @var \Drupal\Core\Cache\CacheBackendInterface
     */
    protected CacheBackendInterface $cacheBackend;

    /**
     * Constructs an UploadForm object.
     *
     * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
     *   The pager manager service.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   The cache backend service.
     */
    public function __construct(PagerManagerInterface $pager_manager, CacheBackendInterface $cache_backend) {
        $this->pagerManager = $pager_manager;
        $this->cacheBackend = $cache_backend;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): UploadForm {
        // Using the create() method for dependency injection.
        return new static(
            $container->get('pager.manager'),
            $container->get('cache.default')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'poeditor_upload_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
        // Build the file upload fieldset.
        $form['upload_file_fieldset'] = [
            '#type' => 'details',
            '#title' => $this->t('Upload Translation File'),
            '#attributes' => ['class' => ['upload-file-container']],
            '#open' => TRUE,
        ];

        // Always display the file upload field.
        $form = $this->buildUploadField($form, $form_state);

        // Check if a file is already uploaded.
        $uploaded_file_info = $this->getUploadedFileInfo();
        if ($uploaded_file_info) {
            // If a file is uploaded, display its information and provide an option to upload another file.
            $form['upload_file_fieldset']['uploaded_file_info'] = [
                '#markup' => $this->t('Uploaded file: @filename', ['@filename' => $uploaded_file_info]),
            ];
            $form['upload_file_fieldset']['upload_another'] = [
                '#type' => 'submit',
                '#value' => $this->t('Upload Another File'),
                '#submit' => ['::uploadAnother'],
            ];
        } else {
            // Build the submit button for uploading translation file.
            $form = $this->buildSubmitButton($form);
        }

        // Build the messages table and pagination.
        return $this->buildMessagesTable($form, $form_state);
    }

    /**
     * Helper function to get uploaded file information.
     *
     * @return string|null
     *   The name of the uploaded file, or null if no file uploaded.
     */
    protected function getUploadedFileInfo(): ?string {
        if ($this->file) {
            // Return the name of the uploaded file.
            return $this->file->getFilename();
        }
        return null;
    }

    /**
     * Submit handler to allow uploading another file.
     */
    public function uploadAnother(array &$form, FormStateInterface $form_state) {
        // Remove the file from form state.
        $this->file = null;
        // Rebuild the form to hide the uploaded file information and show the file upload field again.
        $form_state->setRebuild();
    }

    /**
     * Builds the file upload fieldset.
     *
     * @param array $form
     *   The form array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return array
     *   The updated form array.
     */
    protected function buildUploadField(array $form, FormStateInterface $form_state): array {
        // Using Environment::getUploadMaxSize() to get the maximum upload size.
        $validators = [
            'file_validate_extensions' => ['po'],
            'file_validate_size' => [Environment::getUploadMaxSize()],
        ];
        $form['upload_file_fieldset']['translation_file'] = [
            '#type' => 'file',
            '#title' => $this->t('Translation file'),
            '#description' => [
                '#theme' => 'file_upload_help',
                '#description' => $this->t('A Gettext Portable Object file.'),
                '#upload_validators' => $validators,
            ],
            '#size' => 50,
            '#upload_validators' => $validators,
            '#upload_location' => 'translations://',
            '#attributes' => ['class' => ['file-import-input']],
        ];
        return $form;
    }

    /**
     * Builds the submit button for uploading translation file.
     *
     * @param array $form
     *   The form array.
     *
     * @return array
     *   The updated form array.
     */
    protected function buildSubmitButton(array $form): array {
        // Submit button for uploading translation file.
        $form['upload_file_fieldset']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
        ];
        return $form;
    }

    /**
     * Builds the messages table and pagination.
     *
     * @param array $form
     *   The form array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return array
     *   The updated form array.
     */
    protected function buildMessagesTable(array $form, FormStateInterface $form_state): array {
        // Paginate messages.
        $messages = $this->getMessagesFromCache($form_state);

        // Get filtered values from the session.
        $translation_filter = $this->cacheBackend->get('poeditor_filtered_values')->data['filter'] ?? 'all';
        $messages = $this->applyTranslationFilter($messages, $translation_filter);

        // Applying search filter.
        $search_term = $this->cacheBackend->get('poeditor_filtered_values')->data['search'] ?? '';
        $messages = $this->applySearchFilter($messages, $search_term);

        // Get the "Results per page" value from cache.
        $results_per_page = $this->cacheBackend->get('poeditor_filtered_values')->data['results_per_page'] ?? 10;

        $request = \Drupal::request();
        $page = $request->query->getInt('page', 0); // Getting the "page" value from the URL.
        $limit = $results_per_page; // Setting the limit to the value obtained from cache.
        $total = count($messages); // Total number of messages.
        $pager = $this->pagerManager->createPager($total, $limit);
        $pager->setCurrentPage($page);

        // Getting messages for the current page.
        $offset = $pager->getCurrentPage() * $limit;
        $current_page_messages = array_slice($messages, $offset, $limit);

        // Building the table for messages.
        $form['messages_table_details'] = [
            '#type' => 'details',
            '#title' => $this->t('Messages'),
            '#open' => TRUE,
        ];

        $form['messages_table_details']['messages_table'] = [
            '#type' => 'table',
            '#header' => [
                $this->t('Source string'),
                $this->t('Translation'),
            ],
            '#empty' => $this->t('No strings available.'),
            '#attributes' => ['class' => ['locale-translate-edit-table']],
        ];

        // Filling the messages table with messages for the current page.
        foreach ($current_page_messages as $msgid => $msgstr) {
            $form['messages_table_details']['messages_table'][$msgid]['msgid'] = [
                '#type' => 'markup',
                '#markup' => $msgid,
            ];
            $form['messages_table_details']['messages_table'][$msgid]['msgstr'] = [
                '#type' => 'textfield',
                '#default_value' => $msgstr,
            ];
        }

        // Adding pagination.
        $form['messages_table_details']['pager'] = [
            '#type' => 'pager',
        ];

        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Additional validation.
        parent::validateForm($form, $form_state);

        // File upload validation...
        $file = $form_state->getValue('translation_file');
        if (!$file) {
            $form_state->setErrorByName('translation_file', $this->t('No file uploaded.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $file_name = $form_state->getValue('translation_file');
        if (!empty($file_name)) {
            $this->file = _file_save_upload_from_form($form['upload_file_fieldset']['translation_file'], $form_state, 0);
            if (!$this->file) {
                $form_state->setErrorByName('translation_file', $this->t('File to import not found.'));
            }
        }
        $file_contents = file_get_contents($this->file->getFileUri());

        $messages = $this->parsePoFile($file_contents);
        // Save messages to cache.
        $this->cacheBackend->set('poeditor_messages', $messages, Cache::PERMANENT, ['poeditor']);
        // Store filtered messages in form state.
        $form_state->set('filtered_messages', $messages);
        // Set page to 0 to go back to the first page.
        $form_state->setValue('page', 0);
        $form_state->setRebuild();
    }

    /**
     * Get messages from cache.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return array
     *   The messages array.
     */
    protected function getMessagesFromCache(FormStateInterface $form_state): array {
        $cache = $this->cacheBackend->get('poeditor_messages');
        return $cache ? $cache->data : [];
    }

    /**
     * Parse the content of a .po file and extract msgid and msgstr pairs.
     *
     * @param string $content
     *   The content of the .po file.
     *
     * @return array
     *   An associative array of msgid and msgstr pairs.
     */
    protected function parsePoFile(string $content): array {
        $messages = [];
        // Using preg_match_all() to extract msgid and msgstr pairs.
        preg_match_all('/msgid "(.*)"\nmsgstr "(.*)"\n/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $msgid = $match[1];
            $msgstr = $match[2];
            $messages[$msgid] = $msgstr;
        }
        return $messages;
    }

    /**
     * Apply search filter to messages array.
     *
     * @param array $messages
     *   The array of messages to filter.
     * @param string $search_term
     *   The search term to filter by.
     *
     * @return array
     *   The filtered array of messages.
     */
    private function applySearchFilter(array $messages, string $search_term): array {
        $filtered_messages = [];
        foreach ($messages as $msgid => $msgstr) {
            // Convert both msgid and msgstr to lowercase for case-insensitive search.
            $lowercase_msgid = strtolower($msgid);
            $lowercase_msgstr = strtolower($msgstr);

            // Check if either msgid or msgstr contains the search term.
            if (strpos($lowercase_msgid, strtolower($search_term)) !== false || strpos($lowercase_msgstr, strtolower($search_term)) !== false) {
                $filtered_messages[$msgid] = $msgstr;
            }
        }
        return $filtered_messages;
    }

    /**
     * Apply translation filter to messages array.
     *
     * @param array $messages
     *   The array of messages to filter.
     * @param string $translation_filter
     *   The translation filter to apply.
     *
     * @return array
     *   The filtered array of messages.
     */
    private function applyTranslationFilter(array $messages, string $translation_filter): array {
        $filtered_messages = [];
        foreach ($messages as $msgid => $msgstr) {
            if ($translation_filter === 'all') {
                // Display all messages without filter.
                $filtered_messages[$msgid] = $msgstr;
            } elseif ($translation_filter === 'translated' && $msgstr !== '') {
                // Display only translated messages.
                $filtered_messages[$msgid] = $msgstr;
            } elseif ($translation_filter === 'untranslated' && $msgstr === '') {
                // Display only untranslated messages.
                $filtered_messages[$msgid] = $msgstr;
            }
        }
        return $filtered_messages;
    }
}
