# Copy this file to env.local.ps1 (git ignores it) and fill in your real values
# Load it in each new PowerShell window with:
#   . .\env.local.ps1
# These are fake values. Never commit real passwords

# MySQL (Laragon)
$env:DB_HOST     = "127.0.0.1"
$env:DB_PORT     = "3306"
$env:DB_NAME     = "marketing_lab"
$env:DB_USER     = "YOUR_DB_USER"
$env:DB_PASSWORD = "YOUR_DB_PASSWORD"

# AWS (the keys come from your profile in ~/.aws/credentials)
$env:AWS_PROFILE   = "YOUR_AWS_PROFILE"
$env:AWS_REGION    = "YOUR_AWS_REGION"
$env:SQS_QUEUE_URL = "https://sqs.YOUR_AWS_REGION.amazonaws.com/ACCOUNT_ID/MarketingEmailQueue-s12345"

# Mailtrap test inbox (SMTP)
$env:MAILTRAP_HOST          = "sandbox.smtp.mailtrap.io"
$env:MAILTRAP_PORT          = "587"
$env:MAILTRAP_USERNAME      = "YOUR_MAILTRAP_USERNAME"
$env:MAILTRAP_PASSWORD      = "YOUR_MAILTRAP_PASSWORD"
$env:MAIL_FROM_ADDRESS      = "marketing@example.com"
$env:MAILTRAP_DELAY_SECONDS = "11"
