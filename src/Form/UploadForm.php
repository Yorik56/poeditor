<?php

namespace Drupal\poeditor\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * UploadForm class.
 */
class UploadForm extends FormBase {

  protected $languageManager;
  protected $pagerManager;
  protected $cacheBackend;

  /**
   * Constructs an UploadForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   */
  public function __construct(LanguageManagerInterface $language_manager, PagerManagerInterface $pager_manager, CacheBackendInterface $cache_backend) {
    $this->languageManager = $language_manager;
    $this->pagerManager = $pager_manager;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Utilisation de la méthode create() pour l'injection de dépendances.
    return new static(
      $container->get('language_manager'),
      $container->get('pager.manager'),
      $container->get('cache.default')
    );
  }

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
    // Utilisation de la traduction dans la méthode buildForm().
    $form['upload_file_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload Translation File'),
      '#attributes' => ['class' => ['upload-file-container']],
    ];

    // Utilisation de Environment::getUploadMaxSize() pour obtenir la taille maximale de téléchargement.
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
    $messages = $this->getMessagesFromCache($form_state);
    $request = \Drupal::request();
    $page = $request->query->getInt('page', 0); // Obtention de la valeur de "page" à partir de l'URL.
    $limit = 10; // Changement de la limite à 10.
    $total = count($messages); // Nombre total de messages.
    $pager = $this->pagerManager->createPager($total, $limit);
    $pager->setCurrentPage($page);

    // Obtention des messages de la page actuelle.
    $offset = $pager->getCurrentPage() * $limit;
    $current_page_messages = array_slice($messages, $offset, $limit);

    // Construction de la table pour les messages.
    $form['messages_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Source string'),
        $this->t('Translation'),
      ],
      '#empty' => $this->t('No strings available.'),
      '#attributes' => ['class' => ['locale-translate-edit-table']],
    ];

    // Remplissage de la table des messages avec les messages de la page actuelle.
    foreach ($current_page_messages as $msgid => $msgstr) {
      $form['messages_table'][$msgid]['msgid'] = [
        '#type' => 'markup',
        '#markup' => $msgid,
      ];
      $form['messages_table'][$msgid]['msgstr'] = [
        '#type' => 'textfield',
        '#default_value' => $msgstr,
      ];
    }

    // Ajout de la pagination.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation supplémentaire.
    parent::validateForm($form, $form_state);

    // Validation du téléchargement de fichier...
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
    // Enregistrer les messages dans le cache.
    $this->cacheBackend->set('poeditor_messages', $messages, Cache::PERMANENT, ['poeditor']);
    // Définir la page sur 0 pour revenir à la première page.
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
  protected function getMessagesFromCache(FormStateInterface $form_state) {
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
  protected function parsePoFile($content) {
    $messages = [];
    // Utilisation de preg_match_all() pour extraire les paires msgid et msgstr.
    preg_match_all('/msgid "(.*)"\nmsgstr "(.*)"\n/', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $msgid = $match[1];
      $msgstr = $match[2];
      $messages[$msgid] = $msgstr;
    }
    return $messages;
  }

}
