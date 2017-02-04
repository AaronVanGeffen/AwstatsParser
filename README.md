AwstatsParser
=============

This package contains a series of classes to help read and merge Awstats data files. It includes an example that can be used to merge Awstats data files via a posix commandline.


## Class description

The package contains three classes:

* `AwstatsFromFile`, used to read existing Awstats statistics from file;
* `AwstatsMerger`, used to merges instances of `AwstatsFile`;
* `AwstatsFile`, which is an abstract class used to generalise the two.


## Merging files

The `awmerge.php` script included in the package may be used to easily merge Awstats data files from a shell.
CLI arguments are the filenames to merge; the merged statistics file will be echoed to the output buffer (e.g. STDOUT) and can be piped.

Let’s give an example. Say you have two Awstats statistics files `awstats062010.aaronweb.net.txt` and `awstats062010.projects.aaronweb.net.txt`, and the AwParse script as `awparse.php`. Assuming php is the binary for php-cli, you’d merge these files by executing:

	$ php awparse.php awstats062010.www.aaronweb.net.txt awstats062010.projects.aaronweb.net.txt > awstats062010.aaronweb.net.txt


## To do

* Import mode: only add unique missing data, or merge all data
* Prefixes and suffixes for URLs on import, i.e. adding /projects as a prefix


## Contributing

Bug reports and pull requests are most welcome through Github. The repository is located at:
https://github.com/AaronVanGeffen/AwstatsParser
