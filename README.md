# TYPO3 extension `fal_s3`

FAL driver for S3

Connect FAL/TYPO3 to any of the configured S3 buckets with a few clicks. File based configuration allows specific buckets depending on the context of your application.

The main goal of this extension is to get some insights in the whole FAL driver setup.

Since most methods are implemented using the S3 stream wrapper from the AWS SDK, you should be able to get a fairly good
idea of what's going on when you take a look at the code.

Thanks to the availability of a stream wrapper we were able to use the standard toolkit provided by PHP saving us a lot
of time while developing this driver,

All we needed to care about was the concept of remote file storage and working with FAL.

## Documentation

* [Introduction](/Documentation/Introduction/Index.rst)
* [Administrator Manual](/Documentation/AdministratorManual/Index.rst)
