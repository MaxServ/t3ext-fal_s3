<?php
namespace MaxServ\FalS3\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Arno Schoon <arno@maxserv.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Symfony;

/**
 * Class ConfigurationDefinition
 *
 * @package MaxServ\FalS3\Configuration
 */
class ConfigurationDefinition implements Symfony\Component\Config\Definition\ConfigurationInterface {

	/**
	 * Generates the configuration tree builder.
	 *
	 * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
	 */
	public function getConfigTreeBuilder() {
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
				->scalarNode('basePath')->end()
				->scalarNode('publicBaseUrl')->end()
			->scalarNode('name')->end()
			->end();

		return $treeBuilder;
	}
}