<?php

declare(strict_types=1);

namespace Huluti\AltchaBundle\DependencyInjection;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\UX\StimulusBundle\StimulusBundle;

class HulutiAltchaExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerAceEditorParameters($config, $container);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yml');
        if ($container->getParameter('huluti_altcha.use_sentinel')) {
            $loader->load('services_sentinel.yml');
        }
    }

    /**
     * Register parameters for the DI.
     *
     * @param array<string, bool|float|int|string|null> $config
     */
    private function registerAceEditorParameters(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('huluti_altcha.enable', $config['enable']);
        $container->setParameter('huluti_altcha.floating', $config['floating']);
        $container->setParameter('huluti_altcha.hmacKey', $config['hmacKey']);
        $container->setParameter('huluti_altcha.hide_logo', $config['hide_logo']);
        $container->setParameter('huluti_altcha.hide_footer', $config['hide_footer']);
        $container->setParameter('huluti_altcha.js_path', $config['altcha_js_path']);

        $assetMapperInstalled = interface_exists(AssetMapperInterface::class);
        $container->setParameter('huluti_altcha.use_asset_mapper', $assetMapperInstalled);

        $useStimulus = $config['use_stimulus'];
        if (null === $useStimulus) {
            $bundles = $container->getParameter('kernel.bundles');
            assert(is_array($bundles));
            $useStimulus = in_array(StimulusBundle::class, $bundles, true) && $assetMapperInstalled;
        }

        $container->setParameter('huluti_altcha.use_stimulus', $useStimulus);

        if ($useSentinel = array_key_exists('base_url', $config['sentinel'])) {
            $container->setParameter('huluti_altcha.sentinel.base_url', $config['sentinel']['base_url']);
            $container->setParameter('huluti_altcha.sentinel.api_key', $config['sentinel']['api_key']);
            /** @see https://altcha.org/docs/v2/widget-integration/ */
            $container->setParameter('huluti_altcha.sentinel.challenge_url', sprintf(
                '%s/v1/challenge?apiKey=%s',
                $config['sentinel']['base_url'],
                $config['sentinel']['api_key']
            ));
            /** @see https://altcha.org/docs/v2/server-integration/ */
            $container->setParameter('huluti_altcha.sentinel.verify_signature_url', sprintf(
                '%s/v1/verify/signature',
                $config['sentinel']['base_url']
            ));
        }
        $container->setParameter('huluti_altcha.use_sentinel', $useSentinel);
    }

    /**
     * @see https://symfony.com/doc/current/frontend/create_ux_bundle.html
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($this->isAssetMapperAvailable($container)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/../../assets/controllers' => 'huluti/altcha-bundle',
                    ],
                ],
            ]);
        }
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        // check that FrameworkBundle 6.3 or higher is installed
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        return is_file($bundlesMetadata['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }
}
