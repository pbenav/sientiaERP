#!/usr/bin/env bash
#
# nexERP TUI Client
# Terminal User Interface for nexERP Point of Sale System
#

# Configuration
API_URL="${API_URL:-http://localhost:8000/api}"
CONFIG_FILE="${HOME}/.nexerp/config"

# Colors and styling
BOLD='\033[1m'
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Initialize configuration
init_config() {
    mkdir -p "${HOME}/.nexerp"
    if [ ! -f "$CONFIG_FILE" ]; then
        echo "API_URL=$API_URL" > "$CONFIG_FILE"
    fi
    source "$CONFIG_FILE"
}

# Clear screen and show header
show_header() {
    clear
    echo -e "${BOLD}${CYAN}"
    echo "╔═══════════════════════════════════════════════════════════════╗"
    echo "║                         nexERP POS                            ║"
    echo "║              Terminal Point of Sale Interface                 ║"
    echo "╚═══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# API call wrapper
api_call() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    
    if [ -n "$data" ]; then
        curl -s -X "$method" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "${API_URL}${endpoint}"
    else
        curl -s -X "$method" \
            -H "Accept: application/json" \
            "${API_URL}${endpoint}"
    fi
}

# Main menu
show_main_menu() {
    show_header
    echo -e "${BOLD}Main Menu${NC}"
    echo
    echo "  1) Products Management"
    echo "  2) Customers Management"
    echo "  3) Sales / Checkout"
    echo "  4) View Sales History"
    echo "  5) Settings"
    echo "  0) Exit"
    echo
    echo -n "Select option: "
}

# Products menu
products_menu() {
    while true; do
        show_header
        echo -e "${BOLD}Products Management${NC}"
        echo
        echo "  1) List Products"
        echo "  2) Add Product"
        echo "  3) Search Product"
        echo "  0) Back to Main Menu"
        echo
        echo -n "Select option: "
        read option
        
        case $option in
            1) list_products ;;
            2) add_product ;;
            3) search_product ;;
            0) return ;;
            *) echo -e "${RED}Invalid option${NC}"; sleep 1 ;;
        esac
    done
}

# List products
list_products() {
    show_header
    echo -e "${BOLD}Product List${NC}"
    echo
    
    response=$(api_call "GET" "/products")
    
    if [ -z "$response" ]; then
        echo -e "${RED}Error: Could not connect to API${NC}"
    else
        echo "$response" | jq -r '.[] | "\(.id)\t\(.code)\t\(.name)\t$\(.price)\tStock: \(.stock)"' 2>/dev/null || echo "$response"
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Add product
add_product() {
    show_header
    echo -e "${BOLD}Add New Product${NC}"
    echo
    
    echo -n "Product Code: "
    read code
    echo -n "Product Name: "
    read name
    echo -n "Description: "
    read description
    echo -n "Price: "
    read price
    echo -n "Initial Stock: "
    read stock
    
    data=$(jq -n \
        --arg code "$code" \
        --arg name "$name" \
        --arg description "$description" \
        --arg price "$price" \
        --arg stock "$stock" \
        '{code: $code, name: $name, description: $description, price: ($price | tonumber), stock: ($stock | tonumber)}')
    
    response=$(api_call "POST" "/products" "$data")
    
    if echo "$response" | jq -e '.id' > /dev/null 2>&1; then
        echo -e "${GREEN}Product added successfully!${NC}"
    else
        echo -e "${RED}Error adding product:${NC}"
        echo "$response" | jq '.'
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Search product
search_product() {
    show_header
    echo -e "${BOLD}Search Product${NC}"
    echo
    
    echo -n "Enter product ID: "
    read product_id
    
    response=$(api_call "GET" "/products/${product_id}")
    
    if echo "$response" | jq -e '.id' > /dev/null 2>&1; then
        echo
        echo -e "${BOLD}Product Details:${NC}"
        echo "$response" | jq -r '"ID: \(.id)\nCode: \(.code)\nName: \(.name)\nDescription: \(.description // "N/A")\nPrice: $\(.price)\nStock: \(.stock)\nActive: \(.active)"'
    else
        echo -e "${RED}Product not found${NC}"
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Customers menu
customers_menu() {
    while true; do
        show_header
        echo -e "${BOLD}Customers Management${NC}"
        echo
        echo "  1) List Customers"
        echo "  2) Add Customer"
        echo "  0) Back to Main Menu"
        echo
        echo -n "Select option: "
        read option
        
        case $option in
            1) list_customers ;;
            2) add_customer ;;
            0) return ;;
            *) echo -e "${RED}Invalid option${NC}"; sleep 1 ;;
        esac
    done
}

# List customers
list_customers() {
    show_header
    echo -e "${BOLD}Customer List${NC}"
    echo
    
    response=$(api_call "GET" "/customers")
    
    if [ -z "$response" ]; then
        echo -e "${RED}Error: Could not connect to API${NC}"
    else
        echo "$response" | jq -r '.[] | "\(.id)\t\(.code)\t\(.name)\t\(.email // "N/A")"' 2>/dev/null || echo "$response"
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Add customer
add_customer() {
    show_header
    echo -e "${BOLD}Add New Customer${NC}"
    echo
    
    echo -n "Customer Code: "
    read code
    echo -n "Customer Name: "
    read name
    echo -n "Email (optional): "
    read email
    echo -n "Phone (optional): "
    read phone
    echo -n "Address (optional): "
    read address
    
    data=$(jq -n \
        --arg code "$code" \
        --arg name "$name" \
        --arg email "$email" \
        --arg phone "$phone" \
        --arg address "$address" \
        '{code: $code, name: $name, email: $email, phone: $phone, address: $address}')
    
    response=$(api_call "POST" "/customers" "$data")
    
    if echo "$response" | jq -e '.id' > /dev/null 2>&1; then
        echo -e "${GREEN}Customer added successfully!${NC}"
    else
        echo -e "${RED}Error adding customer:${NC}"
        echo "$response" | jq '.'
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Sales checkout
sales_checkout() {
    show_header
    echo -e "${BOLD}${GREEN}Sales Checkout${NC}"
    echo
    
    # List available products
    echo -e "${BOLD}Available Products:${NC}"
    products=$(api_call "GET" "/products")
    echo "$products" | jq -r '.[] | "\(.id)\t\(.name)\t$\(.price)\tStock: \(.stock)"'
    
    echo
    echo -n "Product ID: "
    read product_id
    echo -n "Quantity: "
    read quantity
    echo -n "Customer ID (optional, press Enter to skip): "
    read customer_id
    
    if [ -z "$customer_id" ]; then
        data=$(jq -n \
            --arg product_id "$product_id" \
            --arg quantity "$quantity" \
            '{product_id: ($product_id | tonumber), quantity: ($quantity | tonumber)}')
    else
        data=$(jq -n \
            --arg customer_id "$customer_id" \
            --arg product_id "$product_id" \
            --arg quantity "$quantity" \
            '{customer_id: ($customer_id | tonumber), product_id: ($product_id | tonumber), quantity: ($quantity | tonumber)}')
    fi
    
    response=$(api_call "POST" "/sales" "$data")
    
    if echo "$response" | jq -e '.id' > /dev/null 2>&1; then
        echo
        echo -e "${GREEN}${BOLD}Sale completed successfully!${NC}"
        echo
        echo -e "${BOLD}Receipt:${NC}"
        echo "Sale ID: $(echo "$response" | jq -r '.id')"
        echo "Product: $(echo "$response" | jq -r '.product.name')"
        echo "Quantity: $(echo "$response" | jq -r '.quantity')"
        echo "Unit Price: $$(echo "$response" | jq -r '.unit_price')"
        echo -e "${BOLD}Total: $$(echo "$response" | jq -r '.total')${NC}"
    else
        echo -e "${RED}Error processing sale:${NC}"
        echo "$response" | jq '.'
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# View sales history
view_sales() {
    show_header
    echo -e "${BOLD}Sales History${NC}"
    echo
    
    response=$(api_call "GET" "/sales")
    
    if [ -z "$response" ]; then
        echo -e "${RED}Error: Could not connect to API${NC}"
    else
        echo "$response" | jq -r '.[] | "ID: \(.id) | Product: \(.product.name) | Qty: \(.quantity) | Total: $\(.total) | Date: \(.created_at)"' 2>/dev/null || echo "$response"
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Settings menu
settings_menu() {
    show_header
    echo -e "${BOLD}Settings${NC}"
    echo
    echo "Current API URL: $API_URL"
    echo
    echo -n "Enter new API URL (or press Enter to keep current): "
    read new_url
    
    if [ -n "$new_url" ]; then
        API_URL="$new_url"
        echo "API_URL=$API_URL" > "$CONFIG_FILE"
        echo -e "${GREEN}API URL updated!${NC}"
    fi
    
    echo
    echo -n "Press Enter to continue..."
    read
}

# Main program
main() {
    # Check dependencies
    if ! command -v jq &> /dev/null; then
        echo -e "${RED}Error: jq is required but not installed.${NC}"
        echo "Please install jq: sudo apt-get install jq"
        exit 1
    fi
    
    if ! command -v curl &> /dev/null; then
        echo -e "${RED}Error: curl is required but not installed.${NC}"
        echo "Please install curl: sudo apt-get install curl"
        exit 1
    fi
    
    init_config
    
    while true; do
        show_main_menu
        read option
        
        case $option in
            1) products_menu ;;
            2) customers_menu ;;
            3) sales_checkout ;;
            4) view_sales ;;
            5) settings_menu ;;
            0) 
                clear
                echo -e "${GREEN}Thank you for using nexERP!${NC}"
                exit 0
                ;;
            *) echo -e "${RED}Invalid option${NC}"; sleep 1 ;;
        esac
    done
}

# Run main program
main
