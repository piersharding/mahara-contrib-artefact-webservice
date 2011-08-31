#!/bin/sh
EXAMPLES='user group institution'
j=0
echo "Select one of the following examples to run:"
for i in $EXAMPLES
do
    echo "$j. $i"
    j=`expr $j + 1`
done
j=`expr $j - 1`
echo "Enter your choice (0..$j or x for exit):"
read opt
if [ "$opt" = "x" ]; then
    echo "aborting"
    exit 1
fi
j=0
for i in $EXAMPLES
do
    if [ "$j" = "$opt" ]; then
        echo "running: $i"
        php example_${i}_api.php --username=blah3 --password=blahblah --url=http://mahara.local.net/maharadev/artefact/webservice/soap/simpleserver.php
        exit 0
    fi
    j=`expr $j + 1`
done
echo "invalid choice selected"
exit
