# NeXLace OTP Email Server Setup

## The Problem

When you click "Send OTP" during registration, you're getting the error:
> **"Error connecting to server."**

This happens because the Node.js email server is not running.

---

## Quick Fix - 3 Steps

### Step 1: Install Node.js

1. **Download Node.js**
   - Go to: https://nodejs.org/
   - Download the **LTS version** (recommended)
   - Run the installer
   - ✅ Keep all default options
   - ✅ Click "Next" through the installation

2. **Verify Installation**
   - Open a **new** Command Prompt
   - Type: `node --version`
   - You should see something like: `v20.x.x`

### Step 2: Start the OTP Server

**Option A: Double-click the script (Easiest)**
1. Go to: `c:\xampp\htdocs\NeXLace\`
2. Double-click: **`start-otp-server.bat`**
3. A black window will open and show "Server running on http://localhost:3000"
4. ✅ **Keep this window open** while using the application

**Option B: Manual start**
1. Open Command Prompt
2. Run:
   ```bash
   cd c:\xampp\htdocs\NeXLace\nodemailer
   npm install
   node index.js
   ```
3. You should see: "Server running on http://localhost:3000"

### Step 3: Test OTP

1. Go to: `http://localhost/NeXLace/registration.html`
2. Enter your email address
3. Click **"Send OTP"** button
4. ✅ You should see: "OTP sent successfully! Check your email."

---

## How It Works

Your NeXLace application has two servers:

1. **PHP Server (Apache via XAMPP)** - Port 80
   - Handles: Login, registration, database operations
   - Status: ✅ Running

2. **Node.js Server** - Port 3000
   - Handles: Sending OTP emails via Gmail
   - Status: ❌ Not running (causing your error)

The registration page tries to connect to `http://localhost:3000/send-otp` to send the OTP email. If the Node.js server isn't running, you get the connection error.

---

## Email Configuration

The OTP server is configured to use Gmail:

**File**: `nodemailer/.env`
```
Email: shashankshankarmadiwal@gmail.com
App Password: lanx qsex jrgu xkgu
```

This is a Gmail App Password (not your regular password). It's already configured and should work.

---

## Important Notes

### ⚠️ Keep Both Servers Running

To use NeXLace with OTP functionality:
1. ✅ Start Apache + MySQL in XAMPP
2. ✅ Start Node.js server (via `start-otp-server.bat`)
3. ✅ Keep both running while using the app

### 💡 Alternative: Disable OTP (Optional)

If you don't want to use OTP functionality, you can:
1. Remove the OTP input field from registration
2. Skip email verification
3. Allow direct registration without OTP

Let me know if you want me to modify the registration to work without OTP!

---

## Troubleshooting

### "Node.js is not installed"
- Download and install from: https://nodejs.org/
- Choose the LTS version
- Restart Command Prompt after installation

### "Failed to send OTP"
- Check that the Node.js server is running
- Verify the email in `.env` file is correct
- Check your internet connection

### "Port 3000 is already in use"
- Another program is using port 3000
- Close other applications or change the port in `nodemailer/index.js`

---

## Next Steps

1. **Install Node.js** (if not installed)
2. **Run `start-otp-server.bat`**
3. **Test the OTP feature**
4. ✅ Everything should work!
