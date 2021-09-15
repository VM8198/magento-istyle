<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_Checkout
 */


namespace Amasty\Checkout\Setup\Operation;

use Amasty\Base\Model\Serializer;
use Amasty\Checkout\Model\Config;
use Amasty\Checkout\Setup\Operation\Utils\ConfigLoader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Cache\Type\Config as CacheTypeConfig;

class LayoutBuilderDataMigration
{

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ConfigLoader
     */
    private $configLoader;

    /**
     * @var Manager
     */
    private $cacheManager;

    public function __construct(
        Serializer $serializer,
        ConfigLoader $configLoader,
        Manager $cacheManager
    ) {
        $this->serializer = $serializer;
        $this->configLoader = $configLoader;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function execute(ModuleDataSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $configTable = $setup->getTable('core_config_data');

        $scopedConfig = $this->configLoader->loadConfig(
            $connection,
            $configTable
        );

        foreach ($scopedConfig as $scopeKey => $scopeValues) {
            list($scope, $scopeId) = explode('_', $scopeKey);
            list($layoutBuilderConfig, $frontendConfig) = $this->getNewConfig($scopeValues, $scope, $scopeId);

            $layoutBuilderConfigPath = Config::PATH_PREFIX
                . Config::LAYOUT_BUILDER_BLOCK
                . Config::FIELD_LAYOUT_BUILDER_CONFIG;

            $frontendConfigPath = Config::PATH_PREFIX
                . Config::LAYOUT_BUILDER_BLOCK
                . Config::FIELD_FRONTEND_LAYOUT_CONFIG;

            $connection->insert(
                $configTable,
                [
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'path' => $layoutBuilderConfigPath,
                    'value' => $this->serializer->serialize($layoutBuilderConfig),
                ]
            );

            $connection->insert(
                $configTable,
                [
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'path' => $frontendConfigPath,
                    'value' => $this->serializer->serialize($frontendConfig),
                ]
            );
        }

        $this->cacheManager->clean([CacheTypeConfig::TYPE_IDENTIFIER]);
    }

    /**
     * @param array $config
     * @param string $scope
     * @param string $scopeId
     * @return array
     */
    private function getNewConfig(array $config, $scope, $scopeId)
    {
        $design = $config[Config::FIELD_CHECKOUT_DESIGN];
        $design = $design == "1" ? 'modern' : 'classic';

        $layout = $config[Config::FIELD_CHECKOUT_LAYOUT];
        if ($design == 'modern') {
            $layout = $config[Config::FIELD_CHECKOUT_LAYOUT_MODERN];
        }

        $layoutBuilderConfig = $this->getDefaultPreset();
        if (!isset($layoutBuilderConfig[$design])) {
            $design = 'classic';
        }

        if (!isset($layoutBuilderConfig[$design][$layout])) {
            $layout = '2columns';
        }

        $presetForCurrentConfig = &$layoutBuilderConfig[$design][$layout];

        // summary always on bottom in this case
        if ($design == 'modern' && $layout == '2columns') {
            $config['blockConfig']['summary']['sort_order'] = 999999999;
        }

        $configWithXAndY = [];
        uasort($config['blockConfig'], function ($itemA, $itemB) {
            return $itemA['sort_order'] <=> $itemB['sort_order'];
        });

        $layoutTemplate = $this->getLayoutTemplateFromPreset($presetForCurrentConfig['layout']);
        foreach ($config['blockConfig'] as $blockName => $blockConfig) {
            $configWithXAndY[$blockName] = array_shift($layoutTemplate);
            $configWithXAndY[$blockName]['title'] = $blockConfig['value'];
        }

        $frontendConfig = [];
        foreach ($presetForCurrentConfig['layout'] as &$blockData) {
            if (!isset($configWithXAndY[$blockData['i']])) {
                continue;
            }

            $configForCurrentBlock = $configWithXAndY[$blockData['i']];

            $blockData['x'] = $configForCurrentBlock['x'];
            $blockData['y'] = $configForCurrentBlock['y'];

            $frontendConfig[$blockData['x']][$blockData['y']] = [
                'name' => $blockData['i'],
                'title' =>  $configForCurrentBlock['title']
            ];
        }

        $frontendConfig = $this->prepareFrontendConfig($frontendConfig);

        return [$layoutBuilderConfig, $frontendConfig];
    }

    /**
     * @param array $frontendConfig
     * @return array
     */
    private function prepareFrontendConfig($frontendConfig)
    {
        ksort($frontendConfig);
        $frontendConfig = array_values($frontendConfig);
        foreach ($frontendConfig as &$item) {
            ksort($item);
            $item = array_values($item);
        }

        return $frontendConfig;
    }

    /**
     * @param array $presetLayout
     * @return array
     */
    private function getLayoutTemplateFromPreset($presetLayout)
    {
        $layoutTemplate = [];
        foreach ($presetLayout as $item) {
            $layoutTemplate[] = [
                'x' => $item['x'],
                'y' => $item['y']
            ];
        }

        return $layoutTemplate;
    }

    /**
     * @return array
     */
    private function getDefaultPreset()
    {
        return [
            'classic' => [
                '2columns' => [
                    'frontendColumns' => 2,
                    'columnsWidth' => [0 => 1, 1 => 1,],
                    'axis' => 'both',
                    'cols' => 2,
                    'layout' => [
                        0 => [
                            'i' => 'shipping_address',
                            'x' => 0,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        1 => [
                            'i' => 'shipping_method',
                            'x' => 0,
                            'y' => 1,
                            'w' => 1,
                            'h' => 1,
                        ],
                        2 => [
                            'i' => 'delivery',
                            'x' => 0,
                            'y' => 2,
                            'w' => 1,
                            'h' => 1,
                        ],
                        3 => [
                            'i' => 'payment_method',
                            'x' => 1,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        4 => [
                            'i' => 'summary',
                            'x' => 1,
                            'y' => 1,
                            'w' => 1,
                            'h' => 1,
                        ],
                    ],
                ],
                '3columns' => [
                    'frontendColumns' => 3,
                    'columnsWidth' => [0 => 1, 1 => 1, 2 => 1,],
                    'axis' => 'both',
                    'cols' => 3,
                    'layout' => [
                        0 => [
                            'i' => 'shipping_address',
                            'x' => 0,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        1 => [
                            'i' => 'shipping_method',
                            'x' => 1,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        2 => [
                            'i' => 'delivery',
                            'x' => 1,
                            'y' => 2,
                            'w' => 1,
                            'h' => 1,
                        ],
                        3 => [
                            'i' => 'payment_method',
                            'x' => 1,
                            'y' => 3,
                            'w' => 1,
                            'h' => 1,
                        ],
                        4 => [
                            'i' => 'summary',
                            'x' => 2,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                    ],
                ],
            ],
            'modern' => [
                '1column' => [
                    'frontendColumns' => 1,
                    'columnsWidth' => [0 => 1,],
                    'axis' => 'both',
                    'cols' => 1,
                    'layout' => [
                        0 => [
                            'i' => 'shipping_address',
                            'x' => 0,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        1 => [
                            'i' => 'shipping_method',
                            'x' => 0,
                            'y' => 1,
                            'w' => 1,
                            'h' => 1,
                        ],
                        2 => [
                            'i' => 'delivery',
                            'x' => 0,
                            'y' => 2,
                            'w' => 1,
                            'h' => 1,
                        ],
                        3 => [
                            'i' => 'payment_method',
                            'x' => 0,
                            'y' => 3,
                            'w' => 1,
                            'h' => 1,
                        ],
                        4 => [
                            'i' => 'summary',
                            'x' => 0,
                            'y' => 4,
                            'w' => 1,
                            'h' => 1,
                        ],
                    ],
                ],
                '2columns' => [
                    'frontendColumns' => 2,
                    'columnsWidth' => [0 => 2, 1 => 1,],
                    'axis' => 'y',
                    'cols' => 3,
                    'layout' => [
                        0 => [
                            'i' => 'shipping_address',
                            'x' => 0,
                            'y' => 0,
                            'w' => 2,
                            'h' => 1,
                        ],
                        1 => [
                            'i' => 'shipping_method',
                            'x' => 0,
                            'y' => 1,
                            'w' => 2,
                            'h' => 1,
                        ],
                        2 => [
                            'i' => 'delivery',
                            'x' => 0,
                            'y' => 2,
                            'w' => 2,
                            'h' => 1,
                        ],
                        3 => [
                            'i' => 'payment_method',
                            'x' => 0,
                            'y' => 3,
                            'w' => 2,
                            'h' => 1,
                        ],
                        4 => [
                            'i' => 'summary',
                            'x' => 2,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                            'static' => true,
                            'axis' => 'x',
                        ],
                    ],
                ],
                '3columns' => [
                    'frontendColumns' => 3,
                    'columnsWidth' => [0 => 1, 1 => 1, 2 => 1,],
                    'axis' => 'both',
                    'cols' => 3,
                    'layout' => [
                        0 => [
                            'i' => 'shipping_address',
                            'x' => 0,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        1 => [
                            'i' => 'shipping_method',
                            'x' => 1,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                        2 => [
                            'i' => 'delivery',
                            'x' => 1,
                            'y' => 2,
                            'w' => 1,
                            'h' => 1,
                        ],
                        3 => [
                            'i' => 'payment_method',
                            'x' => 1,
                            'y' => 3,
                            'w' => 1,
                            'h' => 1,
                        ],
                        4 => [
                            'i' => 'summary',
                            'x' => 2,
                            'y' => 0,
                            'w' => 1,
                            'h' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }
}
