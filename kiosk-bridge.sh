#!/bin/bash
# Kiosk Bridge Script for Energy Savings
# This script polls the local dashboard to physically power down the monitor backlight.
# To run this in the background, you can use: nohup ./kiosk-bridge.sh &

URL="http://localhost/api/monitor/state"
LAST_STATE="wake"

echo "Starting Kiosk Monitor Bridge..."
echo "Polling $URL for monitor state..."

while true; do
  # Fetch the state from the API
  RESPONSE=$(curl -s $URL)
  STATE=$(echo $RESPONSE | grep -o '"state":"[^"]*"' | cut -d'"' -f4)
  
  if [ "$STATE" != "$LAST_STATE" ] && [ ! -z "$STATE" ]; then
    if [ "$STATE" = "sleep" ]; then
      echo "$(date): Dashboard requested SLEEP. Using X11 DPMS Standby."
      DISPLAY=:0 xset dpms force standby
    elif [ "$STATE" = "wake" ]; then
      echo "$(date): Dashboard requested WAKE. Waking and forcing HDMI handshake."
      DISPLAY=:0 xset dpms force on
      DISPLAY=:0 xrandr --auto
    fi
    LAST_STATE=$STATE
  fi
  
  sleep 2
done
