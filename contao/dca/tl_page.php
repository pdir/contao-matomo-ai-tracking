<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;

$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'matomoAiEnable';
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['matomoAiEnable'] = 'matomoAiSiteId,matomoAiTrackingUrl';

$GLOBALS['TL_DCA']['tl_page']['fields']['matomoAiEnable'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['matomoAiSiteId'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'maxlength' => 10, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['matomoAiTrackingUrl'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'save_callback' => [
        static function (mixed $value): string {
            $value = trim((string) $value);

            if ('' !== $value && !preg_match('#^https?://[^/\s]+#i', $value)) {
                throw new RuntimeException($GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlInvalid'] ?? 'Invalid URL');
            }

            // Das Feld steht in der Subpalette von matomoAiEnable und wird nur bei aktiviertem Tracking gespeichert.
            // Ohne eigene URL und ohne MATOMO_TRACKING_URL würde der Startpunkt stillschweigend nicht getrackt.
            if ('' === $value && null === TrackingConfig::fromEnvironment()->trackingUrl) {
                throw new RuntimeException($GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlMissing'] ?? 'Missing Matomo URL');
            }

            return $value;
        },
    ],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

PaletteManipulator::create()
    ->addLegend('matomo_ai_legend', 'global_legend', PaletteManipulator::POSITION_AFTER, true)
    ->addField('matomoAiEnable', 'matomo_ai_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page')
;
