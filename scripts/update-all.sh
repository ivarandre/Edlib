#!/bin/bash

source $HOME/.nvm/nvm.sh

# cd to this script directory
cd "$(dirname "$0")"

# Go to sourcecode folder
pushd ../sourcecode

# java repos
declare -a javaRepos=(
  "apis/version"
  "not_migrated/edlibfacade"
)

# php repos
declare -a phpRepos=(
  "apis/contentauthor"
  "apis/license"
)

for i in "${javaRepos[@]}"
do
  pushd $i
  git pull
  mvn clean package -DskipTests
  popd
done

for i in "${phpRepos[@]}"
do
  pushd $i
  git pull
  nvm use 11
  php7.4 /usr/local/bin/composer install
  if test -f "package.json"; then
    npm i
    npm production
  fi
  popd
done

# Go back to initial folder
popd
