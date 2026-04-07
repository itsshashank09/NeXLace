require('dotenv').config();
const express = require('express');
const nodemailer = require('nodemailer');
const cors = require('cors');
const dns = require('dns');

// Fix DNS resolution issues — use Google Public DNS servers
dns.setServers(['8.8.8.8', '8.8.4.4', '1.1.1.1']);

const app = express();
const PORT = 3000;

// Middleware
app.use(cors());
app.use(express.json());

// In-memory OTP store: email -> { otp, expiry, attempts }
const otpStore = new Map();
const OTP_EXPIRY_MS = 10 * 60 * 1000; // 10 minutes
const MAX_ATTEMPTS = 5;
const RESEND_COOLDOWN_MS = 60 * 1000; // 60 seconds between resends

// Cleanup expired OTPs every 5 minutes
setInterval(() => {
  const now = Date.now();
  for (const [email, data] of otpStore) {
    if (now > data.expiry) {
      otpStore.delete(email);
    }
  }
}, 5 * 60 * 1000);

// Generate a 6-digit OTP
function generateOTP() {
  const digits = '0123456789';
  let otp = '';
  for (let i = 0; i < 6; i++) {
    otp += digits[Math.floor(Math.random() * 10)];
  }
  return otp;
}

// Configure the transporter with explicit SMTP settings
const transporter = nodemailer.createTransport({
  host: 'smtp.gmail.com',
  port: 465,
  secure: true, // use SSL
  auth: {
    user: process.env.EMAIL_USER,
    pass: process.env.EMAIL_PASS,
  },
  connectionTimeout: 10000, // 10 seconds
  greetingTimeout: 10000,
  socketTimeout: 15000,
  tls: {
    rejectUnauthorized: false, // Allow self-signed certs if needed
  },
});

// POST endpoint to send OTP
app.post('/send-otp', async (req, res) => {
  const { email } = req.body;

  if (!email) {
    return res.status(400).json({ success: false, message: 'Email is required' });
  }

  // Rate-limit: prevent resending too quickly
  const existing = otpStore.get(email);
  if (existing && (Date.now() - (existing.expiry - OTP_EXPIRY_MS)) < RESEND_COOLDOWN_MS) {
    return res.status(429).json({ success: false, message: 'Please wait before requesting a new OTP' });
  }

  const otp = generateOTP();

  const mailOptions = {
    from: process.env.EMAIL_USER,
    to: email,
    subject: 'Your One-Time Password (OTP)',
    text: `Your OTP code is: ${otp}\n\nIt is valid for 10 minutes.`,
  };

  try {
    const info = await transporter.sendMail(mailOptions);

    // Store OTP server-side with expiry
    otpStore.set(email, {
      otp,
      expiry: Date.now() + OTP_EXPIRY_MS,
      attempts: 0,
    });

    console.log('OTP sent to ' + email);
    res.json({ success: true, message: 'OTP sent successfully' });
  } catch (error) {
    console.error('Error sending email:', error);
    res.status(500).json({ success: false, message: 'Failed to send OTP' });
  }
});

// POST endpoint to verify OTP
app.post('/verify-otp', (req, res) => {
  const { email, otp } = req.body;

  if (!email || !otp) {
    return res.status(400).json({ success: false, message: 'Email and OTP are required' });
  }

  const stored = otpStore.get(email);

  if (!stored) {
    return res.status(400).json({ success: false, message: 'No OTP found. Please request a new one.' });
  }

  // Check expiry
  if (Date.now() > stored.expiry) {
    otpStore.delete(email);
    return res.status(400).json({ success: false, message: 'OTP has expired. Please request a new one.' });
  }

  // Check attempt limit
  if (stored.attempts >= MAX_ATTEMPTS) {
    otpStore.delete(email);
    return res.status(429).json({ success: false, message: 'Too many failed attempts. Please request a new OTP.' });
  }

  // Verify OTP
  if (stored.otp !== otp) {
    stored.attempts += 1;
    return res.status(400).json({
      success: false,
      message: 'Invalid OTP',
      remaining_attempts: MAX_ATTEMPTS - stored.attempts,
    });
  }

  // OTP is valid — remove from store (single use)
  otpStore.delete(email);
  res.json({ success: true, message: 'OTP verified successfully' });
});

app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
