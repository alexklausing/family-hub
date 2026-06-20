# Family Hub Hardware Purchase Plan

This document outlines the hardware requirements for the Family Hub project, finalized with the **CUNPU 24" IPS Touchscreen** as the primary display.

## 🛒 Bill of Materials (Core System)

| Item                      | Description                                                 | Est. Price | Link                                                                                           |
| :------------------------ | :---------------------------------------------------------- | :--------- | :--------------------------------------------------------------------------------------------- |
| **Argon NEO 5 256GB Kit** | Enclosure + 256GB SSD + 27W Power Supply                    | $110.00    | [Amazon](https://www.amazon.com/Argon-NVME-Case-Raspberry-256GB/dp/B0G37HRFQY)                 |
| **Raspberry Pi 5 (8GB)**  | Main controller (Board only)                                | $80.00     | [PiShop.us](https://www.pishop.us/product/raspberry-pi-5-8gb/)                                 |
| **CUNPU 24" Touchscreen** | 24" IPS 10-Point Capacitive Touch (1080p)                   | $160.00    | [Amazon](https://www.amazon.com/CUNPU-Touchscreen-Mountable-Adjustment-Business/dp/B0DC6NG3K3) |
| **HC-SR501 PIR Sensor**   | **Efficiency Upgrade:** Auto-off monitor when room is empty | $5.00      | [Amazon](https://www.amazon.com/s?k=hc-sr501+pir+motion+sensor)                                |

## 🛒 Mounting & Cables

| Item                   | Description                                      | Est. Price | Link                                                                |
| :--------------------- | :----------------------------------------------- | :--------- | :------------------------------------------------------------------ |
| **VESA Wall Mount**    | Low-Profile 100x100mm Bracket                    | $15.00     | [Amazon](https://www.amazon.com/s?k=low+profile+vesa+mount+100x100) |
| **Micro-HDMI to HDMI** | Right-Angle Cable (3 Feet)                       | $10.00     | [Amazon](https://www.amazon.com/s?k=right+angle+micro+hdmi+to+hdmi) |
| **USB-A to USB-B**     | For touch data (usually included with monitor)   | -          | Included                                                            |
| **Matte Film (Opt.)**  | **Recommended:** 24" Anti-Glare Screen Protector | $20.00     | [Amazon](https://www.amazon.com/s?k=24+inch+matte+screen+protector) |

---

## 🔍 Compatibility Verification (CUNPU + Pi 5)

- **Touch Interface:** The CUNPU uses standard **USB-HID** over a USB-B to USB-A cable. The Pi 5 will recognize this as a native 10-point touch device without drivers.
- **Video Signal:** The Pi 5 output (Micro-HDMI) is compatible with the CUNPU's HDMI input.
- **Power Isolation:** The CUNPU uses its own AC power adapter, ensuring it does **not** draw power from the Pi 5's 5V rail. This maintains stability for the NVMe SSD.
- **Viewing Angles:** The CUNPU is an **IPS** panel, ensuring the dashboard is readable from side angles in a kitchen/hallway.
- **Finish Note:** The CUNPU is **Glossy**. To achieve the requested "Matte" look, a $20 matte film is recommended (listed above).

---

## 🛠 Technical Rationale (Recap)

1.  **8GB RAM:** Required to run the 7+ Docker containers plus a Chromium kiosk smoothly at 1080p.
2.  **NVMe SSD:** Essential for database longevity (PostgreSQL) and search speed (Meilisearch).
3.  **PIR Sensor:** Crucial for energy efficiency (drops draw from ~35W to ~4W during inactivity).

---

## 📂 Reference: Saved Alternatives

These monitors were considered but moved to reference for budget or availability reasons:

- **Dell P2424HT (~$330):** The modern "Matte Specialist" choice. Native anti-glare, built-in Ethernet hub.
- **Prechen 23.8" Portable (~$180):** The "Ultra-Thin" choice for a flush-to-wall look. Uses a VA panel (narrower viewing angles).
- **Philips 242B9T:** (Discontinued) Water-resistant kitchen model.

---

## 🚀 Post-Purchase Setup Notes

1. **Bootloader:** Update the Pi 5 bootloader to support NVMe booting.
2. **Kiosk Mode:** Use a lightweight window manager (Openbox) to launch Chromium in `--kiosk` mode.
3. **HDMI-CEC:** Configure the PIR sensor script to use `cec-client` to put the CUNPU into standby during inactivity.
