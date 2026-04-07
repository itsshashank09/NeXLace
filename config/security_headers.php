<?php
/**
 * Security Headers Helper
 * 
 * Sets Content Security Policy (CSP) and other security headers
 * to protect against XSS, clickjacking, and MIME type sniffing.
 * 
 * Included in all HTML-rendering PHP pages.
 */

// Prevent XSS and data injection
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: https:;");

// Prevent clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Prevent MIME type sniffing
header("X-Content-Type-Options: nosniff");
?>