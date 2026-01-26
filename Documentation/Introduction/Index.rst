.. _introduction:

================
What does it do?
================

This extension implements a FAL driver to allow TYPO3 CMS to store files in an S3 bucket. Configuration is file based and can be adjusted per environment so you can use separate file buckets for your development and production environments.

.. warning::

    **For integration of this extension must set to Composer Mode.**

    Using Composer the latest stable version of the AWS SDK is downloaded when installing this driver.
