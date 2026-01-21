.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _examples:

Examples
========

Hetzner
-------

.. code-block:: php

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['storage1'] = array(
		'basePath' => '/',
		'bucket' => 's3://bucket1',
		'endpoint' => 'https://nbg1.your-objectstorage.com',
		'excludedFolders' => [],
		'key' => '{$IAM_KEY}',
		'publicBaseUrl' => 'https://bucket1.nbg1.your-objectstorage.com',
		'region' => 'eu-central',
		'secret' => '{$IAM_SECRET}',
		'title' => 'S3 Bucket 1',
	);

> the bucket needs to be configured as public, if media should be accessible directly from the frontend.
> the source of an image would look like this: `https://bucket1.nbg1.your-objectstorage.com/example.png`
