#!/bin/bash 

# Run on HerikaAI top level directory
# bash -x debug/debug_tests2.sh

# Latency test 
echo "Latency test "
time php streamv2.php "inputtext|594939787246001|788840576|Draven: Who do you see?"
time php streamv2.php "inputtext|594939787246001|788840576|Draven: What do you think about the GreyBeards?"
time php streamv2.php "inputtext|594939787246001|788840576|Draven: Would you like to have some sex?"		#NSFW TESTING
time php streamv2.php "inputtext|594939787246001|788840576|Draven: Describe a sex situation between us"		#NSFW TESTING
 





