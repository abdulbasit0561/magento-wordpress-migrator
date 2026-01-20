#!/bin/bash
#
# Script to help find Magento database credentials
# Run this on the Magento server (not WordPress server)
#

echo "=== Magento Database Credentials Finder ==="
echo ""

# Check if we're on a Magento install
if [ ! -f "app/etc/env.php" ] && [ ! -f "app/etc/local.xml" ]; then
    echo "Error: Not in a Magento directory"
    echo "Please run this script from your Magento installation directory"
    exit 1
fi

echo "1. Checking Magento version..."
if [ -f "app/etc/env.php" ]; then
    echo "   ✓ Magento 2.x detected"
    CONFIG_FILE="app/etc/env.php"
else
    echo "   ✓ Magento 1.x detected"
    CONFIG_FILE="app/etc/local.xml"
fi
echo ""

echo "2. Extracting database credentials..."
echo ""

if [ -f "app/etc/env.php" ]; then
    # Magento 2
    echo "Database Host:"
    grep -oP "'host'\s*=>\s*'\K[^']+" app/etc/env.php | head -1
    echo ""

    echo "Database Name:"
    grep -oP "'dbname'\s*=>\s*'\K[^']+" app/etc/env.php | head -1
    echo ""

    echo "Database User:"
    grep -oP "'username'\s*=>\s*'\K[^']+" app/etc/env.php | head -1
    echo ""

    echo "Database Password:"
    grep -oP "'password'\s*=>\s*'\K[^']+" app/etc/env.php | head -1
    echo ""

    echo "Table Prefix (if any):"
    grep -oP "'table_prefix'\s*=>\s*'\K[^']+" app/etc/env.php | head -1
    echo ""

else
    # Magento 1
    echo "Database Host:"
    grep -oP "<host><![CDATA[\K[^<]+" app/etc/local.xml
    echo ""

    echo "Database Name:"
    grep -oP "<dbname><![CDATA[\K[^<]+" app/etc/local.xml
    echo ""

    echo "Database User:"
    grep -oP "<username><![CDATA[\K[^<]+" app/etc/local.xml
    echo ""

    echo "Database Password:"
    grep -oP "<password><![CDATA[\K[^<]+" app/etc/local.xml
    echo ""

    echo "Table Prefix (if any):"
    grep -oP "<table_prefix><![CDATA[\K[^<]+" app/etc/local.xml
    echo ""
fi

echo "=== Next Steps ==="
echo ""
echo "1. Copy the database credentials above"
echo "2. Go to your WordPress Admin → Magento → Migrator → Settings"
echo "3. Update the database credentials with these values"
echo "4. Click 'Save Changes'"
echo "5. Click 'Test Connection' to verify"
echo "6. Try migration again"
echo ""
