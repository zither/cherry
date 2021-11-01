 #!/bin/sh
DIR="$( cd "$( dirname "$0" )" >/dev/null && pwd )"
PARENT_DIR="$( dirname ${DIR} )"
SNOOZE=2
COMMAND="php $PARENT_DIR/public/index.php CronTask"
LOG="$PARENT_DIR/storage/tasks.log"
RUN_NEXT_TASK=true

function log ()
{
  if [ ! -z "$1" ];
  then
   echo $(date '+%Y-%m-%d %H:%M:%S') $1 >> ${LOG} 2>&1
  fi
}

function sigint_handler ()
{
  echo -e "\b\bTask runner will exit after current task is done."
  RUN_NEXT_TASK=false
}
trap sigint_handler 2

if [ -f $LOG ]; then
  touch $LOG
fi

echo "Task runner is starting..."
while $RUN_NEXT_TASK
do
 output=$($COMMAND 2>&1)
 log "$output"
 sleep ${SNOOZE}
done
echo "Task runner Stopped."
