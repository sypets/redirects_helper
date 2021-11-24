Redirects Helper

.. important::

   This is currently an experimental version. It is a
   proof-of-concept and not yet used for production. Use at your own risk!

What does it do
===============

This extension does the following:

*  Adds the field "protected" to sys_redirect and TCA so that redirects
   can be protected from automatic deletion.
   (This is a backport of v11 functionality.)

*  Supplies a command redirects_helper:sanitize to convert
   redirects with a path as target to page targets. This has the following
   advantages:

   * no more redirect loops - all redirects point directly to the final
     target by page id (which does not change when the URL is changed)
     https://forge.typo3.org/issues/92748
   * prevents problems with redirects which do not work, see issues
     https://forge.typo3.org/issues/89327 and https://forge.typo3.org/issues/91557

Commands
========

.. warning::

   There is no going back. Make a backup first.

path2page
---------

Converts the target of redirects. Only those with a path as target
are converted to page link, e.g. "t3://page?uid=83".

Show help:

.. code-block:: shell

   vendor/bin/typo3 redirects_helper:path2page -h

Use dry-run (does not change anything):

.. code-block:: shell

   # -d: dry-run: do not change anything, only show
   vendor/bin/typo3 redirects_helper:path2page -d

You can use -v, -vv or -vvv (with increasing verbosity level):

.. code-block:: shell

   # -v: verbose
   vendor/bin/typo3 redirects_helper:path2page -d -v

The output will show paths which can be converted (starting with "OK:"). With
verbosity level -vv and above you will also see failed attempts to convert
(which are not an error but due to fact that not all targets can be converted).

You can also use this to filter for targets which cannot be resolved:

.. code-block:: shell

   # -v: verbose
   vendor/bin/typo3 redirects_helper:path2page -d -vvv | grep -E "Skipping: URL.* does not resolve to valid URL"

These are redirects where it might make sense to remove them. But beware, this
is also the case if the target page is hidden.

By default, interactive mode is on, so you must confirm each conversion. If
you are confident, that it works correctly, you can use -n (non-interactive)

.. code-block:: shell

   # -v: verbose
   vendor/bin/typo3 redirects_helper:path2page -n

