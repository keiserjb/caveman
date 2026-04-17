<?php

namespace Drupal\caveman\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to change text into Caveman-speak.
 *
 * @Filter(
 *   id = "caveman",
 *   module = "caveman",
 *   title = @Translation("Caveman filter"),
 *   description = @Translation("Turn text into caveman speak. Short. Strong."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "caveman_display_tip" = 1,
 *   },
 *   weight = -10
 * )
 */
class Caveman extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings['caveman_display_tip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Caveman tip'),
      '#default_value' => $this->settings['caveman_display_tip'] ?? 1,
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $override = \Drupal::config('caveman.settings')->get('caveman_override');
    $is_april_fools = (\Drupal::service('date.formatter')->format(
      \Drupal::time()->getRequestTime(), 'custom', 'md'
    ) == '0401');

    if (!$override && !$is_april_fools) {
      return new FilterProcessResult($text);
    }

    $ignore_tags = 'a|script|style|code|pre';
    $open_tag = '';
    $chunks = preg_split('/(<.+?>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 0; $i < count($chunks); $i++) {
      if (!empty($chunks[$i]) && $chunks[$i][0] != '<') {
        if ($open_tag == '') {
          $chunks[$i] = $this->transform($chunks[$i]);
        }
      }
      else {
        if ($open_tag == '') {
          if (preg_match("`<($ignore_tags)(?:\s|>)`i", $chunks[$i], $matches)) {
            $open_tag = $matches[1];
          }
        }
        else {
          if (preg_match("`</$open_tag>`i", $chunks[$i])) {
            $open_tag = '';
          }
        }
      }
    }

    return new FilterProcessResult(implode('', $chunks));
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if (!empty($this->settings['caveman_display_tip'])) {
      return $this->t('UGH. Caveman speak. Words short. Tribe understand.');
    }
  }

  /**
   * Transform full text through the caveman pipeline.
   */
  protected function transform($text) {
    $sentences = $this->splitSentences($text);

    foreach ($sentences as &$sentence) {
      $sentence = $this->rewriteSentence($sentence);
      $sentence = $this->wordFlavor($sentence);
    }

    $text = implode(' ', $sentences);
    $text = $this->addFlavor($text);
    $text = $this->emphasis($text);

    return trim($text);
  }

  /**
   * Split text into sentences.
   */
  protected function splitSentences($text) {
    return preg_split('/(?<=[.!?])\s+/', $text);
  }

  /**
   * Rewrite sentence structure — full caveman, no mercy.
   */
  protected function rewriteSentence($sentence) {
    $s = strtolower(trim($sentence));

    // Flatten questions: "do you want" → "you want"
    $s = preg_replace('%^(?:do|does|did|can|could|will|would|should|shall|may|might|must)\s+%', '', $s);

    // Phrase normalization.
    $s = preg_replace([
      '%\bi am\b%',
      '%\bi will\b%',
      '%\bi have\b%',
      '%\bgoing to\b%',
      '%\bdo not\b%',
      "%\bdon't\b%",
      "%\bcan't\b%",
      "%\bwon't\b%",
      "%\bwouldn't\b%",
      "%\bcouldn't\b%",
      "%\bshouldn't\b%",
      '%\bbecause\b%',
      '%\balthough\b%',
      '%\bhowever\b%',
      '%\btherefore\b%',
      '%\bconsequently\b%',
      '%\bfurthermore\b%',
      '%\bnevertheless\b%',
      '%\bmoreover\b%',
    ], [
      'me',
      'me gonna',
      'me got',
      'gonna',
      'no',
      'no',
      'no can',
      'no gonna',
      'no want',
      'no can',
      'no should',
      '.',
      '.',
      '.',
      '.',
      '.',
      '.',
      '.',
      '.',
    ], $s);

    // Pronouns.
    $s = preg_replace(['%\bi\b%', '%\bmy\b%', '%\bmine\b%'], ['me', 'me', 'me'], $s);

    // Modal verbs.
    $s = preg_replace([
      '%\bwould\b%',
      '%\bcould\b%',
      '%\bshould\b%',
      '%\bmight\b%',
      '%\bmay\b%',
      '%\bmust\b%',
      '%\bshall\b%',
    ], [
      'want',
      'can',
      'must',
      'maybe',
      'maybe',
      'MUST',
      'gonna',
    ], $s);

    // Verb simplification.
    $s = preg_replace([
      '%\bis\b%',
      '%\bare\b%',
      '%\bwas\b%',
      '%\bwere\b%',
      '%\bhave\b%',
      '%\bhas\b%',
      '%\bhad\b%',
    ], [
      'be',
      'be',
      'be',
      'be',
      'got',
      'got',
      'got',
    ], $s);

    // Remove filler words.
    $s = preg_replace([
      '%\bthe\b%',
      '%\bthat\b%',
      '%\bvery\b%',
      '%\breally\b%',
      '%\bjust\b%',
      '%\bactually\b%',
      '%\bbasically\b%',
      '%\bsimply\b%',
      '%\bquite\b%',
      '%\brather\b%',
      '%\bsomewhat\b%',
    ], '', $s);

    // Break at commas and connectives.
    $s = preg_replace(['/,\s*/', '%\band then\b%', '%\band also\b%'], '. ', $s);

    // Drop prepositions.
    $s = preg_replace([
      '%\binto\b%',
      '%\bonto\b%',
      '%\bupon\b%',
      '%\bwithin\b%',
      '%\btoward\b%',
      '%\btowards\b%',
      '%\babout\b%',
      '%\baround\b%',
      '%\bbetween\b%',
      '%\bamong\b%',
      '%\bagainst\b%',
      '%\bduring\b%',
    ], '', $s);

    // Cleanup.
    $s = preg_replace('/\s+/', ' ', $s);
    $s = trim($s);

    // Force caveman rhythm.
    $parts = preg_split('/\./', $s);
    $parts = array_filter(array_map('trim', $parts));

    return implode('. ', $parts) . '.';
  }

  /**
   * Caveman dictionary — flavor layer.
   */
  protected function wordFlavor($text) {
    $map = [
      // Transport — plural before singular.
      '%\bairplanes\b%i'    => 'big flying beasts',
      '%\bairplane\b%i'     => 'big flying beast',
      '%\bplanes\b%i'       => 'flying beasts',
      '%\bplane\b%i'        => 'flying beast',
      '%\bsailplanes\b%i'   => 'wind-riding flying beasts',
      '%\bsailplane\b%i'    => 'wind-riding flying beast',
      '%\bgliders\b%i'      => 'sky breath riders',
      '%\bglider\b%i'       => 'sky breath rider',
      '%\bcars\b%i'         => 'fast beasts',
      '%\bcar\b%i'          => 'fast beast',
      '%\btrucks\b%i'       => 'big fast beasts',
      '%\btruck\b%i'        => 'big fast beast',
      '%\bboats\b%i'        => 'water beasts',
      '%\bboat\b%i'         => 'water beast',
      '%\bbikes\b%i'        => 'two wheel beasts',
      '%\bbike\b%i'         => 'two wheel beast',
      '%\btrains\b%i'       => 'loud ground beasts',
      '%\btrain\b%i'        => 'loud ground beast',
      // Tech.
      '%\bcomputers\b%i'    => 'magic rocks',
      '%\bcomputer\b%i'     => 'magic rock',
      '%\bphones\b%i'       => 'talk rocks',
      '%\bphone\b%i'        => 'talk rock',
      '%\bemails\b%i'       => 'message marks',
      '%\bemail\b%i'        => 'message mark',
      '%\bvideos\b%i'       => 'moving pictures',
      '%\bvideo\b%i'        => 'moving picture',
      '%\binternet\b%i'     => 'big web',
      '%\bwebsites\b%i'     => 'cave walls',
      '%\bwebsite\b%i'      => 'cave wall',
      '%\bapps\b%i'         => 'magic tools',
      '%\bapp\b%i'          => 'magic tool',
      '%\bsoftware\b%i'     => 'magic tool',
      '%\bdatabases\b%i'    => 'number caves',
      '%\bdatabase\b%i'     => 'number cave',
      '%\bservers\b%i'      => 'big magic rocks',
      '%\bserver\b%i'       => 'big magic rock',
      '%\bpasswords\b%i'    => 'secret grunts',
      '%\bpassword\b%i'     => 'secret grunt',
      // Abstract concepts — irregular plurals first.
      '%\bstrategies\b%i'   => 'hunt plans',
      '%\bstrategy\b%i'     => 'hunt plan',
      '%\bprocesses\b%i'    => 'ways of doing',
      '%\bprocess\b%i'      => 'way of doing',
      '%\bideas\b%i'        => 'thoughts',
      '%\bidea\b%i'         => 'thought',
      '%\bprojects\b%i'     => 'build things',
      '%\bproject\b%i'      => 'build thing',
      '%\bsystems\b%i'      => 'many parts things',
      '%\bsystem\b%i'       => 'many parts thing',
      '%\bdata\b%i'         => 'many numbers',
      '%\binformation\b%i'  => 'knowing',
      '%\bknowledge\b%i'    => 'cave wisdom',
      '%\bgoals\b%i'        => 'want things',
      '%\bgoal\b%i'         => 'want thing',
      '%\bproblems\b%i'     => 'bad things',
      '%\bproblem\b%i'      => 'bad thing',
      '%\bsolutions\b%i'    => 'fix things',
      '%\bsolution\b%i'     => 'fix thing',
      '%\bmeetings\b%i'     => 'tribe gathers',
      '%\bmeeting\b%i'      => 'tribe gather',
      '%\bpresentations\b%i' => 'show tribes',
      '%\bpresentation\b%i' => 'show tribe',
      '%\breports\b%i'      => 'tribe mark piles',
      '%\breport\b%i'       => 'tell tribe marks',
      '%\bdocuments\b%i'    => 'marks on rocks',
      '%\bdocument\b%i'     => 'marks on rock',
      '%\bschedules\b%i'    => 'when do things',
      '%\bschedule\b%i'     => 'when do thing',
      '%\bdeadlines\b%i'    => 'MUST do nows',
      '%\bdeadline\b%i'     => 'MUST do now',
      '%\bbudgets\b%i'      => 'shiny rock counts',
      '%\bbudget\b%i'       => 'shiny rock count',
      '%\bwind\b%i'         => 'sky breath',
      // People — irregular plurals first.
      '%\bpeople\b%i'       => 'tribe',
      '%\bpersons\b%i'      => 'tribe ones',
      '%\bperson\b%i'       => 'one tribe',
      '%\bfriends\b%i'      => 'cave-friends',
      '%\bfriend\b%i'       => 'cave-friend',
      '%\bleaders\b%i'      => 'chiefs',
      '%\bleader\b%i'       => 'chief',
      '%\bbosses\b%i'       => 'big chiefs',
      '%\bboss\b%i'         => 'big chief',
      '%\bmanagers\b%i'     => 'big chiefs',
      '%\bmanager\b%i'      => 'big chief',
      '%\bteams\b%i'        => 'tribe groups',
      '%\bteam\b%i'         => 'tribe group',
      '%\bchildren\b%i'     => 'small ones',
      '%\bkids\b%i'         => 'small ones',
      '%\bkid\b%i'          => 'small one',
      '%\bwomen\b%i'        => 'cave women',
      '%\bwoman\b%i'        => 'cave woman',
      '%\bmen\b%i'          => 'cave men',
      '%\bman\b%i'          => 'cave man',
      '%\bcustomers\b%i'    => 'trade people',
      '%\bcustomer\b%i'     => 'trade person',
      '%\busers\b%i'        => 'tool people',
      '%\buser\b%i'         => 'tool person',
      // Places — irregular plurals first.
      '%\boffices\b%i'      => 'work caves',
      '%\boffice\b%i'       => 'work cave',
      '%\bcities\b%i'       => 'big tribe places',
      '%\bcity\b%i'         => 'big tribe place',
      '%\btowns\b%i'        => 'tribe places',
      '%\btown\b%i'         => 'tribe place',
      '%\bcountries\b%i'    => 'big lands',
      '%\bcountry\b%i'      => 'big land',
      '%\bworld\b%i'        => 'all land',
      '%\bschools\b%i'      => 'learning caves',
      '%\bschool\b%i'       => 'learning cave',
      '%\bhospitals\b%i'    => 'healing caves',
      '%\bhospital\b%i'     => 'healing cave',
      '%\bstores\b%i'       => 'trade caves',
      '%\bstore\b%i'        => 'trade cave',
      '%\brestaurants\b%i'  => 'food caves',
      '%\brestaurant\b%i'   => 'food cave',
      '%\bhouses\b%i'       => 'caves',
      '%\bhouse\b%i'        => 'cave',
      '%\bhome\b%i'         => 'cave',
      // Resources.
      '%\bmoney\b%i'        => 'shiny rocks',
      '%\bfood\b%i'         => 'FOOD',
      '%\bfire\b%i'         => 'FIRE',
      '%\bwater\b%i'        => 'WATER',
      '%\btools\b%i'        => 'make things',
      '%\btool\b%i'         => 'make thing',
      '%\bweapons\b%i'      => 'hurt sticks',
      '%\bweapon\b%i'       => 'hurt stick',
      // Qualities.
      '%\bgood\b%i'         => 'GOOD',
      '%\bbad\b%i'          => 'BAD',
      '%\bstrong\b%i'       => 'STRONG',
      '%\bfast\b%i'         => 'quick quick',
      '%\bslow\b%i'         => 'slow slow',
      '%\bbig\b%i'          => 'BIG',
      '%\bsmall\b%i'        => 'tiny',
      '%\bimportant\b%i'    => 'BIG thing',
      '%\bdifficult\b%i'    => 'hard thing',
      '%\beasy\b%i'         => 'simple thing',
    ];

    $text = preg_replace(array_keys($map), array_values($map), $text);

    if (rand(0, 3) === 0) {
      $text = 'UGH. ' . $text;
    }

    return $text;
  }

  /**
   * Add random grunt flavor at end of text.
   */
  protected function addFlavor($text) {
    if (rand(0, 4) === 0) {
      $text .= ' UNGA!';
    }
    return $text;
  }

  /**
   * Repeat first chunk for emphasis.
   */
  protected function emphasis($text) {
    if (rand(0, 2) === 0) {
      $parts = explode('.', $text);
      if (!empty($parts[0])) {
        $text .= '. ' . trim($parts[0]) . '.';
      }
    }
    return $text;
  }

}
