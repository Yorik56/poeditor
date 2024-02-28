<?php

namespace Drupal\poeditor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form example with pager.
 */
class FruitFormWithPager extends FormBase implements ContainerInjectionInterface {

  protected $pagerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pager.manager')
    );
  }

  /**
   * Constructs a new FruitFormWithPager object.
   */
  public function __construct(PagerManagerInterface $pager_manager) {
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fruit_form_with_pager';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Array of fruits.
    $fruits = [
      ['name' => 'Apple', 'stock' => 20],
      ['name' => 'Banana', 'stock' => 15],
      ['name' => 'Orange', 'stock' => 10],
      ['name' => 'Grape', 'stock' => 30],
      ['name' => 'Pineapple', 'stock' => 25],
      // Add more fruits as needed.
    ];

    // Pager settings.
    $limit = 2;
    $total = count($fruits);
    $pager = $this->pagerManager->createPager($total, $limit);

    // Get the current page from the pager query parameter.
    $current_page = $pager->getCurrentPage();

    // Set the current page value in the form state.
    $form_state->set('page', $current_page);

    $pager->setCurrentPage($current_page);

    // Get current page fruits.
    $offset = $pager->getCurrentPage() * $limit;
    $current_page_fruits = array_slice($fruits, $offset, $limit);

    // Build the table.
    $header = [
      'name' => $this->t('Fruit'),
      'stock' => $this->t('Stock'),
    ];
    $rows = [];
    foreach ($current_page_fruits as $fruit) {
      $rows[] = [
        'name' => $fruit['name'],
        'stock' => $fruit['stock'],
      ];
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    // Add pager to the form.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle submission if needed.
  }

}
