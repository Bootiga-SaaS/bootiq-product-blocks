<?php

namespace Drupal\bootiq_product_blocks\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Provides the editorial lookbook section used by the Bootiq frontpage.
 */
#[Block(
  id: 'bootiq_the_look',
  admin_label: new TranslatableMarkup('The look'),
  category: new TranslatableMarkup('Bootiq')
)]
final class TheLookBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return [
      'kicker' => (string) $this->t('The look'),
      'title' => (string) $this->t('Build a complete collection, not just a catalogue.'),
      'body' => (string) $this->t('Use visual categories, featured products and editorial sections to guide shoppers from inspiration to checkout.'),
      'image_url' => '/themes/contrib/bootiq/images/slide-2.jpg',
      'button_label' => (string) $this->t('View collection'),
      'button_url' => '/products',
    ] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $form['kicker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kicker'),
      '#default_value' => $this->configuration['kicker'] ?? '',
      '#maxlength' => 120,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->configuration['title'] ?? '',
      '#maxlength' => 180,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->configuration['body'] ?? '',
      '#rows' => 3,
    ];
    $form['image_url'] = $this->imageUploadElement();
    $form['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $this->configuration['button_label'] ?? '',
      '#maxlength' => 120,
    ];
    $form['button_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button URL'),
      '#default_value' => $this->configuration['button_url'] ?? '/products',
      '#description' => $this->t('Use an internal path such as /products.'),
      '#maxlength' => 180,
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    foreach (['kicker', 'title', 'body', 'button_label', 'button_url'] as $key) {
      $this->configuration[$key] = trim((string) $form_state->getValue($key));
    }
    $this->configuration['image_url'] = $this->submittedImagePath($form_state);
  }

  public function build(): array {
    $button_url = $this->configuration['button_url'] ?: '/products';
    $image_attributes = $this->backgroundImageAttribute($this->configuration['image_url'] ?? '');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bp-lookbook']],
      'copy' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['bp-lookbook__copy']],
        'kicker' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->configuration['kicker'],
          '#attributes' => ['class' => ['bp-kicker']],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->configuration['title'],
        ],
        'body' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->configuration['body'],
        ],
        'button' => [
          '#type' => 'link',
          '#title' => $this->configuration['button_label'],
          '#url' => Url::fromUserInput($button_url),
          '#attributes' => ['class' => ['btn', 'btn-primary']],
        ],
      ],
      'image' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['bp-lookbook__image'],
          'aria-hidden' => 'true',
        ] + $image_attributes,
      ],
      '#cache' => [
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

  private function imageUploadElement(): array {
    $default_fid = $this->fileIdFromPublicPath($this->configuration['image_url'] ?? '');
    return [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
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

  private function submittedImagePath(FormStateInterface $form_state): string {
    $value = $form_state->getValue('image_url');
    $fid = is_array($value) ? (int) reset($value) : 0;
    if ($fid > 0 && $file = File::load($fid)) {
      $file->setPermanent();
      $file->save();
      return \Drupal::service('file_url_generator')->generateString($file->getFileUri());
    }
    return trim((string) ($this->configuration['image_url'] ?? ''));
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
    return ['style' => 'background-image: url(' . str_replace(['"', ' '], ['%22', '%20'], $url) . ');'];
  }

}
