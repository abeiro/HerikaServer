#!/bin/bash 

# Run on HerikaAI top level directory
# bash -x debug/debug_tests.sh

echo "Inputtext, functions/actions enabled";
php streamv2.php "inputtext|594939787246001|788840576|Draven: Look at me"
read 


echo "Inputtext, functions/actions disabled"
php stream.php "inputtext|594939787246001|788840576|Draven: Look at me"
read 

echo "Function return value test (needs openai)";
php streamv2.php "funcret|594939787246001|788840576|command@LookAt@Draven@Herika looks at Draven, he is  wearing a strange hat"
read 


echo "Lockpick event"
php comm.php "lockpicked|594939787246001|788840576|secret door"
read 

echo "Wake up event"
php comm.php "goodmorning|594939787246001|788840576|Draven wakes up"
read 

echo "Write diary entry, no functions"
php main.php 		"diary|594939787246001|788840576|Please, write in your diary a summary"
read 

echo "Write diary entry, functions enabled"
php streamv2.php 	"diary|594939787246001|788840576|Please, write in your diary a summary"
read 





