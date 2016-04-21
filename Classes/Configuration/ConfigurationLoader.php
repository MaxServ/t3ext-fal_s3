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
 * Class ConfigurationLoader
 *
 * @package MaxServ\FalS3\Configuration
 */
class ConfigurationLoader extends Symfony\Component\Config\Loader\FileLoader {

	/**
	 * Loads a resource.
	 *
	 * @param mixed $resource The resource
	 * @param string|null $type The resource type or null if unknown
	 *
	 * @return array
	 * @throws \Exception If something went wrong
	 */
	public function load($resource, $type = null) {
		return Symfony\Component\Yaml\Yaml::parse(file_get_contents($resource));
	}

	/**
	 * Returns whether this class supports the given resource.
	 *
	 * @param mixed $resource A resource
	 * @param string|null $type The resource type or null if unknown
	 *
	 * @return bool True if this class supports the given resource, false otherwise
	 */
	public function supports($resource, $type = null) {
		return is_string($resource) && strpos($resource, 'FalS3.yaml') !== FALSE;
	}

}