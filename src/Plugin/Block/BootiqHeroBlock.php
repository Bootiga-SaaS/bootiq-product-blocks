<?php

namespace Drupal\bootiq_product_blocks\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Provides the storefront hero used by the Bootiq frontpage.
 */
#[Block(
  id: 'bootiq_hero',
  admin_label: new TranslatableMarkup('Bootiq hero'),
  category: new TranslatableMarkup('Bootiq')
)]
final class BootiqHeroBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return [
      'kicker' => (string) $this->t('Spring edit'),
      'title' => (string) $this->t('Objects with character for everyday living.'),
      'body' => (string) $this->t('Discover warm ceramics, soft essentials and design pieces selected for a calm, modern home.'),
      'hero_image_url' => '/themes/contrib/bootiq/images/slide-2.jpg',
      'primary_label' => (string) $this->t('Shop new arrivals'),
      'primary_url' => '/products',
      'secondary_label' => (string) $this->t('Explore categories'),
      'category_1_label' => (string) $this->t('Home fragrance'),
      'category_1_title' => (string) $this->t('Ceramic candles'),
      'category_1_image_url' => '/themes/contrib/bootiq/images/slide-1.jpg',
      'category_2_label' => (string) $this->t('Living room'),
      'category_2_title' => (string) $this->t('Furniture'),
      'category_2_image_url' => '/themes/contrib/bootiq/images/slide-2.jpg',
      'category_3_label' => (string) $this->t('Essentials'),
      'category_3_title' => (string) $this->t('Soft apparel'),
      'category_3_image_url' => '/themes/contrib/bootiq/images/slide-3.jpg',
      'benefit_1' => (string) $this->t('Free returns on demo orders'),
      'benefit_2' => (string) $this->t('Secure checkout'),
      'benefit_3' => (string) $this->t('Ready for local pickup or shipping'),
    ] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    foreach ($this->editableFields() as $key => $definition) {
      $form[$key] = [
        '#type' => $definition['type'],
        '#title' => $definition['title'],
        '#default_value' => $this->configuration[$key] ?? '',
        '#maxlength' => 180,
      ];
    }

    $form['body']['#type'] = 'textarea';
    $form['body']['#rows'] = 3;
    $form['primary_url']['#description'] = $this->t('Use an internal path such as /products.');

    foreach ($this->imageKeys() as $image_key) {
      $form[$image_key] = $this->imageUploadElement($image_key, $form[$image_key]['#title']);
    }

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    foreach (array_keys($this->editableFields()) as $key) {
      if (in_array($key, $this->imageKeys(), TRUE)) {
        $this->configuration[$key] = $this->submittedImagePath($form_state, $key);
        continue;
      }
      $this->configuration[$key] = trim((string) $form_state->getValue($key));
    }
  }

  public function build(): array {
    $primary_url = $this->configuration['primary_url'] ?: '/products';
    $hero_image_attributes = $this->backgroundImageAttribute($this->configuration['hero_image_url'] ?? '');
    $category_1_attributes = $this->backgroundImageAttribute($this->configuration['category_1_image_url'] ?? '');
    $category_2_attributes = $this->backgroundImageAttribute($this->configuration['category_2_image_url'] ?? '');
    $category_3_attributes = $this->backgroundImageAttribute($this->configuration['category_3_image_url'] ?? '');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bp-bootiq-hero-block']],
      'hero' => [
        '#type' => 'html_tag',
        '#tag' => 'section',
        '#attributes' => [
          'class' => ['bp-hero'],
          'aria-labelledby' => 'bp-hero-title',
        ],
        'image' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['bp-hero__brand-image'],
            'aria-hidden' => 'true',
          ] + $hero_image_attributes,
        ],
        'panel' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['bp-hero__panel']],
          'kicker' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->configuration['kicker'],
            '#attributes' => ['class' => ['bp-kicker']],
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h1',
            '#value' => $this->configuration['title'],
            '#attributes' => ['id' => 'bp-hero-title'],
          ],
          'body' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->configuration['body'],
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['bp-hero__actions']],
            'primary' => [
              '#type' => 'link',
              '#title' => $this->configuration['primary_label'],
              '#url' => Url::fromUserInput($primary_url),
              '#attributes' => ['class' => ['btn', 'btn-primary']],
            ],
            'secondary' => [
              '#type' => 'html_tag',
              '#tag' => 'a',
              '#value' => $this->configuration['secondary_label'],
              '#attributes' => [
                'class' => ['bp-text-link'],
                'href' => '#bp-categories',
              ],
            ],
          ],
        ],
      ],
      'categories' => [
        '#type' => 'html_tag',
        '#tag' => 'section',
        '#attributes' => [
          'id' => 'bp-categories',
          'class' => ['bp-category-grid'],
          'aria-label' => $this->t('Shop by category'),
        ],
        'home' => $this->categoryCard('home', $this->configuration['category_1_label'], $this->configuration['category_1_title'], $category_1_attributes),
        'living' => $this->categoryCard('living', $this->configuration['category_2_label'], $this->configuration['category_2_title'], $category_2_attributes),
        'wear' => $this->categoryCard('wear', $this->configuration['category_3_label'], $this->configuration['category_3_title'], $category_3_attributes),
      ],
      'benefits' => [
        '#type' => 'html_tag',
        '#tag' => 'section',
        '#attributes' => [
          'class' => ['bp-value-strip'],
          'aria-label' => $this->t('Store benefits'),
        ],
        'benefit_1' => $this->benefit($this->configuration['benefit_1']),
        'benefit_2' => $this->benefit($this->configuration['benefit_2']),
        'benefit_3' => $this->benefit($this->configuration['benefit_3']),
      ],
      '#cache' => [
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

  private function categoryCard(string $modifier, string $label, string $title, array $image_attributes): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#attributes' => [
        'class' => ['bp-category-card', 'bp-category-card--' . $modifier],
        'href' => '/products',
      ] + $image_attributes,
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $title,
      ],
    ];
  }

  private function benefit(string $text): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $text,
    ];
  }

  private function editableFields(): array {
    return [
      'kicker' => ['type' => 'textfield', 'title' => $this->t('Kicker')],
      'title' => ['type' => 'textfield', 'title' => $this->t('Title')],
      'body' => ['type' => 'textarea', 'title' => $this->t('Body')],
      'hero_image_url' => ['type' => 'managed_file', 'title' => $this->t('Hero image')],
      'primary_label' => ['type' => 'textfield', 'title' => $this->t('Primary button label')],
      'primary_url' => ['type' => 'textfield', 'title' => $this->t('Primary button URL')],
      'secondary_label' => ['type' => 'textfield', 'title' => $this->t('Secondary link label')],
      'category_1_label' => ['type' => 'textfield', 'title' => $this->t('First category label')],
      'category_1_title' => ['type' => 'textfield', 'title' => $this->t('First category title')],
      'category_1_image_url' => ['type' => 'managed_file', 'title' => $this->t('First category image')],
      'category_2_label' => ['type' => 'textfield', 'title' => $this->t('Second category label')],
      'category_2_title' => ['type' => 'textfield', 'title' => $this->t('Second category title')],
      'category_2_image_url' => ['type' => 'managed_file', 'title' => $this->t('Second category image')],
      'category_3_label' => ['type' => 'textfield', 'title' => $this->t('Third category label')],
      'category_3_title' => ['type' => 'textfield', 'title' => $this->t('Third category title')],
      'category_3_image_url' => ['type' => 'managed_file', 'title' => $this->t('Third category image')],
      'benefit_1' => ['type' => 'textfield', 'title' => $this->t('First benefit')],
      'benefit_2' => ['type' => 'textfield', 'title' => $this->t('Second benefit')],
      'benefit_3' => ['type' => 'textfield', 'title' => $this->t('Third benefit')],
    ];
  }

  private function imageKeys(): array {
    return [
      'hero_image_url',
      'category_1_image_url',
      'category_2_image_url',
      'category_3_image_url',
    ];
  }

  private function imageUploadElement(string $key, $title): array {
    $default_fid = $this->fileIdFromPublicPath($this->configuration[$key] ?? '');
    return [
      '#type' => 'managed_file',
      '#title' => $title,
      '#default_value' => $default_fid ? [$default_fid] : [],
      '#description' => $this->t('Upload a storefront image. The current image is shown as a preview when available.'),
      '#upload_location' => 'public://bootiq-layout/',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'png jpg jpeg webp gif',
        ],
      ],
      '#preview_image_style' => 'thumbnail',
    ];
  }

  private function submittedImagePath(FormStateInterface $form_state, string $key): string {
    $value = $form_state->getValue($key);
    $fid = is_array($value) ? (int) reset($value) : 0;
    if ($fid > 0 && $file = File::load($fid)) {
      $file->setPermanent();
      $file->save();
      return \Drupal::service('file_url_generator')->generateString($file->getFileUri());
    }
    return trim((string) ($this->configuration[$key] ?? ''));
  }

  private function fileIdFromPublicPath(string $path): int {
    $uri = $this->publicUriFromPath($path);
    if ($uri === '') {
      return 0;
    }
    $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
      'uri' => $uri,
    ]);
    $file = reset($files);
    return $file ? (int) $file->id() : 0;
  }

  private function publicUriFromPath(string $path): string {
    $path = trim($path);
    $prefix = '/sites/default/files/';
    if (!str_starts_with($path, $prefix)) {
      return '';
    }
    return 'public://' . rawurldecode(substr($path, strlen($prefix)));
  }

  private function backgroundImageAttribute(string $url): array {
    $url = trim($url);
    if ($url === '' || !str_starts_with($url, '/')) {
      return [];
    }
    return ['style' => 'background-image: linear-gradient(90deg, rgba(0, 0, 0, .1), rgba(0, 0, 0, 0)), url(' . str_replace(['"', ' '], ['%22', '%20'], $url) . ');'];
  }

}
