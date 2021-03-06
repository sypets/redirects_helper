#!/bin/bash

# 7.4 and composer latest

# abort on error
set -e
set -x

php="7.4"
m="composerInstallMax"
cleanup=0

# -------------------
# automatic variables
# -------------------
prevdir=$(pwd)
thisdir=$(dirname $0)
cd $thisdir
thisdir=$(pwd)
cd $prevdir
progname=$(basename $0)

usage()
{
    echo "[-p <PHP version>] [-m <min|max>] [-h] [-c]"
    echo " -c : cleanup"
    exit 1
}

while getopts "hp:m:c" opt;do
  case $opt in
    p)
      php=${OPTARG}
      ;;
    h)
      usage
      ;;
    c)
      cleanup=1
      ;;
    m)
      level=${OPTARG}
      if [[ $level == min ]];then
        m="composerInstallMin"
      fi
      ;;
    \?)
      echo "invalid option"
      usage
      ;;
  esac
done
shift $((OPTIND-1))

echo "Tests: prepare"
# we need to get typo3/cms-core as source to get the Tests dir with Test traits
echo "Install typo3/cms-core as source. Modifies composer.json! (config:preferred-install)"
composer config preferred-install.typo3/cms-core source


echo "composer install"
Build/Scripts/runTests.sh -p ${php} -s ${m}

echo "cgl"
Build/Scripts/runTests.sh -p ${php} -s cgl -n

echo "composer validate"
Build/Scripts/runTests.sh -p ${php} -s composerValidate

echo "lint"
Build/Scripts/runTests.sh -p ${php} -s lint

#echo "phpstan"
Build/Scripts/runTests.sh -p ${php} -s phpstan -e "-c ../phpstan.neon"

#echo "Unit tests"
#Build/Scripts/runTests.sh -p ${php} -s unit

echo "functional tests"
Build/Scripts/runTests.sh -p ${php} -d mariadb -s functional

echo "cleanup"
echo "remove preferred-install from composer.json"
composer config --unset preferred-install

if [ $cleanup -eq 1 ];then
    $thisdir/cleanup.sh
fi

echo "done"
