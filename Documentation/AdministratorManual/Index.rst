.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _admin-manual:

Administrator Manual
====================

Installation
------------

As stated in the previous chapter, this extension must be installed using Composer to be able to use the AWS SDK. From that point on configuration is pretty straight-forward.

Head over to your CLI and run the following command

.. code-block:: bash

	composer require maxserv/fal_s3

This wil take care of installing all dependencies like the AWS SDK and this extension. After installing it's necessary to flush the system caches.

Configuration
-------------

Add the following snippet to your :code:`AdditionalConfiguration.php` and adjust the values to meet your setup

.. code-block:: php

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['contentStorage'] = array(
		'basePath' => '/{$FOLDER_PREFIX}/'
		'bucket' => 's3://{$BUCKET_NAME}',
		'key' => '{$IAM_KEY}',
		'publicBaseUrl' => 'https://{$CF_DISTRIBUTION_ID}.cloudfront.net',
		'region' => 'eu-west-1',
		'secret' => '{$IAM_SECRET}',
		'title' => 'TYPO3 content storage',
	);

This adds a configuration fo the driver that is reference using **contentStorage**, this unique key is the only value stored in the `File Storage` DB record so this record doesn't need to be changed for each environment.

	- `basePath` is used as a prefix (folder) to store files.
	- `bucket` is the name of your S3 bucket.
	- `key` the Access Key ID provided by AWS (see the IAM console).
	- `publicBaseUrl` is the URL of a CDN that will be used instead of :code:`https://{$BUCKET_NAME}.s3.amazonaws.com`. In this example CloudFront is used because of the simple integration with S3.
	- `region` region to connect to. See http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region for a list of available regions.
	- `secret` Secret Access Key provided by AWS (see the IAM console).
	- `title` The readable title you see as selectable option when editing a File Storage.

Selecting the configuration to use
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Once the configuration is added to :code:`AdditionalConfiguration.php` the preferred storage can be selected within the backend of TYPO3 CMS.

.. figure:: ../Images/AdministratorManual/FileStorageConfiguration.png
	:width: 500px
	:alt: File Storage configuration

Go to the `List` module and select the root page (pid:0). Click the storage you want to edit and select the `Configuration` tab. Choose `S3 driver for FAL` as the driver for this storage. Next choose the storage configuration that should be used.
