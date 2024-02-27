<?php

namespace Drupal\poeditor\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormStateInterface;
use Drupal\locale\Form\TranslateFormBase;

/**
 * Form for uploading the translation file.
 */
class UploadForm extends TranslateFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'poeditor_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Call parent buildForm to initialize messages.

    $form['upload_file_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload Translation File'),
      '#attributes' => ['class' => ['upload-file-container']],
    ];

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

    // Submit button for step 1.
    $form['upload_file_fieldset']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];

    // Paginate messages.
    $messages = $form_state->getValue('messages', []);
    $page = $form_state->get('page') ?? 0;
    $chunked_messages = array_chunk($messages, 30); // Paginate with 30 items per page.
    $page_messages = $chunked_messages[$page] ?? [];

    $filter_values = $this->translateFilterValues();
    $langcode = $filter_values['langcode'];

    $this->languageManager->reset();
    $languages = $this->languageManager->getLanguages();

    $langname = isset($langcode) ? $languages[$langcode]->getName() : "- None -";

    $form['#attached']['library'][] = 'locale/drupal.locale.admin';

    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $filter_values['langcode'],
    ];

    // Add messages table.
    $form['messages_table'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#language' => $langname,
      '#header' => [
        $this->t('Source string'),
        $this->t('Translation for @language', ['@language' => $langname]),
      ],
      '#empty' => $this->t('No strings available.'),
      '#attributes' => ['class' => ['locale-translate-edit-table']],
    ];

    // Populate messages table with messages for the current page.
    foreach ($page_messages as $msgid => $msgstr) {
      $form['messages_table'][$msgid] = [
        'msgid' => ['#plain_text' => $msgid],
        'msgstr' => [
          '#type' => 'textfield',
          '#default_value' => $msgstr,
        ],
      ];
    }

    $form['pager'] = [
      '#type' => 'pager',
      '#wrapper_attributes' => ['class' => ['pager']],
      '#attributes' => ['role' => 'navigation', 'aria-labelledby' => 'pagination-heading'],
      '#heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Pagination'),
        '#attributes' => ['class' => ['visually-hidden'], 'id' => 'pagination-heading'],
      ],
    ];

    $form['pager']['#prefix'] = '<nav class="pager" role="navigation" aria-labelledby="pagination-heading">';
    $form['pager']['#suffix'] = '</nav>';

    // Add your custom pager items here.
    // For example, you can loop through the pages and create the corresponding links.
    $pages = range(1, 10); // Example range of pages.
    foreach ($pages as $page_number) {
      $class = ($page_number == 5) ? 'is-active' : ''; // Example active class.
      $link_text = ($page_number == 5) ? 'Page courante' : $page_number; // Example link text.
      $form['pager']['#markup'] .= '<a href="?page=' . $page_number . '" title="Go to page ' . $page_number . '" class="pager__link pager__link--number ' . $class . '"><span class="visually-hidden">Page</span>' . $link_text . '</a>';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Call parent validateForm for additional validation.
    parent::validateForm($form, $form_state);

    // Validate file upload...
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
    // Save messages into form state.
    $form_state->setValue('messages', $messages);
    $form_state->setRebuild();
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
  protected function parsePoFile($content) {
    $messages = [];
    preg_match_all('/msgid "(.*)"\nmsgstr "(.*)"\n/', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $msgid = $match[1];
      $msgstr = $match[2];
      $messages[$msgid] = $msgstr;
    }
    return $messages;
  }

}
