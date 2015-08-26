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