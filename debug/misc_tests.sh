#!/bin/bash


LAST_TIMESTAMP=$(sqlite3 data/mysqlitedb.db  "select max(gamets) from eventlog;" ".exit")
FAKE_TIMESTAMP=$(( LAST_TIMESTAMP+1 ))

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[1;31m'
NC='\033[0m' # No Color

doRequest() {

	echo "$2"
	printf ${GREEN}
	echo php stream.php "\"inputtext|6|$FAKE_TIMESTAMP|$1\""
	printf ${YELLOW}
	time php stream.php "inputtext|6|$FAKE_TIMESTAMP|$1"
	printf ${NC}
	echo "-"


}

doRequest "Draven : do you remember that history about a mage you told me at Haelga's place?"

exit

doRequest "Draven: Do you remember Brynjolf?" "Memory request."

doRequest "Draven: Do you wanna some drinks?" "Try a situational context."

doRequest "Draven: Where did you get your hunter bow?" "Memory request."

doRequest "Draven: Do you remember our battle against Vuljotnaak?" "Memory request."


doRequest "Draven: Do you remember that inn in Falkreath? the Dead Man whatever..." "Memory request."

doRequest "Draven: What do you think about me?" "Question about prompt 1"

doRequest "Draven: Herika, please describe your personality." "Question about prompt 2"

doRequest "Draven: Draven: What do you know about the GreyBeards?" "General Knowledge. A vague response is acceptable; it's what an average human would do, pretending to know what they're talking about. but we don't want hallucinations either"





