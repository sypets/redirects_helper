This still needs be implemented or checked ...

# Problems

## Problems which should get fixed

* target path is converted to page link (typolink t3://page?). This should also fix
  (some) redirect loops

## Work in progress

Figure out, which redirects can be deleted:

* redirects where the target page no longer exists (or can for some other reason not be resolved)


These can probably be deleted but should be checked:

* redirects for hidden pages
* redirects which were created temporarily and slug was immediately changed, often with source path
  with -1 or -2 etc. at the end or with 'standard-title' or the default text for translated content
  e.g.

  * /page/network-1
  * /page/standard-titel
  * /page/standard-titel-1

*  previous check can be combined with creation date of page and creation date of redirect
