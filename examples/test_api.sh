#!/bin/bash

# WP LLM Connector - API Test Script
# This script tests all available endpoints

# Configuration
# IMPORTANT: Replace these values with your actual WordPress site URL and the API key
# you generated in WordPress (Settings > LLM Connector). This is NOT a Claude API key.
WORDPRESS_URL="https://yoursite.com"
API_KEY="wpllm_your_api_key_here"  # Use the API key from WordPress admin
BASE_URL="${WORDPRESS_URL}/wp-json/wp-llm-connector/v1"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check configuration
if [ "$API_KEY" == "wpllm_your_api_key_here" ]; then
    echo -e "${RED}Error: Please set your API_KEY (from WordPress Settings > LLM Connector) in the script${NC}"
    exit 1
fi

if [ "$WORDPRESS_URL" == "https://yoursite.com" ]; then
    echo -e "${RED}Error: Please set your WORDPRESS_URL in the script${NC}"
    exit 1
fi

echo "WP LLM Connector - API Test"
echo "============================"
echo ""
echo "WordPress URL: $WORDPRESS_URL"
echo "API Key: ${API_KEY:0:20}..."
echo ""

# Test function
test_endpoint() {
    local name=$1
    local endpoint=$2
    local auth_required=$3
    
    echo -n "Testing $name... "
    
    if [ "$auth_required" == "true" ]; then
        response=$(curl -s -w "\n%{http_code}" -H "X-WP-LLM-API-Key: $API_KEY" "${BASE_URL}${endpoint}")
    else
        response=$(curl -s -w "\n%{http_code}" "${BASE_URL}${endpoint}")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" == "200" ]; then
        echo -e "${GREEN}✓ OK${NC}"
        if [ "$VERBOSE" == "true" ]; then
            echo "$body" | jq '.' 2>/dev/null || echo "$body"
            echo ""
        fi
        return 0
    else
        echo -e "${RED}✗ FAILED (HTTP $http_code)${NC}"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
        echo ""
        return 1
    fi
}

# Run tests
echo "Running endpoint tests:"
echo "----------------------"

# Health check (no auth)
test_endpoint "Health Check" "/health" "false"

# Authenticated endpoints
test_endpoint "Site Info" "/site-info" "true"
test_endpoint "Plugins" "/plugins" "true"
test_endpoint "Themes" "/themes" "true"
test_endpoint "System Status" "/system-status" "true"
test_endpoint "User Count" "/user-count" "true"
test_endpoint "Post Stats" "/post-stats" "true"

echo ""
echo "Testing authentication:"
echo "----------------------"

# Test invalid API key
echo -n "Invalid API Key... "
response=$(curl -s -w "\n%{http_code}" -H "X-WP-LLM-API-Key: invalid_key" "${BASE_URL}/site-info")
http_code=$(echo "$response" | tail -n1)

if [ "$http_code" == "401" ]; then
    echo -e "${GREEN}✓ Correctly rejected${NC}"
else
    echo -e "${RED}✗ FAILED (Expected 401, got $http_code)${NC}"
fi

# Test missing API key
echo -n "Missing API Key... "
response=$(curl -s -w "\n%{http_code}" "${BASE_URL}/site-info")
http_code=$(echo "$response" | tail -n1)

if [ "$http_code" == "401" ]; then
    echo -e "${GREEN}✓ Correctly rejected${NC}"
else
    echo -e "${RED}✗ FAILED (Expected 401, got $http_code)${NC}"
fi

echo ""
echo "Testing rate limiting:"
echo "---------------------"
echo "Making 5 rapid requests..."

for i in {1..5}; do
    echo -n "Request $i... "
    response=$(curl -s -w "\n%{http_code}" -H "X-WP-LLM-API-Key: $API_KEY" "${BASE_URL}/site-info")
    http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" == "200" ]; then
        echo -e "${GREEN}✓${NC}"
    elif [ "$http_code" == "429" ]; then
        echo -e "${YELLOW}✓ Rate limited (as expected)${NC}"
    else
        echo -e "${RED}✗ Unexpected response: $http_code${NC}"
    fi
    
    sleep 0.5
done

echo ""
echo "Tests complete!"
echo ""
echo "To see detailed responses, run: VERBOSE=true $0"
