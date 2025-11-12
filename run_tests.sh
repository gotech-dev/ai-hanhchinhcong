#!/bin/bash

# Script để chạy tests - KHÔNG refresh database

echo "🧪 Running Chatbot Improvement Tests..."
echo "⚠️  Note: Database will NOT be refreshed - using existing data"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${RED}❌ .env file not found!${NC}"
    exit 1
fi

# Check if database connection works
echo -e "${YELLOW}📊 Checking database connection...${NC}"
php artisan db:show > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Database connection failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Database connection OK${NC}"
echo ""

# Run Feature Tests
echo -e "${YELLOW}🧪 Running Feature Tests...${NC}"
php artisan test --filter=ChatbotImprovementTest

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Feature tests passed!${NC}"
else
    echo -e "${RED}❌ Feature tests failed!${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}📝 Manual Frontend Tests:${NC}"
echo "Please follow the guide in: tests/Manual/FrontendTestGuide.md"
echo ""
echo -e "${GREEN}✅ All automated tests completed!${NC}"


