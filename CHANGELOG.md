# AwstatsParser changelog

## version 1.4.1 (2020-01-20)
* Change braces to brackets to address a PHP 7.4 deprecation warning.

## version 1.4 (2017-05-04)
* Read section names until the first space. Fixes edge case reading the OS section.

## version 1.3 (2017-02-04)
* Repackaged the classes for easy use with autoloaders and composer.
* Add getSection method to AwstatsFile class.

## version 1.2 (2012-08-30)
* Merged patches by Dave Dykstra:
  * Do a better job at merging FirstTime, LastTime, LastUpdate and TotalVisits.
  * Fixed bug that caused some data to be merged incorrectly.
  * Treat 3rd and 4th indexes as dates when merging VISITOR and EXTRA_1 sections.

## version 1.1 (2012-08-29)
* Released on GitHub.
* Removed unnecessary call-time pass-by-references;
* Added newlines to error messages;

## version 1.0 (2010-06-19)
* Initial release.
