# External Services Setup Guide

This guide outlines the steps required to obtain API keys and credentials for the Family Hub integrations. Once obtained, add these to your `.env` file.

## 1. Apple Calendar (iCloud)

- **What you need:** Your Apple ID email and an **App-Specific Password**.
- **Steps:**
    1. Go to [appleid.apple.com](https://appleid.apple.com/) and sign in.
    2. In the **Sign-In and Security** section, select **App-Specific Passwords**.
    3. Generate a password (e.g., "Family Hub") and copy it.

## 2. Google Calendar

- **What you need:** A Google Cloud Service Account JSON file.
- **Steps:**
    1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
    2. Create a new project called "Family Hub".
    3. Enable the **Google Calendar API**.
    4. Go to **Credentials** > **Create Credentials** > **Service Account**.
    5. Follow the prompts to create the account (skip optional roles).
    6. Once created, click on the service account email > **Keys** > **Add Key** > **Create new key** > **JSON**.
    7. **Save this file as `service-account.json` in `family-hub/storage/app/google-calendar/`.**
    8. **Sharing "Shared with me" Calendars:**
        - For your primary calendar, kids' school, or cub scout calendars:
        - Open the calendar in Google Calendar (Web).
        - Go to **Settings and sharing**.
        - Under **Share with specific people or groups**, click **Add people and groups**.
        - Paste the Service Account email: `family-hub@family-hub-499200.iam.gserviceaccount.com`.
        - Set permissions to **See all event details**.
        - Find the **Calendar ID** (usually an email address or a long string ending in `@group.calendar.google.com`).
        - Add these IDs to your `.env` file, separated by commas.

---

## 3. Office 365 (Business)

- **What you need:** Client ID, Tenant ID, and Client Secret.
- **Steps:**
    1. Go to the [Azure Portal](https://portal.azure.com/) and navigate to **App registrations**.
    2. Click **New registration**. Name it "Family Hub".
    3. Set **Supported account types** to "Accounts in this organizational directory only" (or as required by your business).
    4. Once created, copy the **Application (client) ID** and **Directory (tenant) ID**.
    5. Go to **Certificates & secrets** > **New client secret**. Copy the secret value immediately (it will be hidden later).
    6. Go to **API permissions** > **Add a permission** > **Microsoft Graph**.
    7. Select **Application permissions** and search for `Calendars.Read` (or `Calendars.ReadWrite` if you want to add events).
    8. **Important:** Click **Grant admin consent for [Your Org]** to allow the app to access the calendar without a user login prompt.

## 4. OpenWeatherMap

- **What you need:** An API Key (Free "One Call 3.0" subscription).
- **Steps:**
    1. Create an account at [openweathermap.org](https://openweathermap.org/).
    2. Navigate to your **API Keys** tab and generate a new key.
    3. **Note:** One Call 3.0 requires a credit card for "over-limit" billing, but the first 1,000 calls per day are **free**. You can set a daily limit of 1,000 to ensure it stays free.

## 5. Amazing Marvin

- **What you need:** Your Secret API Token.
- **Steps:**
    1. Open Amazing Marvin (Web or Desktop).
    2. Go to **Strategies** (☰ menu or press `S`).
    3. Enable the **API** strategy.
    4. Click **Settings** on the API strategy card to view your **Secret API Token**.

## 6. iCal / Webcal Subscriptions

- **What you need:** The `.ics` or `webcal://` URLs for your subscriptions (e.g., Cub Scouts, School).
- **Steps:**
    1. Locate the subscription link (e.g., the Cub Scouts and School links you shared).
    2. Add them to your `.env` file as a comma-separated list.

---

## 7. Paprika Recipe Manager

- **What you need:** Your Paprika Cloud Sync Email and Password.
- **Steps:** Ensure you have created a Cloud Sync account within the Paprika app settings.

---

## .env Template

Add these keys to your `family-hub/.env` file:

```env
# Apple
ICLOUD_EMAIL=your-email@icloud.com
ICLOUD_PASSWORD=xxxx-xxxx-xxxx-xxxx

# Google
GOOGLE_CALENDAR_ID=id1@gmail.com,id2@group.calendar.google.com
# Path to the JSON file we created
GOOGLE_SERVICE_ACCOUNT_JSON_PATH=storage/app/google-calendar/service-account.json

# Microsoft / Office 365
MS_GRAPH_CLIENT_ID=your-client-id
MS_GRAPH_TENANT_ID=your-tenant-id
MS_GRAPH_CLIENT_SECRET=your-client-secret
MS_GRAPH_USER_ID=your-work-email@company.com

# Public Subscriptions (iCal/Webcal)
CALENDAR_SUBSCRIPTIONS=https://example.com/school.ics,webcal://example.com/scouts.ics

# Weather
OPENWEATHER_API_KEY=your-api-key-here
LATITUDE=your-lat
LONGITUDE=your-lon

# Marvin
MARVIN_API_TOKEN=your-secret-token-here

# Paprika
PAPRIKA_EMAIL=your-email@example.com
PAPRIKA_PASSWORD=your-password
```
