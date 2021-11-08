Redirects Helper

!!! *Important*: This is currently an experimental version. It is a
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

Converts the target of redirects. Only those with a path as target
are converted to page link, e.g. "t3://page?uid=83".

.. code-block:: shell

   vendor/typo3 redirects_helper:sanitize topagelink

Use dry-run and / or verbose:

.. code-block:: shell

   # -v: verbose
   vendor/typo3 redirects_helper:sanitize -v topagelink
   # -d: dry-run: do not change anything, only show
   vendor/typo3 redirects_helper:sanitize -v -d topagelink

