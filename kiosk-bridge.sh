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
  
  # Extract "sleep" or "wake"
  STATE=$(echo $RESPONSE | grep -o '"state":"[^"]*"' | cut -d'"' -f4)
  
  if [ "$STATE" != "$LAST_STATE" ] && [ ! -z "$STATE" ]; then
    if [ "$STATE" = "sleep" ]; then
      echo "$(date): Dashboard requested SLEEP. Turning off monitor."
      # Try Wayland (GNOME) first
      busctl --user set-property org.gnome.Mutter.DisplayConfig /org/gnome/Mutter/DisplayConfig org.gnome.Mutter.DisplayConfig PowerSaveMode i 1 2>/dev/null || \
      # Fallback to X11
      DISPLAY=:0 xset dpms force off
    elif [ "$STATE" = "wake" ]; then
      echo "$(date): Dashboard requested WAKE. Turning on monitor."
      # Try Wayland (GNOME) first
      busctl --user set-property org.gnome.Mutter.DisplayConfig /org/gnome/Mutter/DisplayConfig org.gnome.Mutter.DisplayConfig PowerSaveMode i 0 2>/dev/null || \
      # Fallback to X11
      DISPLAY=:0 xset dpms force on
    fi
    LAST_STATE=$STATE
  fi
  
  sleep 2
done
