<?php

namespace Drupal\washuas_anchor_link\Plugin\Linkit\Matcher;

use Drupal\linkit\MatcherBase;
use Drupal\linkit\Suggestion\DescriptionSuggestion;
use Drupal\linkit\Suggestion\SuggestionCollection;

/**
 * Provides specific linkit matchers for Anchor links.
 *
 * @Matcher(
 *   id = "ckeditor_anchor",
 *   label = @Translation("CKEditor Anchor link"),
 *   provider = "washuas_anchor_link"
 * )
 */
class CKEditorAnchorLinkMatcher extends MatcherBase {

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = new SuggestionCollection();

    $string = ltrim($string, '#');

    $suggestion = new DescriptionSuggestion();
    $suggestion->setLabel($this->t('#@anchor', ['@anchor' => $string]))
      ->setPath('#' . $string)
      ->setGroup($this->t('Anchor links (within the same page)'));

    $suggestions->addSuggestion($suggestion);

    return $suggestions;
  }

}
