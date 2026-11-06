<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Die Konfiguration erfolgt über Umgebungsvariablen, siehe Config\TrackingConfig.
 */
class PdirMatomoAiTrackingBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
