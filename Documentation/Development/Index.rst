.. _development:

===========
Development
===========

The extension comes with a ddev setup, thanks to Armin Vieweg for this
`example DDEV setup <https://github.com/a-r-m-i-n/ddev-for-typo3-extensions>`__
for TYPO3 extensions.

Beside a TYPO3 13 and 14 installation, it also contains a Minio docker container to test with a real S3 bucket.

Setting up the development environments
=======================================

Running `ddev start` will set up the basics for the development environments.

After this you'll have to execute a few commands to get the TYPO3 installations up and running:

.. code-block:: bash

    ddev install-all

This command will install both the TYPO3 v13 and v14 installations with the `fal_s3` extension and a small
sitepackage with the configuration for the Minio S3 bucket.

If you only want to one of the TYPO3 versions, you can run either of the following commands:

.. code-block:: bash

    ddev install-v13
    ddev install-v14


Creating the Minio S3 buckets
=============================

Through the `ddev mc` command you can create the Minio S3 buckets that are used in the TYPO3 installations.

The buckets used in the configuration are `typo3-13` and `typo3-14`.

.. code-block:: bash

    ddev mc mb minio/typo3-13
    ddev mc mb minio/typo3-14

By default, the buckets are created with the `private` policy, which means that the files are not publicly accessible.

These additional commands can be used to be able to view the files in the backend and frontend:

.. code-block:: bash

    ddev mc anonymous set download minio/typo3-13
    ddev mc anonymous set download minio/typo3-14

After creating the buckets, you can configure them in the backend (see :ref:`administration`).
