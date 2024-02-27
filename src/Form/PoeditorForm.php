<?php

namespace Drupal\poeditor\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PoeditorForm.
 */
class PoeditorForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'poeditor_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        // Vérifier si le formulaire est soumis pour la première fois.
        $file_submitted = $form_state->get('file_submitted');
        $step_2_submitted = $form_state->get('step_2_submitted');

        // Étape 1 : Affichage du champ d'upload de fichier.
        if (!$file_submitted && !$step_2_submitted) {
            return $this->buildUploadForm($form, $form_state);
        }

        // Étape 2 : Affichage des filtres et du contenu du fichier chargé.
        return $this->buildFilterForm($form, $form_state);
    }

    /**
     * Build the form for uploading the translation file.
     */
    protected function buildUploadForm(array $form, FormStateInterface $form_state) {
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

        // Bouton de soumission pour l'étape 1.
        $form['upload_file_fieldset']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
        ];

        return $form;
    }

    /**
     * Build the form for filtering and displaying the translation content.
     */
    protected function buildFilterForm(array $form, FormStateInterface $form_state) {
        // Afficher les filtres.
        $form['filter_fieldset'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Filters and Search'),
            '#attributes' => ['class' => ['filter-container']],
        ];
        // Ajouter le champ de recherche.
        $form['filter_fieldset']['search'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Search'),
            '#placeholder' => $this->t('Search by msgid or msgstr'),
        ];

        // Ajouter le champ de sélection pour filtrer les msgid.
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

        // Bouton de soumission pour le filtrage.
        $form['filter_fieldset']['submit_filter'] = [
            '#type' => 'submit',
            '#value' => $this->t('Filter'),
        ];

        // Afficher le contenu du fichier chargé dans les champs texte.
        $file_contents = file_get_contents($this->file->getFileUri());
        $messages = $this->parsePoFile($file_contents);
        foreach ($messages as $msgid => $msgstr) {
            if ($msgid) {
                $form['translate_fields_fieldset']['msgid_' . $msgid] = [
                    '#type' => 'textfield',
                    '#title' => $msgid,
                    '#default_value' => $msgstr,
                ];
            }
        }

        // Marquer l'étape 2 comme soumise.
        $form_state->set('step_2_submitted', TRUE);

        // Bouton d'export.
        $form['export_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Export'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $file_name = $form_state->getValue('translation_file');
        if (!empty($file_name)) {
            $this->file = _file_save_upload_from_form($form['upload_file_fieldset']['translation_file'], $form_state, 0);
            if (!$this->file) {
                $form_state->setErrorByName('translation_file', $this->t('File to import not found.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Marquer le formulaire comme soumis dans la variable de session.
        $form_state->set('file_submitted', TRUE);
        $form_state->setRebuild();
//        // Étape 1 : Affichage du champ d'upload de fichier.
//        if (!$form_state->get('step_2_submitted')) {
//            // Recharger le formulaire.
//
//        }

    }

    /**
     * Callback de soumission pour le filtre.
     */
    public function filterSubmit(array &$form, FormStateInterface $form_state) {
        // Récupérer la valeur du champ de sélection de filtre.
        $filter_value = $form_state->getValue('filter');

        // Récupérer tous les champs de traduction.
        $translate_fields = $form_state->getValues()['translate_fields_fieldset'];

        // Filtrer les champs de traduction en fonction de la valeur de sélection de filtre.
        $filtered_fields = [];
        foreach ($translate_fields as $field_key => $field_value) {
            // Filtrer par type selon la valeur du champ de sélection de filtre.
            if ($filter_value === 'all') {
                $filtered_fields[$field_key] = $field_value;
            } elseif ($filter_value === 'translated' && !empty($field_value)) {
                $filtered_fields[$field_key] = $field_value;
            } elseif ($filter_value === 'untranslated' && empty($field_value)) {
                $filtered_fields[$field_key] = $field_value;
            }
        }

        // Mettre à jour les champs de traduction avec les champs filtrés.
        $form_state->setValue('translate_fields_fieldset', $filtered_fields);

        // Marquer l'étape 2 comme soumise.
        $form_state->set('step_2_submitted', TRUE);
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
