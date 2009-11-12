#!/bin/bash
VER=`grep "version" meta.ini`
VER=`echo "$VER" | cut -c 16-`
REV=`git rev-parse --short HEAD`
DIR=`pwd`
DIR=`basename "$DIR"`
UDR=`php -r "echo ucfirst(\"$DIR\");"`
PKG="Metrof_$UDR-1.$VER-$REV.zip"
#echo $VER
cd ..
zip -r $PKG $DIR -x "$DIR/.git*" -x "*~" -x "$DIR/mkzip.sh"


