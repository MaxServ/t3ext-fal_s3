<?php
namespace MaxServ\FalS3\Configuration;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony;

/**
 * Class ConfigurationDefinition
 *
 * @package MaxServ\FalS3\Configuration
 */
class ConfigurationDefinition implements Symfony\Component\Config\Definition\ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new Symfony\Component\Config\Definition\Builder\TreeBuilder();

        $rootNode = $treeBuilder->root('falS3');

        $rootNode
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
                ->scalarNode('bucket')->isRequired()->end()
                ->scalarNode('region')->isRequired()->end()
                ->scalarNode('key')->isRequired()->end()
                ->scalarNode('secret')->isRequired()->end()
                ->scalarNode('title')->isRequired()->end()
                ->scalarNode('basePath')->defaultValue('')->end()
                ->scalarNode('publicBaseUrl')->end()
                ->scalarNode('defaultFolder')->defaultValue('user_upload')->end()
            ->scalarNode('name')->end()
            ->end();

        return $treeBuilder;
    }
}
