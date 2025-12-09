#!/bin/bash
#
# Test script for Legacy Asset Resolver
# Usage: ./test-resolution.sh [base-url]
#

BASE_URL="${1:-https://demoportal.t3v13.typo3.ddev.site:4459}"

echo "=================================================="
echo "Testing Legacy Asset Resolver"
echo "Base URL: $BASE_URL"
echo "=================================================="
echo

# Test cases
declare -a TEST_PATHS=(
    "/typo3conf/ext/dh_essentials/Resources/Public/Fonts/fontawesome-pro/fa-brands-400.woff2"
    "/typo3conf/ext/theme_dothomeportal/Resources/Public/Fonts/roboto-v30-latin/roboto-v30-latin-regular.woff2"
    "/typo3conf/ext/configuration_silonext/Resources/Public/Images/bg-body.jpg"
    "/typo3conf/ext/non_existent_extension/Resources/Public/test.css"
)

for path in "${TEST_PATHS[@]}"; do
    echo "Testing: $path"
    
    response=$(curl -I -s -L -w "\nHTTP_CODE:%{http_code}\nREDIRECT_URL:%{redirect_url}\n" "$BASE_URL$path")
    
    http_code=$(echo "$response" | grep "HTTP_CODE:" | cut -d: -f2)
    redirect_url=$(echo "$response" | grep "REDIRECT_URL:" | cut -d: -f2-)
    
    echo "  HTTP Code: $http_code"
    
    if [ "$http_code" = "302" ] || [ "$http_code" = "301" ]; then
        echo "  ✓ Redirect successful"
        echo "  → $redirect_url"
    elif [ "$http_code" = "404" ]; then
        echo "  ✓ 404 returned (expected for non-existent extension)"
    else
        echo "  ✗ Unexpected response code"
    fi
    
    echo
done

echo "=================================================="
echo "Test complete"
echo "=================================================="
