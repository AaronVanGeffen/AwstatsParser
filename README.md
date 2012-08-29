AwstatsParser
=============

This script is an Awstats data file parser that can be used to merge or generate Awstats data files via a posix commandline.

The PHP script contains three classes, to be used via PHP-CLI. The abstract class `AwstatsFile` is used as a generalizer for both the `AwstatsFromFile` class (reads an existing Awstats statistics file) and the `AwstatsMerger` class (merges instances of `AwstatsFile`). CLI arguments are the filenames to merge; the merged statistics file will be echoed to the output buffer, effectively STDOUT, and can be piped.

Let’s give an example. Say you have two Awstats statistics files `awstats062010.aaronweb.net.txt` and `awstats062010.projects.aaronweb.net.txt`, and the AwParse script as `awparse.php`. Assuming php is the binary for php-cli, you’d merge these files by executing:

	$ php awparse.php awstats062010.www.aaronweb.net.txt awstats062010.projects.aaronweb.net.txt > awstats062010.aaronweb.net.txt
