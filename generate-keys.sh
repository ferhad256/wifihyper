#!/bin/bash

# Yo Payments RSA Key Generation Script
# This script generates a private/public key pair for Yo Payments API authentication

echo "=== Yo Payments RSA Key Generation ==="
echo ""

# Check if OpenSSL is installed
if ! command -v openssl &> /dev/null; then
    echo "Error: OpenSSL is not installed. Please install OpenSSL first."
    exit 1
fi

# Create keys directory if it doesn't exist
mkdir -p storage/keys

# Generate private key
echo "Generating private key..."
openssl genpkey -algorithm RSA -out storage/keys/yo_payments_private_key.pem -pkeyopt rsa_keygen_bits:2048

if [ $? -eq 0 ]; then
    echo "✅ Private key generated successfully: storage/keys/yo_payments_private_key.pem"
else
    echo "❌ Failed to generate private key"
    exit 1
fi

# Extract public key
echo "Extracting public key..."
openssl rsa -pubout -in storage/keys/yo_payments_private_key.pem -out storage/keys/yo_payments_public_key.pem

if [ $? -eq 0 ]; then
    echo "✅ Public key extracted successfully: storage/keys/yo_payments_public_key.pem"
else
    echo "❌ Failed to extract public key"
    exit 1
fi

# Set proper permissions
chmod 600 storage/keys/yo_payments_private_key.pem
chmod 644 storage/keys/yo_payments_public_key.pem

echo ""
echo "=== Key Generation Complete ==="
echo ""
echo "IMPORTANT SECURITY NOTES:"
echo "1. Private key: storage/keys/yo_payments_private_key.pem"
echo "   - Keep this file secure and never share it"
echo "   - Do not commit this file to version control"
echo ""
echo "2. Public key: storage/keys/yo_payments_public_key.pem"
echo "   - Share this file with your Yo Payments account representative"
echo "   - This will be configured under your Yo Payments account profile"
echo ""
echo "3. Update your .env file with:"
echo "   YO_PAYMENTS_PUBLIC_KEY_ENABLED=true"
echo "   YO_PAYMENTS_PRIVATE_KEY_PATH=storage/keys/yo_payments_private_key.pem"
echo ""
echo "4. Add to .gitignore:"
echo "   storage/keys/yo_payments_private_key.pem"
echo "" 