<?php

namespace Drupal\bootiq_product_blocks\Plugin\Block;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides related products for commerce product detail pages.
 */
#[Block(
  id: 'bootiq_similar_products',
  admin_label: new TranslatableMarkup('Similar products'),
  category: new TranslatableMarkup('Bootiq')
)]
final class SimilarProductsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const SOURCES = [
    'brand' => 'product_brand',
    'collection' => 'product_collections',
    'tag' => 'product_tags',
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'title' => (string) $this->t('Similar products'),
      'source' => 'collection',
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

    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Match products by'),
      '#default_value' => $this->configuration['source'] ?? 'collection',
      '#options' => [
        'brand' => $this->t('Brand'),
        'collection' => $this->t('Collection'),
        'tag' => $this->t('Tag'),
      ],
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['title'] = trim((string) $form_state->getValue('title'));
    $this->configuration['source'] = (string) $form_state->getValue('source');
  }

  public function build(): array {
    $current_product = $this->getCurrentProduct();
    $source = $this->normalizeSource($this->configuration['source'] ?? 'collection');
    $products = $current_product ? $this->loadSimilarProducts($current_product, $source) : [];
    $view_builder = $this->entityTypeManager->getViewBuilder('commerce_product');

    $items = [];
    foreach ($products as $product) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bootiq-product-grid-block__item']],
        'product' => $view_builder->view($product, 'teaser'),
      ];
    }

    $cache_tags = ['commerce_product_list'];
    if ($current_product) {
      $cache_tags = Cache::mergeTags($cache_tags, $current_product->getCacheTags());
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'bootiq-product-grid-block',
          'bootiq-product-grid-block--similar',
          'bootiq-product-grid-block--similar-' . $source,
        ],
      ],
      '#attached' => [
        'library' => ['bootiq_product_blocks/product_grid'],
      ],
      '#cache' => [
        'tags' => $cache_tags,
        'contexts' => ['route', 'languages:language_interface'],
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
        '#value' => $this->t('No similar products available.'),
        '#attributes' => ['class' => ['bootiq-product-grid-block__empty']],
      ];
    }

    return $build;
  }

  private function getCurrentProduct(): ?ProductInterface {
    $product = $this->routeMatch->getParameter('commerce_product');
    if (is_numeric($product)) {
      $product = $this->entityTypeManager->getStorage('commerce_product')->load((int) $product);
    }
    return $product instanceof ProductInterface ? $product : NULL;
  }

  /**
   * @return \Drupal\commerce_product\Entity\ProductInterface[]
   *   Similar products.
   */
  private function loadSimilarProducts(ProductInterface $current_product, string $source): array {
    $field_name = self::SOURCES[$source];
    if (!$current_product->hasField($field_name) || $current_product->get($field_name)->isEmpty()) {
      return [];
    }

    $term_ids = array_column($current_product->get($field_name)->getValue(), 'target_id');
    if (!$term_ids) {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('commerce_product')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('product_id', $current_product->id(), '<>')
      ->condition($field_name, $term_ids, 'IN')
      ->sort('created', 'DESC')
      ->range(0, 5);

    $ids = $query->execute();
    return $ids ? $this->entityTypeManager->getStorage('commerce_product')->loadMultiple($ids) : [];
  }

  private function normalizeSource(string $source): string {
    return array_key_exists($source, self::SOURCES) ? $source : 'collection';
  }

}
