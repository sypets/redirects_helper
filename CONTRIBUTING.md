
# Development

## Testsuite

Test suite is set up similar to core test suite and can be executed with script
Build/Script/runTests.sh

To run all tests, use convenience script Build/Scripts/ci.sh.

Use the option -h to see all options!

Make sure to always run Build/Scripts/cleanup.sh before committing or run Build/Scripts/ci.sh
with option -c to clean up.

The ci script may change composer.json for platform constraints. We do not want to push that
kind of change to the codebase!

To run individual tests, see commands in that script or in .github/workflows/tests.yml

Always install with this first:

    Build/Scripts/runTests.sh -s composerInstall

This will install in .Build (cause setup this way in composer.json)

For resources, see https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/Testing/ExtensionTesting.html

# Information

The procedure described here deviates from standard "[tea](https://github.com/TYPO3-Documentation/tea)"
example extension because using TYPO3 testing framework and runTests.sh, it is not current best practices,
but it is what is described in the
[documentation](https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/Testing/ExtensionTesting.html)
