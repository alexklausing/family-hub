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
    
    # Magic trick: Get display variables from the active GNOME/X11 session so it works over SSH
    ACTIVE_PID=$(pgrep -n gnome-shell || pgrep -n Xwayland || pgrep -n Xorg)
    if [ -n "$ACTIVE_PID" ]; then
      export $(cat /proc/$ACTIVE_PID/environ | grep -z '^DBUS_SESSION_BUS_ADDRESS=' | tr -d '\0')
      export $(cat /proc/$ACTIVE_PID/environ | grep -z '^WAYLAND_DISPLAY=' | tr -d '\0')
      export $(cat /proc/$ACTIVE_PID/environ | grep -z '^DISPLAY=' | tr -d '\0')
      export $(cat /proc/$ACTIVE_PID/environ | grep -z '^XAUTHORITY=' | tr -d '\0')
    fi

    if [ "$STATE" = "sleep" ]; then
      echo "$(date): Dashboard requested SLEEP. Turning off monitor."
      busctl --user set-property org.gnome.Mutter.DisplayConfig /org/gnome/Mutter/DisplayConfig org.gnome.Mutter.DisplayConfig PowerSaveMode i 1 2>/dev/null || \
      xset dpms force off
    elif [ "$STATE" = "wake" ]; then
      echo "$(date): Dashboard requested WAKE. Turning on monitor."
      busctl --user set-property org.gnome.Mutter.DisplayConfig /org/gnome/Mutter/DisplayConfig org.gnome.Mutter.DisplayConfig PowerSaveMode i 0 2>/dev/null || \
      xset dpms force on
    fi
    LAST_STATE=$STATE
  fi
  
  sleep 2
done
