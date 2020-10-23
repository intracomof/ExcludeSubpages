# ExcludeSubpages
MediaWiki extension that allows to hide subpages from the list on Special:AllPages

# Installation
Clone this repository into extensions folder of your wiki
Add to your LocalSettings.php a following line: 

wfLoadExtension( 'ExcludeSubpages' );

# Configure default behavior
By default subpages will be filtered out from Special:AllPages list 

You can set `$wgHideSubpages = false;` in you LocalSettings.php to make subpages queryable by default
