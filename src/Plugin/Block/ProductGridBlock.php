<?php

namespace Drupal\bootiq_product_blocks\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configurable product grid for storefront landing pages.
 */
#[Block(
  id: 'bootiq_product_grid',
  admin_label: new TranslatableMarkup('Product grid'),
  category: new TranslatableMarkup('Bootiq')
)]
final class ProductGridBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'title' => (string) $this->t('Recent products'),
      'sort' => 'recent',
      'rows' => 2,
    ] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->configuration['title'] ?? '',
      '#maxlength' => 120,
    ];

    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Product order'),
      '#default_value' => $this->configuration['sort'] ?? 'recent',
      '#options' => [
        'recent' => $this->t('Most recent'),
        'best_selling' => $this->t('Best selling'),
      ],
    ];

    $form['rows'] = [
      '#type' => 'select',
      '#title' => $this->t('Rows'),
      '#description' => $this->t('Products are shown in four columns on desktop.'),
      '#default_value' => $this->configuration['rows'] ?? 2,
      '#options' => [
        2 => $this->t('2 rows (8 products)'),
        3 => $this->t('3 rows (12 products)'),
        4 => $this->t('4 rows (16 products)'),
      ],
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['title'] = trim((string) $form_state->getValue('title'));
    $this->configuration['sort'] = (string) $form_state->getValue('sort');
    $this->configuration['rows'] = (int) $form_state->getValue('rows');
  }

  public function build(): array {
    $rows = $this->normalizeRows($this->configuration['rows'] ?? 2);
    $limit = $rows * 4;
    $products = $this->loadProducts($this->configuration['sort'] ?? 'recent', $limit);
    $view_builder = $this->entityTypeManager->getViewBuilder('commerce_product');

    $items = [];
    foreach ($products as $product) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bootiq-product-grid-block__item']],
        'product' => $view_builder->view($product, 'teaser'),
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bootiq-product-grid-block',
          'bootiq-product-grid-block--' . ($this->configuration['sort'] ?? 'recent'),
          'bootiq-product-grid-block--rows-' . $rows,
        ],
      ],
      '#attached' => [
        'library' => ['bootiq_product_blocks/product_grid'],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags(['commerce_product_list'], $this->getOrderCacheTags()),
        'contexts' => ['languages:language_interface'],
        'max-age' => 300,
      ],
    ];

    if (!empty($this->configuration['title'])) {
      $build['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->configuration['title'],
        '#attributes' => ['class' => ['bootiq-product-grid-block__title']],
      ];
    }

    if ($items) {
      $build['grid'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bootiq-product-grid-block__grid']],
        'items' => $items,
      ];
    }
    else {
      $build['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No products available.'),
        '#attributes' => ['class' => ['bootiq-product-grid-block__empty']],
      ];
    }

    return $build;
  }

  /**
   * Loads published products ordered by the selected strategy.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface[]
   *   Product entities.
   */
  private function loadProducts(string $sort, int $limit): array {
    if ($sort === 'best_selling') {
      $ids = $this->loadBestSellingProductIds($limit);
      if ($ids) {
        return $this->loadProductsPreservingOrder($ids);
      }
    }

    $query = $this->entityTypeManager->getStorage('commerce_product')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $ids = $query->execute();
    return $ids ? $this->entityTypeManager->getStorage('commerce_product')->loadMultiple($ids) : [];
  }

  /**
   * Returns product IDs by sold quantity, falling back when no sales exist.
   */
  private function loadBestSellingProductIds(int $limit): array {
    if (!$this->database->schema()->tableExists('commerce_order_item') || !$this->database->schema()->tableExists('commerce_product__variations')) {
      return [];
    }

    $query = $this->database->select('commerce_order_item', 'oi');
    $query->join('commerce_product__variations', 'pv', 'pv.variations_target_id = oi.purchased_entity');
    if ($this->database->schema()->tableExists('commerce_order')) {
      $query->leftJoin('commerce_order', 'o', 'o.order_id = oi.order_id');
      $or = $query->orConditionGroup()
        ->condition('o.state', ['completed', 'fulfillment'], 'IN')
        ->isNull('o.state');
      $query->condition($or);
    }
    $query->addField('pv', 'entity_id', 'product_id');
    $query->addExpression('SUM(oi.quantity)', 'sold_quantity');
    $query->groupBy('pv.entity_id');
    $query->orderBy('sold_quantity', 'DESC');
    $query->range(0, $limit);

    return array_map('intval', $query->execute()->fetchCol());
  }

  /**
   * @param int[] $ids
   *   Product IDs.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface[]
   *   Product entities in requested order.
   */
  private function loadProductsPreservingOrder(array $ids): array {
    $products = $this->entityTypeManager->getStorage('commerce_product')->loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $id) {
      if (!empty($products[$id]) && $products[$id]->isPublished()) {
        $ordered[$id] = $products[$id];
      }
    }
    return $ordered;
  }

  private function getOrderCacheTags(): array {
    return ($this->configuration['sort'] ?? 'recent') === 'best_selling' ? ['commerce_order_list', 'commerce_order_item_list'] : [];
  }

  private function normalizeRows(mixed $rows): int {
    $rows = (int) $rows;
    return in_array($rows, [2, 3, 4], TRUE) ? $rows : 2;
  }

}
