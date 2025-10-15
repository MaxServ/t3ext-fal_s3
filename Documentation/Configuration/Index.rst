.. _configuration:

=============
Configuration
=============

Add the following snippet to your :code:`config/system/settings.php` and adjust the values to meet your setup

.. code-block:: php

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['contentStorage'] = [
        'basePath' => '/{$FOLDER_PREFIX}/'
        'bucket' => 's3://{$BUCKET_NAME}',
        'excludedFolders' => ['Secret/Stash', 'SomethingElse'],
        'key' => '{$IAM_KEY}',
        'publicBaseUrl' => 'https://{$CF_DISTRIBUTION_ID}.cloudfront.net',
        'region' => 'eu-west-1',
        'secret' => '{$IAM_SECRET}',
        'title' => 'TYPO3 content storage',
    ];

This adds a configuration fo the driver that is reference using **contentStorage**, this unique key is the only value
stored in the `File Storage` DB record so this record doesn't need to be changed for each environment.

`basePath`
    Used as a prefix (folder) to store files.

`bucket`
    The name of your S3 bucket.

`excludedFolders`
    An array of folders that are present on S3, but should not be made available to TYPO3.

`key`
    The Access Key ID provided by AWS (see the IAM console).

`publicBaseUrl`
    The URL of a CDN that will be used instead of :code:`https://{$BUCKET_NAME}.s3.amazonaws.com`.

`region`
    Region to connect to. See http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region for a list of available regions.

`secret`
    Secret Access Key provided by AWS (see the IAM console).

`title`
    The readable title you see as selectable option when editing a File Storage.
