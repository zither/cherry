 #!/bin/sh
DIR="$( cd "$( dirname "$0" )" >/dev/null && pwd )"
PARENT_DIR="$( dirname ${DIR} )"
SNOOZE=5
COMMAND="php $PARENT_DIR/public/index.php CronTask"
LOG="$PARENT_DIR/storage/tasks.log"

if [ -f $LOG ]; then
  touch $LOG
fi

echo `date` "starting..." >> ${LOG} 2>&1
while true
do
 ${COMMAND} >> ${LOG} 2>&1
 #echo `date` "sleeping..." >> ${LOG} 2>&1
 sleep ${SNOOZE}
done