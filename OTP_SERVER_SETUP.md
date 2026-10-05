# Email OTP service

The registration page uses a separate Express service to send and verify a six-digit email code.

```sh
cd nodemailer
npm ci
# Copy .env.example to .env and add EMAIL_USER and EMAIL_PASS.
npm start
```

The server listens at `http://localhost:3000`. Keep the PHP server running as well. For Gmail, `EMAIL_PASS` is an app password for the configured account, not a value to publish in source control. See [Nodemailer's SMTP documentation](https://nodemailer.com/smtp).

Codes expire after ten minutes, have a limited number of verification attempts and are removed after successful verification. The store is in memory, so restarting the process invalidates outstanding codes. Resend cooldowns also apply.

The service uses cryptographic random numbers for codes and keeps SMTP certificate validation enabled. A certificate or SMTP authentication failure should be fixed at the connection/configuration level; do not disable TLS checks to make it succeed.

## Integration limits

On local hostnames, `registration.html` points to port 3000. A deployed site needs an email-service URL that its users can reach. Browser-side OTP success is not enough to authorize PHP registration: the PHP endpoint needs proof verified on the server before this can be used as a production registration control.

If email fails, check the service output, SMTP credentials, account settings and network connection. If port 3000 is occupied, choose a different service port and update the registration URL consistently.
